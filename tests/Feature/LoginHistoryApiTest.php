<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_mark_login_history_as_read(): void
    {
        $user = User::factory()->create();
        $history = $user->loginHistories()->create([
            'logged_in_at' => now(),
        ]);

        $this->postJson("/api/login-histories/{$history->id}/read")
            ->assertUnauthorized();
    }

    public function test_user_can_mark_their_login_history_as_read_without_deleting_it(): void
    {
        $user = User::factory()->create();
        $history = $user->loginHistories()->create([
            'logged_in_at' => now()->subDay(),
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Test Browser',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/login-histories/{$history->id}/read")
            ->assertOk()
            ->assertJsonPath('message', 'ログイン履歴を既読にしました。');

        $this->assertNotNull($history->fresh()->read_at);
        $this->assertDatabaseHas('login_histories', [
            'id' => $history->id,
            'user_id' => $user->id,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Test Browser',
        ]);
    }

    public function test_user_cannot_mark_another_users_login_history_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $history = $otherUser->loginHistories()->create([
            'logged_in_at' => now()->subDay(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/login-histories/{$history->id}/read")
            ->assertNotFound();

        $this->assertNull($history->fresh()->read_at);
    }
}
