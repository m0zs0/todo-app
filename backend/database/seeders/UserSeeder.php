<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példafelhasználókat csak helyi környezetben lehet létrehozni.'
            );
        }

        DB::transaction(function () {
            $emails = [
                'admin@example.com',
                'user@example.com',
            ];

            if (User::whereIn('email', $emails)->exists()) {
                throw new RuntimeException(
                    'Legalább az egyik példafelhasználó már létezik.'
                );
            }

            User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'adminpw',
                'role' => 'admin',
            ]);

            User::create([
                'name' => 'Sima User',
                'email' => 'user@example.com',
                'password' => 'userpw',
                'role' => 'user',
            ]);
        });
    }
}