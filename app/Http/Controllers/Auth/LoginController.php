<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function __invoke(LoginRequest $request)
    {
        // API 用バリデーション
        $credentials = $request->validated();
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts(
            $throttleKey,
            self::MAX_LOGIN_ATTEMPTS
        )) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'ログイン試行回数が上限に達しました。しばらくしてから再度お試しください。',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', (string) $retryAfter);
        }

        // 認証
        if (!Auth::attempt($credentials)) {
            $attempts = RateLimiter::hit(
                $throttleKey,
                self::LOGIN_DECAY_SECONDS
            );
            $context = [
                ...$this->logContext($request),
                'attempts' => $attempts,
            ];

            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                Log::warning('auth.login.throttled', [
                    ...$context,
                    'retry_after' => RateLimiter::availableIn($throttleKey),
                ]);
            } else {
                Log::warning('auth.login.failed', $context);
            }

            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 401);
        }

        $failedAttempts = RateLimiter::attempts($throttleKey);
        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();
        $previousLogin = $user->loginHistories()
            ->orderByDesc('logged_in_at')
            ->orderByDesc('id')
            ->first();
        $loginContext = $this->logContext($request);

        $user->loginHistories()->create([
            'logged_in_at' => now(),
            'ip_address' => $loginContext['ip'],
            'user_agent' => $loginContext['user_agent'],
        ]);

        // 既存トークン削除
        $user->tokens()->delete();

        // 新しいトークン発行
        $token = $user->createToken('next')->plainTextToken;

        Log::info('auth.login.succeeded', [
            'user_id' => $user->id,
            ...$loginContext,
            'failed_attempts' => $failedAttempts,
        ]);

        return response()->json([
            'token' => $token,
            'previous_login' => $previousLogin === null
                ? null
                : [
                    'id' => $previousLogin->id,
                    'logged_in_at' => $previousLogin->logged_in_at->toIso8601String(),
                    'ip_address' => $previousLogin->ip_address,
                    'user_agent' => $previousLogin->user_agent,
                ],
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }

    private function throttleKey(LoginRequest $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $source = $email . '|' . ($request->ip() ?? 'unknown');
        $hash = hash_hmac('sha256', $source, (string) config('app.key'));

        return 'login:' . $hash;
    }

    private function logContext(LoginRequest $request): array
    {
        $userAgent = $request->userAgent();

        if ($userAgent !== null) {
            $userAgent = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);
            $userAgent = mb_strcut($userAgent ?? '', 0, 255, 'UTF-8');
        }

        return [
            'ip' => $request->ip(),
            'user_agent' => $userAgent,
        ];
    }
}
