<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FixedExpenseProcessingService
{
    public function preview(User $user, string $targetMonth): array
    {
        $expenseDate = $this->expenseDate($targetMonth);

        $fixedExpenses = $user->fixedExpenses()
            ->where('is_enabled', true)
            ->whereDoesntHave('processes', function ($query) use ($expenseDate) {
                $query->whereDate('target_month', $expenseDate);
            })
            ->with('category.group')
            ->orderBy('id')
            ->get();

        return [
            'target_month' => $targetMonth,
            'expense_date' => $expenseDate,
            'fixed_expenses' => $fixedExpenses,
            'count' => $fixedExpenses->count(),
            'total_amount' => $fixedExpenses->sum('amount'),
        ];
    }

    public function process(User $user, string $targetMonth): array
    {
        $expenseDate = $this->expenseDate($targetMonth);

        return DB::transaction(function () use ($user, $targetMonth, $expenseDate) {
            $fixedExpenses = $user->fixedExpenses()
                ->where('is_enabled', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $createdCount = 0;
            $skippedCount = 0;
            $totalAmount = 0;

            foreach ($fixedExpenses as $fixedExpense) {
                $alreadyProcessed = $fixedExpense->processes()
                    ->whereDate('target_month', $expenseDate)
                    ->exists();

                if ($alreadyProcessed) {
                    $skippedCount++;
                    continue;
                }

                $expense = $user->expenses()->create([
                    'date' => $expenseDate,
                    'amount' => $fixedExpense->amount,
                    'memo' => $fixedExpense->memo,
                    'category_id' => $fixedExpense->category_id,
                ]);

                $fixedExpense->processes()->create([
                    'expense_id' => $expense->id,
                    'target_month' => $expenseDate,
                ]);

                $createdCount++;
                $totalAmount += $fixedExpense->amount;
            }

            return [
                'message' => $createdCount > 0
                    ? '固定費の出金処理が完了しました。'
                    : '未処理の固定費はありません。',
                'target_month' => $targetMonth,
                'expense_date' => $expenseDate,
                'created_count' => $createdCount,
                'skipped_count' => $skippedCount,
                'total_amount' => $totalAmount,
            ];
        });
    }

    private function expenseDate(string $targetMonth): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $targetMonth)
            ->startOfMonth()
            ->format('Y-m-d');
    }
}
