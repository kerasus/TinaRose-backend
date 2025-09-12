<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assign permissions to roles
        $owner = Role::findByName('MoldingWorker');
        $owner->givePermissionTo([
            'view-colors',
            'view-fabrics',
            'view-productions',
            'view-productParts'
        ]);
        $owner = Role::findByName('ColoringWorker');
        $owner->givePermissionTo([
            'view-colors',
            'view-fabrics',
            'view-productions',
            'view-productParts'
        ]);
        $owner = Role::findByName('FabricCutter');
        $owner->givePermissionTo([
            'view-colors',
            'view-fabrics',
            'view-productions',
            'view-productParts'
        ]);

        $accountant = Role::findByName('Accountant');
        $accountant->givePermissionTo([
            'view-users',
            'view-colors',
            'view-fabrics',
            'view-productions',
            'view-productParts'
        ]);

        $manager = Role::findByName('Manager');
        $manager->givePermissionTo(Permission::all());
    }
}
