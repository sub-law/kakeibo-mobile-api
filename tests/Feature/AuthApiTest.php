<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.email', $user->email);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'next',
        ]);
    }

    public function test_user_cannot_log_in_with_unknown_email_or_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized');

        $this->assertDatabaseCount('personal_access_tokens', 0);
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
