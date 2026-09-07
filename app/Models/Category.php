<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Expense;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['category_group_id', 'name'];

    public function group()
    {
        return $this->belongsTo(CategoryGroup::class, 'category_group_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
