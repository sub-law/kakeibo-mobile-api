<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Income;

class IncomeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Income::create([
            'user_id' => 1,
            'amount' => 5000,
            'date' => '2026-06-20',
            'memo' => 'テスト入金1',
        ]);

        Income::create([
            'user_id' => 1,
            'amount' => 7500,
            'date' => '2026-06-21',
            'memo' => 'テスト入金2',
        ]);

        Income::create([
            'user_id' => 1,
            'amount' => 10000,
            'date' => '2026-06-22',
            'memo' => 'テスト入金3',
        ]);

        Income::create([
            'user_id' => 1,
            'amount' => 10000,
            'date' => '2026-05-22',
            'memo' => 'テスト入金4',
        ]);

        Income::create([
            'user_id' => 1,
            'amount' => 5000,
            'date' => '2026-05-23',
            'memo' => 'テスト入金5',
        ]);

        Income::create([
            'user_id' => 1,
            'amount' => 25000,
            'date' => '2026-05-25',
            'memo' => 'テスト入金6',
        ]);
    }
}
