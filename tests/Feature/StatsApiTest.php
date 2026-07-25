<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AssetBalance;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_monthly_summary(): void
    {
        $this->getJson('/api/stats/2026/monthly-summary')
            ->assertUnauthorized();
    }

    public function test_user_can_get_their_monthly_and_annual_summary(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstAccount = Account::factory()->create();
        $secondAccount = Account::factory()->create();

        Income::factory()->for($user)->create(['date' => '2026-01-10', 'amount' => 100000]);
        Income::factory()->for($user)->create(['date' => '2026-01-20', 'amount' => 50000]);
        Income::factory()->for($user)->create(['date' => '2026-03-10', 'amount' => 300000]);
        Income::factory()->for($user)->create(['date' => '2025-01-10', 'amount' => 900000]);
        Income::factory()->for($otherUser)->create(['date' => '2026-01-10', 'amount' => 800000]);

        Expense::factory()->for($user)->create(['date' => '2026-01-05', 'amount' => 40000]);
        Expense::factory()->for($user)->create(['date' => '2026-02-05', 'amount' => 60000]);
        Expense::factory()->for($user)->create(['date' => '2025-01-05', 'amount' => 700000]);
        Expense::factory()->for($otherUser)->create(['date' => '2026-01-05', 'amount' => 600000]);

        AssetBalance::factory()->for($user)->for($firstAccount)->create([
            'date' => '2026-01-01',
            'amount' => 1000000,
        ]);
        AssetBalance::factory()->for($user)->for($secondAccount)->create([
            'date' => '2026-01-01',
            'amount' => 500000,
        ]);
        AssetBalance::factory()->for($user)->for($firstAccount)->create([
            'date' => '2026-06-01',
            'amount' => 2000000,
        ]);
        AssetBalance::factory()->for($user)->for($secondAccount)->create([
            'date' => '2026-06-01',
            'amount' => 800000,
        ]);
        AssetBalance::factory()->for($otherUser)->for($firstAccount)->create([
            'date' => '2026-06-01',
            'amount' => 9000000,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/stats/2026/monthly-summary')
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('accounts.0', [
                'id' => $firstAccount->id,
                'name' => $firstAccount->name,
                'type' => $firstAccount->type,
            ])
            ->assertJsonPath('accounts.1', [
                'id' => $secondAccount->id,
                'name' => $secondAccount->name,
                'type' => $secondAccount->type,
            ])
            ->assertJsonCount(12, 'monthly')
            ->assertJsonPath('monthly.0', [
                'month' => 1,
                'income' => 150000,
                'expense' => 40000,
                'assets' => 1500000,
                'assets_by_account' => [
                    $firstAccount->id => 1000000,
                    $secondAccount->id => 500000,
                ],
            ])
            ->assertJsonPath('monthly.1', [
                'month' => 2,
                'income' => 0,
                'expense' => 60000,
                'assets' => 0,
                'assets_by_account' => [
                    $firstAccount->id => 0,
                    $secondAccount->id => 0,
                ],
            ])
            ->assertJsonPath('monthly.2', [
                'month' => 3,
                'income' => 300000,
                'expense' => 0,
                'assets' => 0,
                'assets_by_account' => [
                    $firstAccount->id => 0,
                    $secondAccount->id => 0,
                ],
            ])
            ->assertJsonPath('monthly.5', [
                'month' => 6,
                'income' => 0,
                'expense' => 0,
                'assets' => 2800000,
                'assets_by_account' => [
                    $firstAccount->id => 2000000,
                    $secondAccount->id => 800000,
                ],
            ])
            ->assertJsonPath('totals.income', 450000)
            ->assertJsonPath('totals.expense', 100000)
            ->assertJsonPath('totals.balance', 350000)
            ->assertJsonPath('totals.latest_assets', 2800000)
            ->assertJsonPath('totals.asset_change', 1300000);
    }

    public function test_monthly_summary_returns_zeroes_when_the_year_has_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/stats/2026/monthly-summary')
            ->assertOk()
            ->assertJsonPath('accounts', [])
            ->assertJsonCount(12, 'monthly')
            ->assertJsonPath('totals', [
                'income' => 0,
                'expense' => 0,
                'balance' => 0,
                'latest_assets' => 0,
                'asset_change' => 0,
            ]);

        foreach ($response->json('monthly') as $index => $month) {
            $this->assertSame($index + 1, $month['month']);
            $this->assertSame(0, $month['income']);
            $this->assertSame(0, $month['expense']);
            $this->assertSame(0, $month['assets']);
            $this->assertSame([], $month['assets_by_account']);
        }
    }

    public function test_monthly_summary_rejects_invalid_years_with_japanese_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/stats/invalid/monthly-summary')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year'])
            ->assertJsonPath('errors.year.0', '年は数値で指定してください。');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/stats/1899/monthly-summary')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year'])
            ->assertJsonPath('errors.year.0', '年は1900年以上で指定してください。');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/stats/2101/monthly-summary')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year'])
            ->assertJsonPath('errors.year.0', '年は2100以下で指定してください。');
    }
}
