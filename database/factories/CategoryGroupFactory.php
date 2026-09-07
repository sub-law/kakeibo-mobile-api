<?php

namespace Database\Factories;

use App\Models\CategoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryGroup>
 */
class CategoryGroupFactory extends Factory
{
    protected $model = CategoryGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
