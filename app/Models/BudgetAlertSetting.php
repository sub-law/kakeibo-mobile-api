<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetAlertSetting extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'monthly_budget',
        'warning_threshold_percent',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'monthly_budget' => 'integer',
            'warning_threshold_percent' => 'integer',
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

    public function reads()
    {
        return $this->hasMany(BudgetAlertRead::class);
    }
}
