<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AssetBalance;
use App\Models\User;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assetBalances()
    {
        return $this->hasMany(AssetBalance::class);
    }
}
