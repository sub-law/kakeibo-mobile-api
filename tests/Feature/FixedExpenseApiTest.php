<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_fixed_expense_endpoints(): void
    {
        $requests = [
            ['getJson', '/api/fixed-expenses'],
            ['postJson', '/api/fixed-expenses'],
            ['getJson', '/api/fixed-expenses/1'],
            ['putJson', '/api/fixed-expenses/1'],
            ['getJson', '/api/fixed-expenses/process-preview?target_month=2026-08'],
            ['postJson', '/api/fixed-expenses/process'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_user_can_create_list_view_and_update_their_fixed_expenses(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses', [
                'category_id' => $category->id,
                'amount' => 12000,
                'memo' => '生命保険料',
                'is_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('amount', 12000)
            ->assertJsonPath('memo', '生命保険料')
            ->assertJsonPath('is_enabled', true);

        $fixedExpenseId = $response->json('id');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fixed-expenses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $fixedExpenseId)
            ->assertJsonPath('0.category.group.id', $category->category_group_id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/fixed-expenses/{$fixedExpenseId}")
            ->assertOk()
            ->assertJsonPath('memo', '生命保険料');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/fixed-expenses/{$fixedExpenseId}", [
                'category_id' => $newCategory->id,
                'amount' => 15000,
                'memo' => '生命保険料（変更後）',
                'is_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('category.id', $newCategory->id)
            ->assertJsonPath('amount', 15000)
            ->assertJsonPath('memo', '生命保険料（変更後）')
            ->assertJsonPath('is_enabled', false);
    }

    public function test_fixed_expense_returns_japanese_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', 'カテゴリは必須です。')
            ->assertJsonPath('errors.amount.0', '月額料金は必須です。')
            ->assertJsonPath('errors.memo.0', '用途は必須です。')
            ->assertJsonPath('errors.is_enabled.0', '固定費の有効・無効を指定してください。');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses', [
                'category_id' => 999999,
                'amount' => 0,
                'memo' => '',
                'is_enabled' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', '選択したカテゴリが存在しません。')
            ->assertJsonPath('errors.amount.0', '月額料金は1円以上で入力してください。')
            ->assertJsonPath('errors.memo.0', '用途は必須です。')
            ->assertJsonPath('errors.is_enabled.0', '固定費の有効・無効の形式が正しくありません。');
    }

    public function test_user_cannot_access_another_users_fixed_expense(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $otherFixedExpense = FixedExpense::factory()->for($otherUser)->for($category)->create([
            'amount' => 980,
            'memo' => '動画配信サービス',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fixed-expenses')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/fixed-expenses/{$otherFixedExpense->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/fixed-expenses/{$otherFixedExpense->id}", [
                'category_id' => $category->id,
                'amount' => 1980,
                'memo' => '変更不可',
                'is_enabled' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('fixed_expenses', [
            'id' => $otherFixedExpense->id,
            'amount' => 980,
            'is_enabled' => true,
        ]);
    }
}
