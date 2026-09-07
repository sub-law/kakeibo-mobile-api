<?php

namespace App\Services;

use App\Models\User;

class MonthlyStatsService
{
    public function build(User $user, int $year): array
    {
        $income = $this->aggregateIncome($user, $year);
        $expense = $this->aggregateExpense($user, $year);
        $assets = $this->aggregateAssets($user, $year);

        return $this->buildResponse($year, $income, $expense, $assets);
    }

    private function aggregateIncome(User $user, int $year): array
    {
        $monthly = array_fill(1, 12, 0);

        foreach ($user->incomes()->whereYear('date', $year)->get(['date', 'amount']) as $income) {
            $month = (int) substr((string) $income->date, 5, 2);
            $monthly[$month] += (int) $income->amount;
        }

        return $monthly;
    }

    private function aggregateExpense(User $user, int $year): array
    {
        $monthly = array_fill(1, 12, 0);

        foreach ($user->expenses()->whereYear('date', $year)->get(['date', 'amount']) as $expense) {
            $month = (int) substr((string) $expense->date, 5, 2);
            $monthly[$month] += (int) $expense->amount;
        }

        return $monthly;
    }

    private function aggregateAssets(User $user, int $year): array
    {
        $assetsByMonth = array_fill(1, 12, 0);
        $assetsByAccount = array_fill(1, 12, []);
        $accounts = [];
        $recordedMonths = [];

        $balances = $user->assetBalances()
            ->with('account:id,name,type')
            ->whereYear('date', $year)
            ->orderBy('account_id')
            ->get(['date', 'amount', 'account_id']);

        foreach ($balances as $balance) {
            $month = (int) substr((string) $balance->date, 5, 2);
            $amount = (int) ($balance->amount ?? 0);

            $assetsByMonth[$month] += $amount;
            $assetsByAccount[$month][$balance->account_id] =
                ($assetsByAccount[$month][$balance->account_id] ?? 0) + $amount;
            $recordedMonths[$month] = true;
            $accounts[$balance->account_id] = [
                'id' => $balance->account->id,
                'name' => $balance->account->name,
                'type' => $balance->account->type,
            ];
        }

        return [
            'by_month' => $assetsByMonth,
            'by_account' => $assetsByAccount,
            'accounts' => $accounts,
            'recorded_months' => array_keys($recordedMonths),
        ];
    }

    private function buildResponse(int $year, array $income, array $expense, array $assets): array
    {
        $monthly = [];

        foreach (range(1, 12) as $month) {
            $assetsByAccount = [];

            foreach (array_keys($assets['accounts']) as $accountId) {
                $assetsByAccount[$accountId] = $assets['by_account'][$month][$accountId] ?? 0;
            }

            $monthly[] = [
                'month' => $month,
                'income' => $income[$month],
                'expense' => $expense[$month],
                'assets' => $assets['by_month'][$month],
                'assets_by_account' => (object) $assetsByAccount,
            ];
        }

        $recordedMonths = $assets['recorded_months'];
        sort($recordedMonths);

        $firstAssetMonth = $recordedMonths[0] ?? null;
        $latestAssetMonth = $recordedMonths === []
            ? null
            : $recordedMonths[count($recordedMonths) - 1];
        $latestAssets = $latestAssetMonth === null
            ? 0
            : $assets['by_month'][$latestAssetMonth];
        $assetChange = $firstAssetMonth === null
            ? 0
            : $latestAssets - $assets['by_month'][$firstAssetMonth];
        $totalIncome = array_sum($income);
        $totalExpense = array_sum($expense);

        return [
            'year' => $year,
            'accounts' => array_values($assets['accounts']),
            'monthly' => $monthly,
            'totals' => [
                'income' => $totalIncome,
                'expense' => $totalExpense,
                'balance' => $totalIncome - $totalExpense,
                'latest_assets' => $latestAssets,
                'asset_change' => $assetChange,
            ],
        ];
    }
}
