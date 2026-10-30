<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'owner' => [
                'loans.apply',
                'loans.view_own',
                'loans.view',
                'loans.review',
            ],

            'chairperson' => [
                'loans.apply',
                'loans.view_own',
                'loans.view',
                'loans.review',
            ],

            'treasurer' => [
                'loans.apply',
                'loans.view_own',
                'loans.view',
            ],

            'secretary' => [
                'loans.apply',
                'loans.view_own',
            ],

            'member' => [
                'loans.apply',
                'loans.view_own',
            ],
        ];

        $allPermissions = array_unique(
            array_merge(
                ...array_values($rolePermissions)
            )
        );

        foreach ($allPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($rolePermissions as $name => $permissions) {
            $roles = Role::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
