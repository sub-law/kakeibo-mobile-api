<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'date' => fake()->date(),
            'amount' => fake()->numberBetween(1, 1_000_000),
            'memo' => fake()->optional()->sentence(),
        ];
    }
}
