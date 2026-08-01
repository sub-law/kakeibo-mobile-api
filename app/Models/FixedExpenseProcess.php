<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedExpenseProcess extends Model
{
    protected $fillable = [
        'user_id',
        'fixed_expense_id',
        'expense_id',
        'target_month',
        'category_id',
        'amount',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'target_month' => 'date:Y-m-d',
            'amount' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fixedExpense()
    {
        return $this->belongsTo(FixedExpense::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
