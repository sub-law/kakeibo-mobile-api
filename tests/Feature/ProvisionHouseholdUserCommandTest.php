<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionHouseholdUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_a_user_and_their_accounts(): void
    {
        $password = 'secure-password-123';

        $this->artisan('app:provision-household-user')
            ->expectsQuestion('名前', '追加ユーザー')
            ->expectsQuestion('メールアドレス', 'additional-user@example.com')
            ->expectsQuestion('パスワード（12文字以上）', $password)
            ->expectsQuestion('パスワード（確認）', $password)
            ->expectsQuestion('口座名', '生活口座')
            ->expectsChoice(
                '口座種別',
                'bank',
                ['bank', 'securities', 'cash']
            )
            ->expectsConfirmation('別の口座も追加しますか？', 'yes')
            ->expectsQuestion('口座名', '現金')
            ->expectsChoice(
                '口座種別',
                'cash',
                ['bank', 'securities', 'cash']
            )
            ->expectsConfirmation('別の口座も追加しますか？', 'no')
            ->expectsConfirmation('この内容で作成しますか？', 'yes')
            ->expectsOutput('ユーザーと口座を作成しました。')
            ->assertSuccessful();

        $user = User::where('email', 'additional-user@example.com')->firstOrFail();

        $this->assertSame('追加ユーザー', $user->name);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => '生活口座',
            'type' => 'bank',
        ]);
        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => '現金',
            'type' => 'cash',
        ]);
    }
}
