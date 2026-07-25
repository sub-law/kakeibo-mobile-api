<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
}
