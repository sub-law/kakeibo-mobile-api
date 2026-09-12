<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AssetBalance;
use App\Models\BudgetAlertSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if ($user === null) {
            throw new RuntimeException('デモデータの紐付け先ユーザーが存在しません。');
        }

        $categories = $this->categories([
            '食料品',
            '交通費',
            '娯楽・レジャー',
            '電気代',
            '通信費',
        ]);
        $accounts = $this->accounts($user, ['A銀行', 'A証券', '現金']);
        $currentMonth = now()->toImmutable()->startOfMonth();

        DB::transaction(function () use ($user, $categories, $accounts, $currentMonth): void {
            $monthlyData = [
                -2 => [
                    'income' => 280000,
                    'expenses' => [
                        '食料品' => 36000,
                        '交通費' => 7000,
                        '娯楽・レジャー' => 8000,
                    ],
                    'assets' => [
                        'A銀行' => 300000,
                        'A証券' => 150000,
                        '現金' => 30000,
                    ],
                ],
                -1 => [
                    'income' => 300000,
                    'expenses' => [
                        '食料品' => 42000,
                        '交通費' => 8000,
                        '娯楽・レジャー' => 12000,
                    ],
                    'assets' => [
                        'A銀行' => 330000,
                        'A証券' => 160000,
                        '現金' => 25000,
                    ],
                ],
                0 => [
                    'income' => 300000,
                    'expenses' => [
                        '食料品' => 38000,
                        '交通費' => 8000,
                        '娯楽・レジャー' => 22000,
                    ],
                    'assets' => [
                        'A銀行' => 360000,
                        'A証券' => 175000,
                        '現金' => 20000,
                    ],
                ],
            ];

            foreach ($monthlyData as $monthOffset => $data) {
                $date = $currentMonth->addMonths($monthOffset)->toDateString();

                Income::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => $date,
                        'memo' => '給与',
                    ],
                    ['amount' => $data['income']]
                );

                foreach ($data['expenses'] as $categoryName => $amount) {
                    Expense::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'category_id' => $categories[$categoryName]->id,
                            'date' => $date,
                            'memo' => $this->expenseMemo($categoryName),
                        ],
                        ['amount' => $amount]
                    );
                }

                foreach ($data['assets'] as $accountName => $amount) {
                    AssetBalance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'account_id' => $accounts[$accountName]->id,
                            'date' => $date,
                        ],
                        ['amount' => $amount]
                    );
                }
            }

            $this->seedFixedExpenses($user, $categories);
            $this->seedBudgetAlertSettings($user, $categories);
        });
    }

    /**
     * @param  list<string>  $names
     * @return Collection<string, Category>
     */
    private function categories(array $names): Collection
    {
        $categories = Category::query()
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');
        $missingNames = collect($names)->diff($categories->keys());

        if ($missingNames->isNotEmpty()) {
            throw new RuntimeException(
                'デモデータに必要なカテゴリが存在しません: '.$missingNames->implode(', ')
            );
        }

        return $categories;
    }

    /**
     * @param  list<string>  $names
     * @return Collection<string, Account>
     */
    private function accounts(User $user, array $names): Collection
    {
        $accounts = $user->accounts()
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');
        $missingNames = collect($names)->diff($accounts->keys());

        if ($missingNames->isNotEmpty()) {
            throw new RuntimeException(
                'デモデータに必要な口座が存在しません: '.$missingNames->implode(', ')
            );
        }

        return $accounts;
    }

    private function expenseMemo(string $categoryName): string
    {
        return match ($categoryName) {
            '食料品' => '食料品の購入',
            '交通費' => '電車・バス',
            '娯楽・レジャー' => '休日のレジャー',
        };
    }

    /**
     * @param  Collection<string, Category>  $categories
     */
    private function seedFixedExpenses(User $user, Collection $categories): void
    {
        $fixedExpenses = [
            ['category' => '電気代', 'amount' => 8000, 'memo' => '電気代'],
            ['category' => '通信費', 'amount' => 5000, 'memo' => '通信費'],
        ];

        foreach ($fixedExpenses as $fixedExpense) {
            FixedExpense::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category_id' => $categories[$fixedExpense['category']]->id,
                    'memo' => $fixedExpense['memo'],
                ],
                [
                    'amount' => $fixedExpense['amount'],
                    'is_enabled' => true,
                ]
            );
        }
    }

    /**
     * @param  Collection<string, Category>  $categories
     */
    private function seedBudgetAlertSettings(User $user, Collection $categories): void
    {
        $settings = [
            ['category' => '食料品', 'monthly_budget' => 50000],
            ['category' => '娯楽・レジャー', 'monthly_budget' => 20000],
        ];

        foreach ($settings as $setting) {
            BudgetAlertSetting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'category_id' => $categories[$setting['category']]->id,
                ],
                [
                    'monthly_budget' => $setting['monthly_budget'],
                    'warning_threshold_percent' => 70,
                    'is_enabled' => true,
                ]
            );
        }
    }
}
