<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['name' => 'Manager']);
        Role::create(['name' => 'Accountant']);
        Role::create(['name' => 'ProductManager']);
        Role::create(['name' => 'WarehouseKeeper']);
        Role::create(['name' => 'MiddleWorker']);
        Role::create(['name' => 'Assembler']);
        Role::create(['name' => 'FabricCutter']);
        Role::create(['name' => 'ColoringWorker']);
        Role::create(['name' => 'MoldingWorker']);
        Role::create(['name' => 'Customer']);
    }
}
