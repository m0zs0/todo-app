<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TodoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példafeladatokat csak helyi környezetben lehet létrehozni.'
            );
        }

        DB::transaction(function () {
            $admin = User::where('email', 'admin@example.com')->firstOrFail();
            $user = User::where('email', 'user@example.com')->firstOrFail();

            Todo::firstOrCreate([
                'user_id' => $admin->id,
                'title' => 'Admin feladat',
            ]);

            Todo::firstOrCreate([
                'user_id' => $user->id,
                'title' => 'Első feladat',
            ]);
        });
    }
}