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

    public function test_unauthenticated_user_cannot_preview_or_process_fixed_expenses(): void
    {
        $this->getJson('/api/fixed-expenses/process-preview?target_month=2026-08')
            ->assertUnauthorized();

        $this->postJson('/api/fixed-expenses/process', [
            'target_month' => '2026-08',
        ])->assertUnauthorized();
    }

    public function test_preview_contains_only_current_users_enabled_unprocessed_fixed_expenses(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $target = FixedExpense::factory()
            ->for($user)
            ->for($category)
            ->create(['amount' => 5000]);
        FixedExpense::factory()->disabled()->for($user)->create();
        FixedExpense::factory()->for($otherUser)->create();
        $processed = FixedExpense::factory()->for($user)->create();
        FixedExpenseProcess::create([
            'user_id' => $user->id,
            'fixed_expense_id' => $processed->id,
            'expense_id' => null,
            'target_month' => '2026-08-01',
            'category_id' => $processed->category_id,
            'amount' => $processed->amount,
            'memo' => $processed->memo,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fixed-expenses/process-preview?target_month=2026-08')
            ->assertOk()
            ->assertJsonPath('target_month', '2026-08')
            ->assertJsonPath('expense_date', '2026-08-01')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total_amount', 5000)
            ->assertJsonPath('fixed_expenses.0.id', $target->id);
    }

    public function test_processing_creates_expenses_on_the_first_day_and_process_histories(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 12000,
            'memo' => '生命保険料',
        ]);
        FixedExpense::factory()->for($user)->for($category)->create([
            'amount' => 1800,
            'memo' => '継続課金',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated()
            ->assertJsonPath('expense_date', '2026-08-01')
            ->assertJsonPath('processed_count', 2)
            ->assertJsonPath('total_amount', 13800)
            ->assertJsonCount(2, 'expenses');

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'date' => '2026-08-01',
            'amount' => 12000,
            'memo' => '生命保険料',
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseCount('fixed_expense_processes', 2);
    }

    public function test_later_fixed_expense_changes_do_not_change_processed_snapshots(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $user = User::factory()->create();
        $fixedExpense = FixedExpense::factory()->for($user)->create([
            'amount' => 3000,
            'memo' => '処理時の用途',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated();

        $fixedExpense->update([
            'amount' => 4500,
            'memo' => '変更後の用途',
        ]);

        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'amount' => 3000,
            'memo' => '処理時の用途',
        ]);
        $this->assertDatabaseHas('fixed_expense_processes', [
            'fixed_expense_id' => $fixedExpense->id,
            'amount' => 3000,
            'memo' => '処理時の用途',
        ]);
    }

    public function test_same_fixed_expense_is_not_processed_twice_in_the_same_month(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $user = User::factory()->create();
        FixedExpense::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated()
            ->assertJsonPath('processed_count', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated()
            ->assertJsonPath('processed_count', 0);

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('fixed_expense_processes', 1);
    }

    public function test_processed_expense_can_be_updated_and_deleted_without_allowing_reprocessing(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create();
        FixedExpense::factory()->for($user)->for($category)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated();

        $expense = Expense::where('user_id', $user->id)->sole();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/expenses/{$expense->id}", [
                'date' => '2026-08-10',
                'amount' => 9999,
                'memo' => '修正後の用途',
                'category_id' => $category->id,
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/expenses/{$expense->id}")
            ->assertOk();

        $this->assertDatabaseHas('fixed_expense_processes', [
            'user_id' => $user->id,
            'expense_id' => null,
            'target_month' => '2026-08-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fixed-expenses/process', [
                'target_month' => '2026-08',
            ])
            ->assertCreated()
            ->assertJsonPath('processed_count', 0);

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('fixed_expense_processes', 1);
    }
}
