<?php

namespace App\Models;

use Database\Factories\AssetBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Account;
class AssetBalance extends Model
{
    /** @use HasFactory<AssetBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'amount',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
