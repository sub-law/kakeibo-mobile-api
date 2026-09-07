<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetAlertRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'budget_alert_setting_id',
        'year',
        'month',
        'level',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function setting()
    {
        return $this->belongsTo(BudgetAlertSetting::class, 'budget_alert_setting_id');
    }
}
