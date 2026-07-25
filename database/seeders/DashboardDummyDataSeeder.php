<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AssetBalance;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DashboardDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('本番環境ではダミーデータを登録できません。');
        }

        $email = env('DEFAULT_USER_EMAIL');

        if (! $email) {
            throw new RuntimeException('対象ユーザーの環境変数が設定されていません。');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException('ダミーデータの対象ユーザーが存在しません。');
        }

        $incomes = [
            1 => 280000,
            2 => 172000,
            3 => 273000,
            4 => 485000,
            5 => 390000,
            6 => 291000,
            7 => 130000,
        ];

        $expenses = [
            1 => [
                '食料品' => 144000,
                '日用品' => 2000,
                '猫飼育費' => 4000,
                '健康管理費' => 24000,
                '医療費' => 13000,
                '交通費' => 8000,
                '交際費' => 11000,
                '衣料品' => 4000,
                '家電・家具' => 19000,
                '通信費' => 17000,
                '電気代' => 15000,
                'ガス代' => 26000,
                '水道代' => 18000,
                '継続課金' => 24000,
                '生命保険料' => 10000,
                '特別計上' => 23000,
            ],
            2 => [
                '食料品' => 135000,
                '日用品' => 8000,
                '猫飼育費' => 2000,
                '医療費' => 25000,
                '交通費' => 12000,
                '交際費' => 28000,
                '衣料品' => 6000,
                '娯楽・レジャー' => 201000,
                '家電・家具' => 1000,
                '通信費' => 21000,
                '電気代' => 13000,
                'ガス代' => 21000,
                '継続課金' => 2000,
                '生命保険料' => 10000,
                '健康保険料' => 116000,
                '特別計上' => 100000,
            ],
            3 => [
                '食料品' => 113000,
                '日用品' => 15000,
                '猫飼育費' => 4000,
                '健康管理費' => 10000,
                '医療費' => 14000,
                '交通費' => 2000,
                '美容費' => 5000,
                '交際費' => 3000,
                '衣料品' => 6000,
                '通信費' => 21000,
                '電気代' => 14000,
                'ガス代' => 14000,
                '水道代' => 13000,
                '継続課金' => 13000,
                '生命保険料' => 10000,
                '年金' => 53000,
                '特別計上' => 23000,
            ],
            4 => [
                '食料品' => 104000,
                '日用品' => 10000,
                '猫飼育費' => 6000,
                '健康管理費' => 17000,
                '医療費' => 22000,
                '交通費' => 3000,
                '交際費' => 9000,
                '衣料品' => 8000,
                '娯楽・レジャー' => 4000,
                '家電・家具' => 8000,
                '通信費' => 18000,
                '電気代' => 11000,
                'ガス代' => 12000,
                '継続課金' => 8000,
                '生命保険料' => 10000,
                '年金' => 18000,
                '特別計上' => 10000,
            ],
            5 => [
                '食料品' => 121000,
                '日用品' => 8000,
                '猫飼育費' => 12000,
                '健康管理費' => 10000,
                '医療費' => 9000,
                '交通費' => 3000,
                '美容費' => 10000,
                '交際費' => 18000,
                '衣料品' => 13000,
                '通信費' => 18000,
                '電気代' => 12000,
                'ガス代' => 8000,
                '継続課金' => 2000,
                '生命保険料' => 10000,
                '年金' => 18000,
                '特別計上' => 1000,
            ],
            6 => [
                '食料品' => 138000,
                '日用品' => 9000,
                '猫飼育費' => 18000,
                '健康管理費' => 18000,
                '医療費' => 10000,
                '交通費' => 1000,
                '交際費' => 11000,
                '書籍・ゲーム' => 1000,
                '通信費' => 18000,
                '電気代' => 11000,
                'ガス代' => 5000,
                '継続課金' => 2000,
                '生命保険料' => 10000,
                '健康保険料' => 15000,
                '年金' => 18000,
                '特別計上' => 68000,
            ],
            7 => [
                '食料品' => 97000,
                '日用品' => 7000,
                '猫飼育費' => 13000,
                '健康管理費' => 10000,
                '交通費' => 4000,
                '交際費' => 30000,
                '通信費' => 16000,
                '継続課金' => 2000,
                '生命保険料' => 10000,
                '健康保険料' => 15000,
                '年金' => 18000,
            ],
        ];

        $assetBalances = [
            1 => ['A銀行' => 11000, 'B銀行' => 713000, 'C銀行' => 1737000, 'A証券' => 6018000, '金庫' => 1400000, '現金' => 296000],
            2 => ['A銀行' => 11000, 'B銀行' => 687000, 'C銀行' => 1526000, 'A証券' => 5979000, '金庫' => 1400000, '現金' => 87000],
            3 => ['A銀行' => 11000, 'B銀行' => 661000, 'C銀行' => 1242000, 'A証券' => 6299000, '金庫' => 1400000, '現金' => 211000],
            4 => ['A銀行' => 11000, 'B銀行' => 657000, 'C銀行' => 1407000, 'A証券' => 6083000, '金庫' => 1400000, '現金' => 237000],
            5 => ['A銀行' => 11000, 'B銀行' => 653000, 'C銀行' => 1458000, 'A証券' => 6122000, '金庫' => 1400000, '現金' => 269000],
            6 => ['A銀行' => 11000, 'B銀行' => 649000, 'C銀行' => 1553000, 'A証券' => 6167000, '金庫' => 1400000, '現金' => 103000],
            7 => ['A銀行' => 11000, 'B銀行' => 645000, 'C銀行' => 1321000, 'A証券' => 6122000, '金庫' => 1400000, '現金' => 112000],
        ];

        $categoryNames = collect($expenses)
            ->flatMap(fn (array $monthlyExpenses) => array_keys($monthlyExpenses))
            ->unique()
            ->values();
        $categories = Category::with('group')
            ->whereIn('name', $categoryNames)
            ->get()
            ->keyBy('name');
        $missingCategories = $categoryNames->diff($categories->keys());

        if ($missingCategories->isNotEmpty()) {
            throw new RuntimeException('必要なカテゴリが登録されていません。');
        }

        $accountNames = collect($assetBalances)
            ->flatMap(fn (array $monthlyBalances) => array_keys($monthlyBalances))
            ->unique()
            ->values();
        $accounts = Account::whereIn('name', $accountNames)->get()->keyBy('name');

        if ($accountNames->diff($accounts->keys())->isNotEmpty()) {
            throw new RuntimeException('必要な口座が登録されていません。');
        }

        DB::transaction(function () use ($user, $incomes, $expenses, $assetBalances, $categories, $accounts): void {
            foreach ($incomes as $month => $amount) {
                Income::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'date' => sprintf('2026-%02d-15', $month),
                        'memo' => '管理画面確認用ダミー入金',
                    ],
                    ['amount' => $amount]
                );
            }

            foreach ($expenses as $month => $monthlyExpenses) {
                foreach ($monthlyExpenses as $categoryName => $amount) {
                    Expense::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'category_id' => $categories[$categoryName]->id,
                            'date' => sprintf('2026-%02d-15', $month),
                            'memo' => '管理画面確認用ダミー出金',
                        ],
                        ['amount' => $amount]
                    );
                }
            }

            foreach ($assetBalances as $month => $monthlyBalances) {
                foreach ($monthlyBalances as $accountName => $amount) {
                    AssetBalance::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'account_id' => $accounts[$accountName]->id,
                            'date' => sprintf('2026-%02d-01', $month),
                        ],
                        ['amount' => $amount]
                    );
                }
            }
        });
    }
}
