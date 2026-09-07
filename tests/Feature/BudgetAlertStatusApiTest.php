<?php

namespace Tests\Feature;

use App\Models\BudgetAlertSetting;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAlertStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_status_or_mark_an_alert_as_read(): void
    {
        $this->getJson('/api/budget-alert-status')->assertUnauthorized();
        $this->postJson('/api/budget-alert-settings/1/read')->assertUnauthorized();
    }

    public function test_status_returns_an_empty_alert_list_when_there_are_no_alerts(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 69999,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonPath('alerts', []);
    }

    public function test_status_uses_the_configured_warning_threshold(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 200000,
            'warning_threshold_percent' => 85,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 170000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.level', 'warning')
            ->assertJsonPath('alerts.0.warning_threshold_percent', 85)
            ->assertJsonPath('alerts.0.usage_rate', 85)
            ->assertJsonPath(
                'alerts.0.message',
                "{$category->name}の出金が設定金額の85%に達しました。"
            );
    }

    public function test_status_returns_warning_and_danger_alerts_for_each_category(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $warningCategory = Category::factory()->create();
        $dangerCategory = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $warningSetting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $warningCategory->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        $dangerSetting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $dangerCategory->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $otherCategory->id,
            'monthly_budget' => 1,
            'warning_threshold_percent' => 70,
            'is_enabled' => false,
        ]);

        Expense::factory()->for($user)->for($warningCategory)->create([
            'date' => '2026-07-05',
            'amount' => 70000,
        ]);
        Expense::factory()->for($user)->for($warningCategory)->create([
            'date' => '2026-07-20',
            'amount' => 500000,
        ]);
        Expense::factory()->for($user)->for($dangerCategory)->create([
            'date' => '2026-07-05',
            'amount' => 120000,
        ]);
        Expense::factory()->for($otherUser)->for($warningCategory)->create([
            'date' => '2026-07-05',
            'amount' => 900000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonCount(2, 'alerts')
            ->assertJsonPath('alerts.0.setting_id', $warningSetting->id)
            ->assertJsonPath('alerts.0.category.id', $warningCategory->id)
            ->assertJsonPath('alerts.0.category.group.id', $warningCategory->category_group_id)
            ->assertJsonPath('alerts.0.level', 'warning')
            ->assertJsonPath('alerts.0.monthly_budget', 100000)
            ->assertJsonPath('alerts.0.warning_threshold_percent', 70)
            ->assertJsonPath('alerts.0.spent_amount', 70000)
            ->assertJsonPath('alerts.0.usage_rate', 70)
            ->assertJsonPath(
                'alerts.0.message',
                "{$warningCategory->name}の出金が設定金額の70%に達しました。"
            )
            ->assertJsonPath('alerts.1.setting_id', $dangerSetting->id)
            ->assertJsonPath('alerts.1.category.id', $dangerCategory->id)
            ->assertJsonPath('alerts.1.level', 'danger')
            ->assertJsonPath('alerts.1.spent_amount', 120000);
    }

    public function test_read_warning_is_hidden_but_danger_is_shown_again(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 70000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/budget-alert-settings/{$setting->id}/read")
            ->assertOk()
            ->assertJsonPath('message', 'アラートを既読にしました。');

        $this->assertDatabaseHas('budget_alert_reads', [
            'budget_alert_setting_id' => $setting->id,
            'year' => 2026,
            'month' => 7,
            'level' => 'warning',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonPath('alerts', []);

        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-09',
            'amount' => 30000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.level', 'danger')
            ->assertJsonPath(
                'alerts.0.message',
                "{$category->name}の出金が設定金額に達しました。"
            );
    }

    public function test_read_state_does_not_hide_an_alert_in_the_next_month(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 70000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/budget-alert-settings/{$setting->id}/read")
            ->assertOk();

        Carbon::setTestNow('2026-08-10 12:00:00');
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-08-05',
            'amount' => 70000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.level', 'warning');
    }

    public function test_user_cannot_mark_another_users_alert_as_read(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 70,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($otherUser)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 70000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/budget-alert-settings/{$setting->id}/read")
            ->assertNotFound();

        $this->assertDatabaseMissing('budget_alert_reads', [
            'budget_alert_setting_id' => $setting->id,
        ]);
    }

    public function test_user_cannot_mark_a_nonexistent_alert_as_read(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 100000,
            'warning_threshold_percent' => 80,
            'is_enabled' => true,
        ]);
        Expense::factory()->for($user)->for($category)->create([
            'date' => '2026-07-05',
            'amount' => 79999,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/budget-alert-settings/{$setting->id}/read")
            ->assertConflict()
            ->assertJsonPath('message', '既読にできるアラートがありません。');
    }
}
