<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;

class Authenticate implements AuthenticatesRequests
{
    protected $auth;

    protected static $redirectToCallback;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public static function using($guard, ...$others)
    {
        return static::class . ':' . implode(',', [$guard, ...$others]);
    }

    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        return $next($request);
    }

    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        $this->unauthenticated($request, $guards);
    }

    protected function unauthenticated($request, array $guards)
    {
        // API ではリダイレクトせず、401 を返す
        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            null, // ← redirectTo を無効化
        );
    }

    protected function redirectTo(Request $request)
    {
        return null; // ← 念のため null を返す
    }

    public static function redirectUsing(callable $redirectToCallback)
    {
        static::$redirectToCallback = $redirectToCallback;
    }
}
