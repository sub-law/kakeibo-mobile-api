<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'amount',
        'memo',
        'category_id',
    ];

    // ① 支出 → ユーザー（必須）
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ② 支出 → 小分類カテゴリ（必須）
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}
