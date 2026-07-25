<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_expense_endpoints(): void
    {
        $requests = [
            ['getJson', '/api/expenses'],
            ['postJson', '/api/expenses'],
            ['getJson', '/api/expenses/1'],
            ['putJson', '/api/expenses/1'],
            ['deleteJson', '/api/expenses/1'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_authenticated_user_can_create_an_expense(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/expenses', [
                'date' => '2026-07-19',
                'amount' => 1500,
                'memo' => '昼食代',
                'category_id' => $category->id,
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'user_id' => $user->id,
                'date' => '2026-07-19',
                'amount' => 1500,
                'memo' => '昼食代',
                'category_id' => $category->id,
            ]);

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'date' => '2026-07-19',
            'amount' => 1500,
            'memo' => '昼食代',
            'category_id' => $category->id,
        ]);
    }

    public function test_expense_creation_returns_japanese_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/expenses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'amount', 'category_id'])
            ->assertJsonPath('errors.date.0', '日付は必須です。')
            ->assertJsonPath('errors.amount.0', '金額は必須です。')
            ->assertJsonPath('errors.category_id.0', 'カテゴリは必須です。');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/expenses', [
                'date' => 'invalid-date',
                'amount' => 0,
                'category_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'amount', 'category_id'])
            ->assertJsonPath('errors.date.0', '日付の形式が正しくありません。')
            ->assertJsonPath('errors.amount.0', '金額は1円以上で入力してください。')
            ->assertJsonPath('errors.category_id.0', '選択したカテゴリが存在しません。');
    }

    public function test_user_can_list_only_their_expenses_for_the_requested_month_in_date_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();

        $laterExpense = Expense::factory()->for($user)->for($category)->create(['date' => '2026-07-20']);
        $earlierExpense = Expense::factory()->for($user)->for($category)->create(['date' => '2026-07-01']);
        Expense::factory()->for($user)->for($category)->create(['date' => '2026-06-30']);
        Expense::factory()->for($otherUser)->for($category)->create(['date' => '2026-07-10']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/expenses?year=2026&month=7')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $earlierExpense->id)
            ->assertJsonPath('1.id', $laterExpense->id)
            ->assertJsonPath('0.category.id', $category->id)
            ->assertJsonPath('0.category.group.id', $category->category_group_id);
    }

    public function test_user_can_view_their_expense_with_its_category_and_group(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $expense = Expense::factory()->for($user)->for($category)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('id', $expense->id)
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('category.group.id', $category->category_group_id);
    }

    public function test_user_can_update_their_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();
        $newCategory = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/expenses/{$expense->id}", [
                'date' => '2026-07-20',
                'amount' => 3200,
                'memo' => '更新後の支出',
                'category_id' => $newCategory->id,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'date' => '2026-07-20',
                'amount' => 3200,
                'memo' => '更新後の支出',
                'category_id' => $newCategory->id,
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'user_id' => $user->id,
            'date' => '2026-07-20',
            'amount' => 3200,
            'memo' => '更新後の支出',
            'category_id' => $newCategory->id,
        ]);
    }

    public function test_user_can_delete_their_expense(): void
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Deleted');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_user_cannot_view_update_or_delete_another_users_expense(): void
    {
        $user = User::factory()->create();
        $otherExpense = Expense::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/expenses/{$otherExpense->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/expenses/{$otherExpense->id}", [
                'date' => '2026-07-20',
                'amount' => 3200,
                'memo' => '変更不可',
                'category_id' => $category->id,
            ])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/expenses/{$otherExpense->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('expenses', ['id' => $otherExpense->id]);
    }
}
