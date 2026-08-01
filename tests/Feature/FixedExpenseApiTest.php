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
        ];

        foreach ($requests as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_user_can_create_a_fixed_expense_and_memo_is_required(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses', [
                'category_id' => $category->id,
                'amount' => 12000,
                'memo' => '生命保険料',
                'is_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'amount' => 12000,
                'memo' => '生命保険料',
                'is_enabled' => true,
            ]);

        $this->assertDatabaseHas('fixed_expenses', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 12000,
            'memo' => '生命保険料',
            'is_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses', [
                'category_id' => $category->id,
                'amount' => 12000,
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['memo'])
            ->assertJsonPath('errors.memo.0', '用途は必須です。');
    }

    public function test_user_can_list_view_and_update_only_their_fixed_expenses(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $fixedExpense = FixedExpense::factory()
            ->for($user)
            ->for($category)
            ->create();
        FixedExpense::factory()->for($otherUser)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fixed-expenses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $fixedExpense->id)
            ->assertJsonPath('0.category.id', $category->id)
            ->assertJsonPath(
                '0.category.group.id',
                $category->category_group_id
            );

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/fixed-expenses/{$fixedExpense->id}")
            ->assertOk()
            ->assertJsonPath('id', $fixedExpense->id);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/fixed-expenses/{$fixedExpense->id}", [
                'category_id' => $newCategory->id,
                'amount' => 9800,
                'memo' => '動画配信サービス',
                'is_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'category_id' => $newCategory->id,
                'amount' => 9800,
                'memo' => '動画配信サービス',
                'is_enabled' => false,
            ]);
    }

    public function test_user_cannot_view_or_update_another_users_fixed_expense(): void
    {
        $user = User::factory()->create();
        $otherFixedExpense = FixedExpense::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/fixed-expenses/{$otherFixedExpense->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/fixed-expenses/{$otherFixedExpense->id}", [
                'category_id' => $category->id,
                'amount' => 1000,
                'memo' => '変更不可',
                'is_enabled' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('fixed_expenses', [
            'id' => $otherFixedExpense->id,
            'memo' => '変更不可',
        ]);
    }
}
