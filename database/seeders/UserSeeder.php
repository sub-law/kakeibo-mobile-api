<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('DEFAULT_USER_EMAIL')],
            [
                'name' => 'kakeibotaro',
                'password' => bcrypt(env('DEFAULT_USER_PASSWORD')),
            ]
        );
    }
}
