<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoanFinancialPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'loans.disburse',
            'loans.record_repayment',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::query()
            ->whereIn('name', ['owner', 'treasurer'])
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($permissions) {
                $role->givePermissionTo($permissions);
            });

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
