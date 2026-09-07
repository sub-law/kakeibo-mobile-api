<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\CategoryGroup;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // 生活費
            ['group' => '生活費', 'name' => '食料品'],
            ['group' => '生活費', 'name' => '日用品'],
            ['group' => '生活費', 'name' => '猫飼育費'],
            ['group' => '生活費', 'name' => '猫医療費'],
            ['group' => '生活費', 'name' => '衣料品'],
            ['group' => '生活費', 'name' => '書籍・ゲーム'],

            // 固定費
            ['group' => '固定費', 'name' => '電気代'],
            ['group' => '固定費', 'name' => 'ガス代'],
            ['group' => '固定費', 'name' => '水道代'],
            ['group' => '固定費', 'name' => '通信費'],

            // 医療・健康
            ['group' => '医療・健康', 'name' => '健康管理費'],
            ['group' => '医療・健康', 'name' => '医療費'],
            ['group' => '医療・健康', 'name' => '生命保険料'],
            ['group' => '医療・健康', 'name' => '健康保険料'],

            // 交通
            ['group' => '交通', 'name' => '交通費'],

            // 美容・自己投資
            ['group' => '美容・自己投資', 'name' => '美容費'],

            // 交際・娯楽
            ['group' => '交際・娯楽', 'name' => '交際費'],
            ['group' => '交際・娯楽', 'name' => '娯楽・レジャー'],
            ['group' => '交際・娯楽', 'name' => 'タバコ'],

            // 大きな買い物
            ['group' => '大きな買い物', 'name' => '家電・家具'],

            // 継続課金
            ['group' => '継続課金', 'name' => '継続課金'],

            // 税金・社会保障
            ['group' => '税金・社会保障', 'name' => '年金'],
            ['group' => '税金・社会保障', 'name' => '税金'],

            // 特別計上
            ['group' => '特別計上', 'name' => '特別計上'],
        ];

        foreach ($categories as $c) {
            $group = CategoryGroup::where('name', $c['group'])->first();

            Category::create([
                'category_group_id' => $group->id,
                'name' => $c['name'],
            ]);
        }
    }
}
