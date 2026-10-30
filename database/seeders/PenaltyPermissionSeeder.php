<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PenaltyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'owner' => [
                'penalties.view',
                'penalties.record_payment',
                'penalties.waive',
                'transactions.view',
            ],

            'chairperson' => [
                'penalties.view',
                'penalties.waive',
                'transactions.view',
            ],

            'treasurer' => [
                'penalties.view',
                'penalties.record_payment',
                'transactions.view',
            ],

            'secretary' => [
                'penalties.view',
                'transactions.view',
            ],

            'member' => [
                'penalties.view_own',
            ],
        ];

        $allPermissions = array_unique(
            array_merge(
                ...array_values($rolePermissions)
            )
        );

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        foreach ($rolePermissions as $roleName => $permissions) {
            $roles = Role::query()
                ->where('name', $roleName)
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
