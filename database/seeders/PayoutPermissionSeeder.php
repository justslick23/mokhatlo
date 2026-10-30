<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PayoutPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = [
            'owner' => [
                'payouts.view',
                'payouts.prepare',
                'payouts.reconcile',
                'payouts.approve',
                'payouts.execute',
                'payouts.close',
            ],

            'treasurer' => [
                'payouts.view',
                'payouts.prepare',
                'payouts.reconcile',
                'payouts.execute',
            ],

            'chairperson' => [
                'payouts.view',
                'payouts.approve',
                'payouts.close',
            ],

            'secretary' => [
                'payouts.view',
            ],
        ];

        $permissions = array_unique(
            array_merge(...array_values($mapping))
        );

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($mapping as $roleName => $permissions) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) =>
                    $role->givePermissionTo($permissions)
                );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
