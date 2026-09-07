<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->value('id');

        if ($userId === null) {
            throw new \RuntimeException('口座の紐付け先ユーザーが存在しません。');
        }

        DB::table('accounts')->insert([
            [
                'user_id' => $userId,
                'name' => 'A銀行',
                'type' => 'bank',
            ],
            [
                'user_id' => $userId,
                'name' => 'B銀行',
                'type' => 'bank',
            ],
            [
                'user_id' => $userId,
                'name' => 'C銀行',
                'type' => 'bank',
            ],
            [
                'user_id' => $userId,
                'name' => 'A証券',
                'type' => 'securities',
            ],
            [
                'user_id' => $userId,
                'name' => '金庫',
                'type' => 'cash',
            ],
            [
                'user_id' => $userId,
                'name' => '現金',
                'type' => 'cash',
            ],
        ]);
    }
}
