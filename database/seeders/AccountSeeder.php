<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('accounts')->insert([
            [
                'name' => 'A銀行',
                'type' => 'bank',
            ],
            [
                'name' => 'B銀行',
                'type' => 'bank',
            ],
            [
                'name' => 'C銀行',
                'type' => 'bank',
            ],
            [
                'name' => 'A証券',
                'type' => 'securities',
            ],
            [
                'name' => '金庫',
                'type' => 'cash',
            ],
            [
                'name' => '現金',
                'type' => 'cash',
            ],
        ]);
    }
}
