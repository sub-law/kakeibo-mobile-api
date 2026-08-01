<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedExpenseProcess extends Model
{
    protected $fillable = [
        'fixed_expense_id',
        'expense_id',
        'target_month',
    ];

    public function fixedExpense()
    {
        return $this->belongsTo(FixedExpense::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
