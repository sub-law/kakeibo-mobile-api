<?php

namespace App\Models;

use Database\Factories\FixedExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedExpense extends Model
{
    /** @use HasFactory<FixedExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'memo',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function processes()
    {
        return $this->hasMany(FixedExpenseProcess::class);
    }
}
