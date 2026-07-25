<?php

namespace App\Models;

use Database\Factories\CategoryGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryGroup extends Model
{
    /** @use HasFactory<CategoryGroupFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
