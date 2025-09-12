<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Color;
use App\Models\Fabric;
use App\Models\Production;
use App\Models\ProductPart;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign key checks during seeding
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate tables to ensure clean state
        User::truncate();
        Color::truncate();
        Fabric::truncate();
        Production::truncate();
        ProductPart::truncate();

        // Re-enable foreign key checks
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
