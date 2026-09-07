<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'previous_login', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('previous_login', null)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'next',
        ]);
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_successful_login_returns_previous_login_and_records_current_login(): void
    {
        $previousLoginAt = now()->subDay()->startOfSecond();
        $currentLoginAt = now()->startOfSecond();
        $user = User::factory()->create(['email' => 'returning-user@example.com']);
        $previousLogin = $user->loginHistories()->create([
            'logged_in_at' => $previousLoginAt,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Previous Browser',
        ]);

        $this->travelTo($currentLoginAt);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->withHeader('User-Agent', 'Current Browser')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('previous_login', [
                'id' => $previousLogin->id,
                'logged_in_at' => $previousLoginAt->toIso8601String(),
                'ip_address' => '192.0.2.10',
                'user_agent' => 'Previous Browser',
            ]);

        $this->assertDatabaseCount('login_histories', 2);
        $currentLogin = $user->loginHistories()
            ->orderByDesc('logged_in_at')
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertTrue($currentLogin->logged_in_at->equalTo($currentLoginAt));
        $this->assertSame('192.0.2.11', $currentLogin->ip_address);
        $this->assertSame('Current Browser', $currentLogin->user_agent);
        $this->assertNull($currentLogin->read_at);
    }

    public function test_user_cannot_log_in_with_unknown_email_or_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'メールアドレスまたはパスワードが正しくありません。');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'メールアドレスまたはパスワードが正しくありません。');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'throttle@example.com',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath(
                'message',
                'ログイン試行回数が上限に達しました。しばらくしてから再度お試しください。'
            )
            ->assertJsonStructure(['retry_after'])
            ->assertHeader('Retry-After');

        $this->assertGreaterThan(0, $response->json('retry_after'));
        $this->assertSame(
            (string) $response->json('retry_after'),
            $response->headers->get('Retry-After')
        );
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_successful_login_clears_failed_attempt_count(): void
    {
        $user = User::factory()->create([
            'email' => 'throttle-reset@example.com',
        ]);

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        Auth::logout();
        $this->flushSession();
        Auth::forgetGuards();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_login_is_allowed_after_throttle_expires(): void
    {
        $user = User::factory()->create([
            'email' => 'throttle-expiry@example.com',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->travel(61)->seconds();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_login_throttle_is_scoped_by_email_and_ip(): void
    {
        $user = User::factory()->create([
            'email' => 'throttle-scope@example.com',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_successful_login_is_logged_without_credentials(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'email' => 'login-log@example.com',
        ]);

        $this->withHeader('User-Agent', 'AuthApiTest Browser')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertOk();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($user): bool {
                return $message === 'auth.login.succeeded'
                    && $context === [
                        'user_id' => $user->id,
                        'ip' => '127.0.0.1',
                        'user_agent' => 'AuthApiTest Browser',
                        'failed_attempts' => 0,
                    ];
            });
    }

    public function test_failed_and_throttled_login_events_are_logged_without_credentials(): void
    {
        Log::spy();

        $user = User::factory()->create([
            'email' => 'failed-login-log@example.com',
        ]);

        foreach (range(1, 6) as $attempt) {
            $this->withHeader('User-Agent', 'AuthApiTest Browser')
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ]);
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'auth.login.failed'
                    && $context['ip'] === '127.0.0.1'
                    && $context['user_agent'] === 'AuthApiTest Browser'
                    && $context['attempts'] >= 1
                    && $context['attempts'] <= 4
                    && ! array_key_exists('email', $context)
                    && ! array_key_exists('password', $context)
                    && ! array_key_exists('token', $context);
            })
            ->times(4);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'auth.login.throttled'
                    && $context['ip'] === '127.0.0.1'
                    && $context['user_agent'] === 'AuthApiTest Browser'
                    && $context['attempts'] === 5
                    && $context['retry_after'] > 0
                    && ! array_key_exists('email', $context)
                    && ! array_key_exists('password', $context)
                    && ! array_key_exists('token', $context);
            })
            ->once();
    }

    public function test_login_returns_japanese_validation_errors(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.email.0', 'メールアドレスを入力してください。')
            ->assertJsonPath('errors.password.0', 'パスワードを入力してください。');

        $this->postJson('/api/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'メールアドレスの形式が正しくありません。');
    }

    public function test_issued_token_can_access_authenticated_user_endpoint(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_token_within_thirty_day_expiration_can_access_authenticated_endpoint(): void
    {
        config(['sanctum.expiration' => 43200]);

        $user = User::factory()->create();
        $newAccessToken = $user->createToken('within-expiration');
        $newAccessToken->accessToken->forceFill([
            'created_at' => now()->subDays(29),
        ])->save();

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$newAccessToken->plainTextToken}",
        ])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_token_older_than_thirty_day_expiration_is_rejected(): void
    {
        config(['sanctum.expiration' => 43200]);

        $user = User::factory()->create();
        $newAccessToken = $user->createToken('expired');
        $newAccessToken->accessToken->forceFill([
            'created_at' => now()->subDays(31),
        ])->save();

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$newAccessToken->plainTextToken}",
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_user_endpoint_requires_a_valid_token(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');

        $this->getJson('/api/user', [
            'Authorization' => 'Bearer invalid-token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_logging_in_replaces_the_users_existing_tokens(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old-token')->plainTextToken;

        $newToken = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        Auth::logout();
        $this->flushSession();
        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$oldToken}",
        ])->assertUnauthorized();

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$newToken}",
        ])->assertOk();
    }

    public function test_logout_invalidates_only_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $remainingToken = $user->createToken('remaining')->plainTextToken;
        $otherUserToken = $otherUser->createToken('other-user')->plainTextToken;

        $this->postJson('/api/logout', [], [
            'Authorization' => "Bearer {$currentToken}",
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Logout');

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$currentToken}",
        ])->assertUnauthorized();

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$remainingToken}",
        ])->assertOk()->assertJsonPath('id', $user->id);

        Auth::forgetGuards();

        $this->getJson('/api/user', [
            'Authorization' => "Bearer {$otherUserToken}",
        ])->assertOk()->assertJsonPath('id', $otherUser->id);
    }

    public function test_unauthenticated_user_cannot_change_password(): void
    {
        $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_user_can_change_password_and_revoke_all_of_their_tokens(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('remaining');
        $otherUser->createToken('other-user');

        $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], [
            'Authorization' => "Bearer {$currentToken}",
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'パスワードを変更しました。再度ログインしてください。'
            );

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $otherUser->id,
            'tokenable_type' => User::class,
            'name' => 'other-user',
        ]);
    }

    public function test_password_change_rejects_incorrect_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;

        $this->putJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password'])
            ->assertJsonPath(
                'errors.current_password.0',
                '現在のパスワードが正しくありません。'
            );

        $user->refresh();

        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'current',
        ]);
    }

    public function test_password_change_returns_japanese_validation_errors(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;

        $this->putJson('/api/user/password', [], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password'])
            ->assertJsonPath(
                'errors.current_password.0',
                '現在のパスワードを入力してください。'
            )
            ->assertJsonPath(
                'errors.password.0',
                '新しいパスワードを入力してください。'
            );

        $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'different',
        ], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath(
                'errors.password.0',
                '新しいパスワードは8文字以上で入力してください。'
            )
            ->assertJsonPath(
                'errors.password.1',
                '新しいパスワード（確認）が一致しません。'
            );

        $this->putJson('/api/user/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                '現在のパスワードとは異なるパスワードを入力してください。'
            );
    }
}
