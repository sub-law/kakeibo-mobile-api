<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedExpense>
 */
class FixedExpenseFactory extends Factory
{
    protected $model = FixedExpense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'amount' => fake()->numberBetween(1, 1_000_000),
            'memo' => fake()->sentence(),
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
        ]);
    }
}
