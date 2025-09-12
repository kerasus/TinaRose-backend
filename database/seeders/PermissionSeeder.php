<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::create(['name' => 'view-users']);
        Permission::create(['name' => 'view-colors']);
        Permission::create(['name' => 'view-fabrics']);
        Permission::create(['name' => 'view-productions']);
        Permission::create(['name' => 'view-productParts']);
    }
}
