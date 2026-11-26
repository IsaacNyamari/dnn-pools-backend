<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Nothing is seeded outside a developer machine, and no account has a known password.
     * Create the first system administrator with: php artisan app:system-admin
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
