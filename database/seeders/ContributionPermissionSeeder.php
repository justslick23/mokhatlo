<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ContributionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'contributions.view',
            'contributions.view_own',
            'contributions.record',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'owner' => [
                'contributions.view',
                'contributions.record',
            ],
            'chairperson' => [
                'contributions.view',
            ],
            'treasurer' => [
                'contributions.view',
                'contributions.record',
            ],
            'secretary' => [
                'contributions.view',
            ],
            'member' => [
                'contributions.view_own',
            ],
        ];

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
