<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_income_endpoints(): void
    {
        $requests = [
            ['getJson', '/api/incomes'],
            ['postJson', '/api/incomes'],
            ['getJson', '/api/incomes/1'],
            ['putJson', '/api/incomes/1'],
            ['deleteJson', '/api/incomes/1'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_authenticated_user_can_create_an_income(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/incomes', [
            'date' => '2026-07-19',
            'amount' => 5000,
            'memo' => '給与以外の入金',
        ]);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'date' => '2026-07-19',
                'amount' => 5000,
                'memo' => '給与以外の入金',
                'user_id' => $user->id,
            ]);

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'date' => '2026-07-19',
            'amount' => 5000,
            'memo' => '給与以外の入金',
        ]);
    }

    public function test_income_creation_returns_japanese_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/incomes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'amount'])
            ->assertJsonPath('errors.date.0', '日付を入力してください。')
            ->assertJsonPath('errors.amount.0', '金額を入力してください。');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/incomes', [
                'date' => 'invalid-date',
                'amount' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'amount'])
            ->assertJsonPath('errors.date.0', '日付の形式が正しくありません。')
            ->assertJsonPath('errors.amount.0', '金額は1円以上で入力してください。');
    }

    public function test_income_accepts_database_boundary_values_on_create_and_update(): void
    {
        $user = User::factory()->create();
        $createMemo = str_repeat('あ', 255);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/incomes', [
                'date' => '2026-07-19',
                'amount' => 2147483647,
                'memo' => $createMemo,
            ])
            ->assertCreated()
            ->assertJsonPath('amount', 2147483647)
            ->assertJsonPath('memo', $createMemo);

        $incomeId = $response->json('id');
        $updateMemo = str_repeat('い', 255);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/incomes/{$incomeId}", [
                'date' => '2026-07-20',
                'amount' => 2147483647,
                'memo' => $updateMemo,
            ])
            ->assertOk()
            ->assertJsonPath('amount', 2147483647)
            ->assertJsonPath('memo', $updateMemo);

        $this->assertDatabaseHas('incomes', [
            'id' => $incomeId,
            'amount' => 2147483647,
            'memo' => $updateMemo,
        ]);
    }

    public function test_income_rejects_values_beyond_database_boundaries_on_create_and_update(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create([
            'amount' => 1000,
            'memo' => '変更前',
        ]);
        $invalidPayload = [
            'date' => '2026-07-20',
            'amount' => 2147483648,
            'memo' => str_repeat('あ', 256),
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/incomes', $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'memo'])
            ->assertJsonPath('errors.amount.0', '金額は2,147,483,647円以下で入力してください。')
            ->assertJsonPath('errors.memo.0', 'メモは255文字以内で入力してください。');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/incomes/{$income->id}", $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'memo']);

        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'amount' => 1000,
            'memo' => '変更前',
        ]);
    }

    public function test_user_can_list_only_their_incomes_for_the_requested_month_in_date_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $laterIncome = Income::factory()->for($user)->create(['date' => '2026-07-20']);
        $earlierIncome = Income::factory()->for($user)->create(['date' => '2026-07-01']);
        Income::factory()->for($user)->create(['date' => '2026-06-30']);
        Income::factory()->for($otherUser)->create(['date' => '2026-07-10']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/incomes?year=2026&month=7')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $earlierIncome->id)
            ->assertJsonPath('1.id', $laterIncome->id);
    }

    public function test_income_list_rejects_invalid_year_and_month_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/incomes?year=invalid&month=13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'month'])
            ->assertJsonPath('errors.year.0', '年は数値で入力してください。')
            ->assertJsonPath('errors.month.0', '月は1〜12の範囲で入力してください。');
    }

    public function test_user_can_view_their_income(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/incomes/{$income->id}")
            ->assertOk()
            ->assertJsonPath('id', $income->id)
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_user_can_update_their_income(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/incomes/{$income->id}", [
                'date' => '2026-07-20',
                'amount' => 12000,
                'memo' => '更新後の備考',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'date' => '2026-07-20',
                'amount' => 12000,
                'memo' => '更新後の備考',
            ]);

        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'user_id' => $user->id,
            'date' => '2026-07-20',
            'amount' => 12000,
            'memo' => '更新後の備考',
        ]);
    }

    public function test_user_can_delete_their_income(): void
    {
        $user = User::factory()->create();
        $income = Income::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/incomes/{$income->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Deleted');

        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    }

    public function test_user_cannot_view_update_or_delete_another_users_income(): void
    {
        $user = User::factory()->create();
        $otherIncome = Income::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/incomes/{$otherIncome->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/incomes/{$otherIncome->id}", [
                'date' => '2026-07-20',
                'amount' => 12000,
                'memo' => '変更不可',
            ])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/incomes/{$otherIncome->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('incomes', ['id' => $otherIncome->id]);
    }
}
