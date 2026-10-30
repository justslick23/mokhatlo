<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanCapturePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::findOrCreate(
            'loans.create_for_members',
            'web'
        );

        Role::query()
            ->whereIn('name', ['owner', 'treasurer'])
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($permission) {
                $role->givePermissionTo($permission);
            });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
