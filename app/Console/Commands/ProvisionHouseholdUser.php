<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionHouseholdUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:provision-household-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '非公開の家計簿ユーザーと初期口座を対話形式で作成します';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('名前'));
        $email = mb_strtolower(trim((string) $this->ask('メールアドレス')));
        $password = (string) $this->secret('パスワード（12文字以上）');
        $passwordConfirmation = (string) $this->secret('パスワード（確認）');
        $accounts = [];

        do {
            $accounts[] = [
                'name' => trim((string) $this->ask('口座名')),
                'type' => (string) $this->choice(
                    '口座種別',
                    ['bank', 'securities', 'cash'],
                    'bank'
                ),
            ];
        } while ($this->confirm('別の口座も追加しますか？', false));

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'accounts' => $accounts,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12),
                ],
                'accounts' => ['required', 'array', 'min:1'],
                'accounts.*.name' => [
                    'required',
                    'string',
                    'max:255',
                    'distinct',
                ],
                'accounts.*.type' => [
                    'required',
                    'in:bank,securities,cash',
                ],
            ],
            [
                'name.required' => '名前を入力してください。',
                'email.required' => 'メールアドレスを入力してください。',
                'email.email' => 'メールアドレスの形式が正しくありません。',
                'email.unique' => 'このメールアドレスは既に使用されています。',
                'password.required' => 'パスワードを入力してください。',
                'password.confirmed' => 'パスワードの確認が一致しません。',
                'password.min' => 'パスワードは12文字以上にしてください。',
                'accounts.*.name.required' => '口座名を入力してください。',
                'accounts.*.name.distinct' => '同じ口座名が重複しています。',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("ユーザー: {$name} <{$email}>");

        foreach ($accounts as $account) {
            $this->line("口座: {$account['name']} ({$account['type']})");
        }

        if (! $this->confirm('この内容で作成しますか？', false)) {
            $this->info('作成を中止しました。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($name, $email, $password, $accounts): void {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->accounts()->createMany($accounts);
        });

        $this->info('ユーザーと口座を作成しました。');

        return self::SUCCESS;
    }
}
