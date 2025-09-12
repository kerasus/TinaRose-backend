<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve roles from the database
        $managerRole = Role::where('name', 'Manager')->first();

        // Seed users
        $users = [
            [
                'firstname' => 'علی',
                'lastname' => 'اسمعیلی',
                'username' => 'krasus',
                'password' => bcrypt('123456789'), // Hash the password
                'mobile' => '09964007332',
                'email' => 'krasus@example.com',
                'role' => 'Manager'
            ],
            [
                'firstname' => 'حمید',
                'lastname' => 'طاوسی',
                'username' => 'hamid',
                'password' => bcrypt('123456789'), // Hash the password
                'mobile' => '09127510537',
                'email' => 'hamid@example.com',
                'role' => 'Manager'
            ]
        ];

        foreach ($users as $userData) {
            // Remove the 'role' field from the user data
            $userRole = $userData['role'] ?? null;
            unset($userData['role']);

            // Create the user
            $user = User::factory()->create($userData);

            // Assign role to the user
            if (isset($userRole)) {
                if ($userRole === 'Manager') {
                    $user->assignRole($managerRole);
                }
            }
        }
    }
}
