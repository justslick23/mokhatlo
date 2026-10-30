<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FinancialReportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'owner' => [
                'reports.view',
                'reports.export',
                'reports.view_own',
            ],
            'chairperson' => [
                'reports.view',
                'reports.export',
                'reports.view_own',
            ],
            'treasurer' => [
                'reports.view',
                'reports.export',
                'reports.view_own',
            ],
            'secretary' => [
                'reports.view',
                'reports.export',
                'reports.view_own',
            ],
            'member' => [
                'reports.view_own',
            ],
        ];

        foreach (array_unique(array_merge(...array_values($map))) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($map as $roleName => $permissions) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->get()
                ->each(
                    fn (Role $role) =>
                        $role->givePermissionTo($permissions)
                );
        }

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
