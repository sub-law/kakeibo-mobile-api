<?php

namespace Tests\Feature;

use App\Models\BudgetAlertRead;
use App\Models\BudgetAlertSetting;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAlertSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_budget_alert_setting_endpoints(): void
    {
        $requests = [
            ['getJson', '/api/budget-alert-settings'],
            ['postJson', '/api/budget-alert-settings'],
            ['getJson', '/api/budget-alert-settings/1'],
            ['putJson', '/api/budget-alert-settings/1'],
            ['deleteJson', '/api/budget-alert-settings/1'],
        ];

        foreach ($requests as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_user_can_create_and_list_multiple_category_settings(): void
    {
        $user = User::factory()->create();
        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/budget-alert-settings', [
                'category_id' => $secondCategory->id,
                'monthly_budget' => 50000,
                'warning_threshold_percent' => 80,
                'is_enabled' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('category.id', $secondCategory->id)
            ->assertJsonPath('monthly_budget', 50000)
            ->assertJsonPath('warning_threshold_percent', 80)
            ->assertJsonPath('is_enabled', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/budget-alert-settings', [
                'category_id' => $firstCategory->id,
                'monthly_budget' => 120000,
                'warning_threshold_percent' => 70,
                'is_enabled' => true,
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-settings')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.category.id', $firstCategory->id)
            ->assertJsonPath('0.category.group.id', $firstCategory->category_group_id)
            ->assertJsonPath('1.category.id', $secondCategory->id);
    }

    public function test_user_can_view_update_and_delete_their_setting(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 120000,
            'is_enabled' => true,
        ]);
        BudgetAlertRead::create([
            'budget_alert_setting_id' => $setting->id,
            'year' => 2026,
            'month' => 7,
            'level' => 'warning',
            'read_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/budget-alert-settings/{$setting->id}")
            ->assertOk()
            ->assertJsonPath('id', $setting->id)
            ->assertJsonPath('category.id', $category->id);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/budget-alert-settings/{$setting->id}", [
                'category_id' => $newCategory->id,
                'monthly_budget' => 90000,
                'warning_threshold_percent' => 75,
                'is_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('category.id', $newCategory->id)
            ->assertJsonPath('monthly_budget', 90000)
            ->assertJsonPath('warning_threshold_percent', 75)
            ->assertJsonPath('is_enabled', false);

        $this->assertDatabaseMissing('budget_alert_reads', [
            'budget_alert_setting_id' => $setting->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/budget-alert-settings/{$setting->id}")
            ->assertOk()
            ->assertJsonPath('message', 'アラート設定を削除しました。');

        $this->assertDatabaseMissing('budget_alert_settings', [
            'id' => $setting->id,
        ]);
    }

    public function test_user_cannot_register_the_same_category_more_than_once(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 120000,
            'is_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/budget-alert-settings', [
                'category_id' => $category->id,
                'monthly_budget' => 150000,
                'warning_threshold_percent' => 70,
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id')
            ->assertJsonPath(
                'errors.category_id.0',
                '同じカテゴリのアラート設定はすでに登録されています。'
            );

        $this->assertDatabaseCount('budget_alert_settings', 1);
    }

    public function test_user_cannot_update_a_setting_to_an_already_configured_category(): void
    {
        $user = User::factory()->create();
        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();
        $firstSetting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $firstCategory->id,
            'monthly_budget' => 120000,
            'is_enabled' => true,
        ]);
        BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $secondCategory->id,
            'monthly_budget' => 50000,
            'is_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/budget-alert-settings/{$firstSetting->id}", [
                'category_id' => $secondCategory->id,
                'monthly_budget' => 150000,
                'warning_threshold_percent' => 70,
                'is_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id')
            ->assertJsonPath(
                'errors.category_id.0',
                '同じカテゴリのアラート設定はすでに登録されています。'
            );

        $this->assertDatabaseHas('budget_alert_settings', [
            'id' => $firstSetting->id,
            'category_id' => $firstCategory->id,
            'monthly_budget' => 120000,
        ]);
    }

    public function test_user_can_update_a_setting_without_changing_its_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $setting = BudgetAlertSetting::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'monthly_budget' => 120000,
            'is_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/budget-alert-settings/{$setting->id}", [
                'category_id' => $category->id,
                'monthly_budget' => 150000,
                'warning_threshold_percent' => 80,
                'is_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('monthly_budget', 150000)
            ->assertJsonPath('warning_threshold_percent', 80);
    }

    public function test_budget_alert_setting_returns_japanese_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/budget-alert-settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
                'monthly_budget',
                'warning_threshold_percent',
                'is_enabled',
            ])
            ->assertJsonPath('errors.category_id.0', 'カテゴリは必須です。')
            ->assertJsonPath('errors.monthly_budget.0', '月間予算は必須です。')
            ->assertJsonPath('errors.warning_threshold_percent.0', '警告割合は必須です。')
            ->assertJsonPath('errors.is_enabled.0', 'アラートの有効・無効を指定してください。');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/budget-alert-settings', [
                'category_id' => 999999,
                'monthly_budget' => 0,
                'warning_threshold_percent' => 100,
                'is_enabled' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', '選択したカテゴリが存在しません。')
            ->assertJsonPath('errors.monthly_budget.0', '月間予算は1円以上で入力してください。')
            ->assertJsonPath(
                'errors.warning_threshold_percent.0',
                '警告割合は1%から99%の間で入力してください。'
            )
            ->assertJsonPath('errors.is_enabled.0', 'アラートの有効・無効の形式が正しくありません。');
    }

    public function test_user_cannot_access_another_users_settings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create();
        $otherSetting = BudgetAlertSetting::create([
            'user_id' => $otherUser->id,
            'category_id' => $category->id,
            'monthly_budget' => 120000,
            'is_enabled' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-settings')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/budget-alert-settings/{$otherSetting->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/budget-alert-settings/{$otherSetting->id}", [
                'category_id' => $category->id,
                'monthly_budget' => 90000,
                'warning_threshold_percent' => 70,
                'is_enabled' => false,
            ])
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/budget-alert-settings/{$otherSetting->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('budget_alert_settings', [
            'id' => $otherSetting->id,
            'monthly_budget' => 120000,
        ]);
    }
}
