<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixedExpenseProcessingService
{
    public function preview(User $user, CarbonImmutable $targetMonth): array
    {
        $fixedExpenses = $this->unprocessedFixedExpenses(
            $user,
            $targetMonth
        )->get();

        return $this->previewPayload($fixedExpenses, $targetMonth);
    }

    public function process(User $user, CarbonImmutable $targetMonth): array
    {
        return DB::transaction(function () use ($user, $targetMonth): array {
            $fixedExpenses = $this->unprocessedFixedExpenses(
                $user,
                $targetMonth
            )
                ->lockForUpdate()
                ->get();

            $expenses = $fixedExpenses->map(function (
                FixedExpense $fixedExpense
            ) use ($user, $targetMonth): Expense {
                $expense = Expense::create([
                    'user_id' => $user->id,
                    'category_id' => $fixedExpense->category_id,
                    'date' => $targetMonth->startOfMonth()->toDateString(),
                    'amount' => $fixedExpense->amount,
                    'memo' => $fixedExpense->memo,
                ]);

                $fixedExpense->processes()->create([
                    'user_id' => $user->id,
                    'expense_id' => $expense->id,
                    'target_month' => $targetMonth->startOfMonth()->toDateString(),
                    'category_id' => $fixedExpense->category_id,
                    'amount' => $fixedExpense->amount,
                    'memo' => $fixedExpense->memo,
                ]);

                return $expense->load('category.group');
            });

            return [
                'message' => '固定費の出金処理が完了しました。',
                'target_month' => $targetMonth->format('Y-m'),
                'expense_date' => $targetMonth->startOfMonth()->toDateString(),
                'processed_count' => $expenses->count(),
                'total_amount' => $expenses->sum('amount'),
                'expenses' => $expenses->values(),
            ];
        });
    }

    private function unprocessedFixedExpenses(
        User $user,
        CarbonImmutable $targetMonth
    ) {
        return $user->fixedExpenses()
            ->where('is_enabled', true)
            ->whereDoesntHave('processes', function ($query) use ($targetMonth) {
                $query->whereDate(
                    'target_month',
                    $targetMonth->startOfMonth()->toDateString()
                );
            })
            ->with('category.group')
            ->orderBy('id');
    }

    private function previewPayload(
        Collection $fixedExpenses,
        CarbonImmutable $targetMonth
    ): array {
        return [
            'target_month' => $targetMonth->format('Y-m'),
            'expense_date' => $targetMonth->startOfMonth()->toDateString(),
            'count' => $fixedExpenses->count(),
            'total_amount' => $fixedExpenses->sum('amount'),
            'fixed_expenses' => $fixedExpenses->values(),
        ];
    }
}
