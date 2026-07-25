<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoryGroup;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryGroup::with('categories')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'categories' => $group->categories->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'name' => $c->name,
                        ];
                    }),
                ];
            });
    }
}
