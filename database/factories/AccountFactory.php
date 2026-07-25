<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => fake()->randomElement(['bank', 'securities', 'cash']),
        ];
    }
}
