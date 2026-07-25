<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AssetBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_asset_balance_endpoints(): void
    {
        $this->getJson('/api/accounts')->assertUnauthorized();
        $this->getJson('/api/asset-balances')->assertUnauthorized();
        $this->postJson('/api/asset-balances/bulk')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_accounts(): void
    {
        $user = User::factory()->create();
        $accounts = Account::factory()->count(2)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/accounts')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'id' => $accounts[0]->id,
                'name' => $accounts[0]->name,
                'type' => $accounts[0]->type,
            ]);
    }

    public function test_account_list_is_empty_when_no_accounts_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/accounts')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_user_can_register_multiple_monthly_asset_balances(): void
    {
        $user = User::factory()->create();
        $accounts = Account::factory()->count(2)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/asset-balances/bulk', [
                'date' => '2026-07-01',
                'balances' => [
                    ['account_id' => $accounts[0]->id, 'amount' => 100000],
                    ['account_id' => $accounts[1]->id, 'amount' => 250000],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', '月次残高を登録しました（上書き含む）')
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('asset_balances', [
            'user_id' => $user->id,
            'account_id' => $accounts[0]->id,
            'amount' => 100000,
            'date' => '2026-07-01',
        ]);
        $this->assertDatabaseHas('asset_balances', [
            'user_id' => $user->id,
            'account_id' => $accounts[1]->id,
            'amount' => 250000,
            'date' => '2026-07-01',
        ]);
    }

    public function test_bulk_registration_updates_only_the_authenticated_users_existing_balance(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->create();

        AssetBalance::factory()->for($user)->for($account)->create([
            'date' => '2026-07-01',
            'amount' => 100000,
        ]);
        $otherBalance = AssetBalance::factory()->for($otherUser)->for($account)->create([
            'date' => '2026-07-01',
            'amount' => 900000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/asset-balances/bulk', [
                'date' => '2026-07-01',
                'balances' => [
                    ['account_id' => $account->id, 'amount' => 150000],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('asset_balances', 2);
        $this->assertDatabaseHas('asset_balances', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 150000,
            'date' => '2026-07-01',
        ]);
        $this->assertDatabaseHas('asset_balances', [
            'id' => $otherBalance->id,
            'amount' => 900000,
        ]);
    }

    public function test_bulk_registration_treats_zero_null_and_missing_amount_as_zero(): void
    {
        $user = User::factory()->create();
        $accounts = Account::factory()->count(3)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/asset-balances/bulk', [
                'date' => '2026-07-01',
                'balances' => [
                    ['account_id' => $accounts[0]->id, 'amount' => 0],
                    ['account_id' => $accounts[1]->id, 'amount' => null],
                    ['account_id' => $accounts[2]->id],
                ],
            ])
            ->assertOk();

        foreach ($accounts as $account) {
            $this->assertDatabaseHas('asset_balances', [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'amount' => 0,
                'date' => '2026-07-01',
            ]);
        }
    }

    public function test_bulk_registration_returns_validation_errors_for_missing_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/asset-balances/bulk', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'balances'])
            ->assertJsonPath('errors.date.0', '日付は必須です。')
            ->assertJsonPath('errors.balances.0', '残高データがありません。');
    }

    public function test_bulk_registration_rejects_invalid_balance_values(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/asset-balances/bulk', [
                'date' => 'invalid-date',
                'balances' => [
                    ['amount' => 1000],
                    ['account_id' => 999999, 'amount' => 'invalid'],
                    ['account_id' => 999998, 'amount' => -1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'balances.0.account_id',
                'balances.1.account_id',
                'balances.1.amount',
                'balances.2.account_id',
                'balances.2.amount',
            ]);

        $errors = $response->json('errors');

        $this->assertSame('口座IDは必須です。', $errors['balances.0.account_id'][0]);
        $this->assertSame('指定された口座が存在しません。', $errors['balances.1.account_id'][0]);
        $this->assertSame('金額は整数で入力してください。', $errors['balances.1.amount'][0]);
        $this->assertSame('金額は0以上で入力してください。', $errors['balances.2.amount'][0]);
    }

    public function test_user_can_list_only_their_balances_for_the_requested_month_in_account_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstAccount = Account::factory()->create();
        $secondAccount = Account::factory()->create();

        $secondBalance = AssetBalance::factory()->for($user)->for($secondAccount)->create([
            'date' => '2026-07-01',
        ]);
        $firstBalance = AssetBalance::factory()->for($user)->for($firstAccount)->create([
            'date' => '2026-07-01',
        ]);
        AssetBalance::factory()->for($user)->for($firstAccount)->create([
            'date' => '2026-06-01',
        ]);
        AssetBalance::factory()->for($otherUser)->for($firstAccount)->create([
            'date' => '2026-07-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/asset-balances?year=2026&month=7')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $firstBalance->id)
            ->assertJsonPath('data.1.id', $secondBalance->id)
            ->assertJsonPath('data.0.account.id', $firstAccount->id)
            ->assertJsonPath('data.0.account.name', $firstAccount->name)
            ->assertJsonPath('data.0.account.type', $firstAccount->type);
    }

    public function test_asset_balance_list_is_empty_when_the_month_has_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/asset-balances?year=2026&month=7')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_asset_balance_list_rejects_months_outside_one_to_twelve(): void
    {
        $user = User::factory()->create();

        foreach ([0, 13] as $month) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/asset-balances?year=2026&month={$month}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['month'])
                ->assertJsonPath('errors.month.0', '月は1〜12の範囲で入力してください。');
        }
    }
}
