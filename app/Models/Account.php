<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AssetBalance;
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = ['name', 'type'];

    public function assetBalances()
    {
        return $this->hasMany(AssetBalance::class);
    }
}
