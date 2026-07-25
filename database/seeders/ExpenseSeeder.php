<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Expense;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $dummy = [
            [
                'user_id' => 1,
                'date' => '2026-06-01',
                'amount' => 1200,
                'memo' => 'コンビニで昼食',
                'category_id' => 1, // 食費
            ],
            [
                'user_id' => 1,
                'date' => '2026-06-02',
                'amount' => 3500,
                'memo' => '日用品まとめ買い',
                'category_id' => 2, // 日用品
            ],
            [
                'user_id' => 1,
                'date' => '2026-06-03',
                'amount' => 780,
                'memo' => '電車代',
                'category_id' => 15, // 交通費
            ],
            [
                'user_id' => 1,
                'date' => '2026-06-04',
                'amount' => 5400,
                'memo' => '飲み会',
                'category_id' => 17, // 交際費
            ],
        ];

        foreach ($dummy as $data) {
            Expense::create($data);
        }
    }
}
