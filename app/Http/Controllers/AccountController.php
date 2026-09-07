<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountController extends Controller
{
    // 口座一覧（セレクトボックス用）
    public function index(Request $request)
    {
        $accounts = $request->user()
            ->accounts()
            ->orderBy('id')
            ->get();

        return response()->json($accounts, 200);
    }
}
