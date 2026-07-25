<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CategoryGroup;

class CategoryGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            '生活費',
            '固定費',
            '医療・健康',
            '交通',
            '美容・自己投資',
            '交際・娯楽',
            '大きな買い物',
            '継続課金',
            '税金・社会保障',
            '特別計上',
        ];

        foreach ($groups as $name) {
            CategoryGroup::create(['name' => $name]);
        }
    }
}
