<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class AccountController extends Controller
{
    // 口座一覧（セレクトボックス用）
    public function index()
    {
        return response()->json(Account::all(), 200);
    }
}
