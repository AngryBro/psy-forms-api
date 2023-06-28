<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Token;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User();
        $user->email = "admin@admin.ru";
        $user->save();
        $token = new Token();
        $token->token = "admintoken";
        $token->user_id = 1;
        $token->save();
    }
}
