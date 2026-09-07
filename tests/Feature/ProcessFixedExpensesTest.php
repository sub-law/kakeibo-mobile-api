<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\FixedExpenseProcess;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessFixedExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_preview_lists_only_unprocessed_enabled_fixed_expenses(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $processed = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);
        $unprocessed = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 980,
            'memo' => '動画配信サービス',
        ]);
        FixedExpense::factory()->for($user)->for($category)->disabled()->create([
            'amount' => 500,
            'memo' => '無効な固定費',
        ]);
        $expense = Expense::factory()->for($user)->for($category)->create();
        FixedExpenseProcess::create([
            'fixed_expense_id' => $processed->id,
            'expense_id' => $expense->id,
            'target_month' => '2026-08-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fixed-expenses/process-preview?target_month=2026-08')
            ->assertOk()
            ->assertJsonPath('target_month', '2026-08')
            ->assertJsonPath('expense_date', '2026-08-01')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total_amount', 980)
            ->assertJsonPath('fixed_expenses.0.id', $unprocessed->id)
            ->assertJsonPath('fixed_expenses.0.category.id', $category->id);
    }

    public function test_approval_creates_month_start_expenses_with_current_values(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $first = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);
        $second = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 980,
            'memo' => '動画配信サービス',
        ]);
        FixedExpense::factory()->for($user)->for($category)->disabled()->create([
            'amount' => 500,
            'memo' => '無効な固定費',
        ]);
        FixedExpense::factory()->for($otherUser)->for($category)->create([
            'amount' => 3000,
            'memo' => '他ユーザーの固定費',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertOk()
            ->assertJsonPath('message', '固定費の出金処理が完了しました。')
            ->assertJsonPath('expense_date', '2026-08-01')
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('total_amount', 12980);

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'date' => '2026-08-01',
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'date' => '2026-08-01',
            'amount' => 980,
            'memo' => '動画配信サービス',
        ]);
        $this->assertDatabaseHas('fixed_expense_processes', [
            'fixed_expense_id' => $first->id,
            'target_month' => '2026-08-01',
        ]);
        $this->assertDatabaseHas('fixed_expense_processes', [
            'fixed_expense_id' => $second->id,
            'target_month' => '2026-08-01',
        ]);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_reprocessing_creates_only_fixed_expenses_added_after_first_process(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertJsonPath('created_count', 1);

        FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 980,
            'memo' => '動画配信サービス',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1)
            ->assertJsonPath('total_amount', 980);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertOk()
            ->assertJsonPath('message', '未処理の固定費はありません。')
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('skipped_count', 2);

        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseCount('fixed_expense_processes', 2);
    }

    public function test_changing_fixed_expense_does_not_change_past_expense(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $fixedExpense = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertOk();

        $fixedExpense->update([
            'category_id' => $newCategory->id,
            'amount' => 15000,
            'memo' => '生命保険料（変更後）',
        ]);

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);
        $this->assertDatabaseMissing('expenses', [
            'user_id' => $user->id,
            'amount' => 15000,
        ]);
    }

    public function test_generated_expense_can_be_updated_and_deleted_without_removing_process_history(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $fixedExpense = FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertOk();

        $process = FixedExpenseProcess::where('fixed_expense_id', $fixedExpense->id)->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/expenses/{$process->expense_id}", [
                'date' => '2026-08-02',
                'amount' => 13000,
                'memo' => '手動修正',
                'category_id' => $newCategory->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('expenses', [
            'id' => $process->expense_id,
            'date' => '2026-08-02',
            'amount' => 13000,
            'memo' => '手動修正',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/expenses/{$process->expense_id}")
            ->assertOk();

        $this->assertDatabaseMissing('expenses', ['id' => $process->expense_id]);
        $this->assertDatabaseHas('fixed_expense_processes', [
            'id' => $process->id,
            'fixed_expense_id' => $fixedExpense->id,
            'expense_id' => null,
            'target_month' => '2026-08-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-08'])
            ->assertJsonPath('created_count', 0);
    }

    public function test_processing_rejects_a_month_other_than_current_month(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', ['target_month' => '2026-07'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.target_month.0', '出金処理できるのは今月分のみです。');
    }

}
