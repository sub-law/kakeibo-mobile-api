<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'logged_in_at',
        'ip_address',
        'user_agent',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
