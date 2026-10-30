<?php

namespace App\Services\Societies;

use App\Models\Society;
use App\Models\SocietyMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class CreateSociety
{
    private const ROLE_PERMISSIONS = [
        'owner' => [
            'society.manage',
            'members.view',
            'members.invite',
            'cycles.view',
            'cycles.create',
            'cycles.activate',
            'penalties.view',
            'penalties.record_payment',
            'penalties.waive',
            'transactions.view',
            'loans.apply',
            'loans.view_own',
            'loans.view',
            'loans.review',
            'loans.create_for_members',
            'loans.disburse',
'loans.record_repayment',
        ],

        'chairperson' => [
            'members.view',
            'members.invite',
            'cycles.view',
            'cycles.create',
            'cycles.activate',
            'penalties.view',
            'penalties.waive',
            'transactions.view',
            'loans.apply',
            'loans.view_own',
            'loans.view',
            'loans.review',
        ],

        'treasurer' => [
            'members.view',
            'members.invite',
            'cycles.view',
            'cycles.create',
            'penalties.view',
            'penalties.record_payment',
            'transactions.view',
            'loans.apply',
            'loans.view_own',
            'loans.view',
            'loans.review',
            'loans.create_for_members',
            'loans.disburse',
'loans.record_repayment',
        ],

        'secretary' => [
            'members.view',
            'members.invite',
            'cycles.view',
            'penalties.view',
            'transactions.view',
            'loans.apply',
            'loans.view_own',
        ],

        'member' => [
            'cycles.view',
            'penalties.view_own',
            'loans.apply',
            'loans.view_own',
        ],
    ];

    public function execute(User $creator, array $data): Society
    {
        $previousTeamId = getPermissionsTeamId();

        try {
            return DB::transaction(function () use ($creator, $data) {

                $society = Society::create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'currency_code' => 'LSL',
                    'timezone' => 'Africa/Maseru',
                    'status' => 'active',
                    'created_by' => $creator->id,
                ]);

                SocietyMembership::create([
                    'society_id' => $society->id,
                    'user_id' => $creator->id,
                    'status' => 'active',
                    'member_number' => 'M' . str_pad(
                        (string) $creator->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'joined_at' => now(),
                ]);

                setPermissionsTeamId($society->id);

                $permissionNames = array_values(
                    array_unique(
                        array_merge(
                            ...array_values(self::ROLE_PERMISSIONS)
                        )
                    )
                );

                foreach ($permissionNames as $name) {
                    Permission::findOrCreate($name, 'web');
                }

                foreach (self::ROLE_PERMISSIONS as $name => $permissions) {

                    $role = Role::firstOrCreate([
                        'society_id' => $society->id,
                        'name' => $name,
                        'guard_name' => 'web',
                    ]);

                    $role->syncPermissions($permissions);

                    if ($name === 'owner') {
                        $creator->assignRole($role);
                    }
                }

                return $society;
            });
        } finally {
            setPermissionsTeamId($previousTeamId);

            $creator->unsetRelation('roles');
            $creator->unsetRelation('permissions');
        }
    }
}