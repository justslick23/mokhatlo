<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanPenaltyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'owner' => [
                'loans.penalties.view',
                'loans.penalties.collect',
                'loans.penalties.waive',
            ],
            'chairperson' => [
                'loans.penalties.view',
                'loans.penalties.waive',
            ],
            'treasurer' => [
                'loans.penalties.view',
                'loans.penalties.collect',
            ],
            'secretary' => [
                'loans.penalties.view',
            ],
            'member' => [
                'loans.penalties.view_own',
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
