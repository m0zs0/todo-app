<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példaadatokat csak helyi környezetben lehet betölteni.'
            );
        }

        DB::transaction(function () {
            $this->call([
                UserSeeder::class,
                TodoSeeder::class,
            ]);
        });
    }
}