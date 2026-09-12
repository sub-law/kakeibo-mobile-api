<?php

namespace Tests\Feature;

use App\Models\AssetBalance;
use App\Models\BudgetAlertRead;
use App\Models\BudgetAlertSetting;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\FixedExpenseProcess;
use App\Models\Income;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CategoryGroupSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_demo_data_is_clear_current_and_safe_to_seed_again(): void
    {
        Carbon::setTestNow('2026-09-11 12:00:00');

        $user = User::factory()->create();
        $this->seed([
            CategoryGroupSeeder::class,
            CategorySeeder::class,
            AccountSeeder::class,
        ]);

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(3, Income::where('user_id', $user->id)->count());
        $this->assertSame(9, Expense::where('user_id', $user->id)->count());
        $this->assertSame(9, AssetBalance::where('user_id', $user->id)->count());
        $this->assertSame(2, FixedExpense::where('user_id', $user->id)->count());
        $this->assertSame(2, BudgetAlertSetting::where('user_id', $user->id)->count());

        $this->assertDatabaseHas('incomes', [
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'amount' => 300000,
            'memo' => '給与',
        ]);
        $this->assertSame(
            68000,
            (int) Expense::where('user_id', $user->id)
                ->whereDate('date', '2026-09-01')
                ->sum('amount')
        );
        $this->assertSame(
            555000,
            (int) AssetBalance::where('user_id', $user->id)
                ->whereDate('date', '2026-09-01')
                ->sum('amount')
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/budget-alert-status')
            ->assertOk()
            ->assertJsonCount(2, 'alerts');
        $levelsByCategory = collect($response->json('alerts'))
            ->mapWithKeys(fn (array $alert) => [
                $alert['category']['name'] => $alert['level'],
            ]);

        $this->assertSame('warning', $levelsByCategory['食料品']);
        $this->assertSame('danger', $levelsByCategory['娯楽・レジャー']);
        $this->assertSame(0, FixedExpenseProcess::count());
        $this->assertSame(0, BudgetAlertRead::count());
    }
}
