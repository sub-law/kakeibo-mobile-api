<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(1, 1_000_000),
            'date' => fake()->date(),
            'memo' => fake()->optional()->sentence(),
        ];
    }
}
