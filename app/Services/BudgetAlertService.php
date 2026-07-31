<?php

namespace App\Services;

use App\Models\BudgetAlertSetting;
use App\Models\User;

class BudgetAlertService
{
    public function build(User $user): array
    {
        $today = now()->startOfDay();
        $settings = $user->budgetAlertSettings()
            ->where('is_enabled', true)
            ->with([
                'category.group',
                'reads' => fn ($query) => $query
                    ->where('year', $today->year)
                    ->where('month', $today->month),
            ])
            ->orderBy('category_id')
            ->get();

        if ($settings->isEmpty()) {
            return ['alerts' => []];
        }

        $spentByCategory = $user->expenses()
            ->whereBetween('date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->toDateString(),
            ])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');
        $alerts = [];

        foreach ($settings as $setting) {
            $alert = $this->buildAlert(
                $setting,
                (int) ($spentByCategory[$setting->category_id] ?? 0)
            );

            if (! $alert) {
                continue;
            }

            $isRead = $setting->reads->contains(
                fn ($read) => $read->level === $alert['level']
            );

            if (! $isRead) {
                $alerts[] = $alert;
            }
        }

        return ['alerts' => $alerts];
    }

    public function currentAlert(
        User $user,
        BudgetAlertSetting $setting
    ): ?array {
        if (
            $setting->user_id !== $user->id
            || ! $setting->is_enabled
        ) {
            return null;
        }

        $today = now()->startOfDay();
        $spentAmount = (int) $user->expenses()
            ->where('category_id', $setting->category_id)
            ->whereBetween('date', [
                $today->copy()->startOfMonth()->toDateString(),
                $today->toDateString(),
            ])
            ->sum('amount');

        return $this->buildAlert($setting, $spentAmount);
    }

    private function buildAlert(
        BudgetAlertSetting $setting,
        int $spentAmount
    ): ?array {
        if (! $setting->is_enabled || ! $setting->category) {
            return null;
        }

        $monthlyBudget = $setting->monthly_budget;
        $warningThresholdPercent = $setting->warning_threshold_percent;
        $usageRate = round($spentAmount / $monthlyBudget * 100, 1);
        $categoryName = $setting->category->name;

        if ($spentAmount >= $monthlyBudget) {
            $level = 'danger';
            $message = $spentAmount === $monthlyBudget
                ? sprintf('%sの出金が設定金額に達しました。', $categoryName)
                : sprintf(
                    '%sの出金が設定金額を%s円超えました。',
                    $categoryName,
                    number_format($spentAmount - $monthlyBudget)
                );
        } elseif (
            $spentAmount * 100
            >= $monthlyBudget * $warningThresholdPercent
        ) {
            $level = 'warning';
            $message = sprintf(
                '%sの出金が設定金額の%s%%に達しました。',
                $categoryName,
                $warningThresholdPercent
            );
        } else {
            return null;
        }

        return [
            'setting_id' => $setting->id,
            'category' => [
                'id' => $setting->category->id,
                'name' => $categoryName,
                'group' => [
                    'id' => $setting->category->group->id,
                    'name' => $setting->category->group->name,
                ],
            ],
            'level' => $level,
            'monthly_budget' => $monthlyBudget,
            'warning_threshold_percent' => $warningThresholdPercent,
            'spent_amount' => $spentAmount,
            'usage_rate' => $usageRate,
            'message' => $message,
        ];
    }
}
