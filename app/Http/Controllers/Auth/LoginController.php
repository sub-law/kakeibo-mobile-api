<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request)
    {
        // API 用バリデーション
        $credentials = $request->validated();

        // 認証
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // 既存トークン削除
        $user->tokens()->delete();

        // 新しいトークン発行
        $token = $user->createToken('next')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }
}
