<?php

namespace Database\Factories;

use App\Models\AssetBalance;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetBalanceFactory extends Factory
{
    protected $model = AssetBalance::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'amount' => $this->faker->numberBetween(0, 1000000),
            'date' => $this->faker->dateTimeThisYear()->format('Y-m-01'),
        ];
    }
}
