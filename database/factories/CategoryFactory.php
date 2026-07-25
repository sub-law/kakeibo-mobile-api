<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'category_group_id' => CategoryGroup::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
