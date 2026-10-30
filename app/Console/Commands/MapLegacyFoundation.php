<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MapLegacyFoundation extends Command
{
    protected $signature = 'mokhatlo:legacy:map-foundation
        {batchId=3 : Staged legacy batch}
        {--society= : Existing Mokhatlo 2.0 society ID}
        {--cycle= : Existing target cycle ID (must have matching dates)}
        {--create-users : Permit creation of missing user accounts}
        {--create-cycle : Permit creation of a historical draft cycle}
        {--allow-name-mismatch : Explicitly accept a renamed target society}
        {--apply : Write identity records and permanent ID mappings}
        {--force : Allow --apply in production}';

    protected $description = 'Preview or map legacy users, members and cycle to an existing society; no financial postings';

    public function handle(): int
    {
        try {
            $batchId = (int) $this->argument('batchId');
            $batch = DB::table('legacy_import_batches')->find($batchId);

            if (! $batch || ! in_array(
                $batch->status,
                ['completed', 'needs_review'],
                true
            )) {
                throw new RuntimeException(
                    'Select a completed/needs_review source snapshot.'
                );
            }

            if (! $this->option('society')) {
                $this->info('Existing target societies:');

                $this->table(
                    ['ID', 'Name', 'Status'],
                    DB::table('societies')
                        ->orderBy('id')
                        ->get(['id', 'name', 'status'])
                        ->map(fn ($row) => [
                            (string) $row->id,
                            $row->name,
                            $row->status,
                        ])
                        ->all()
                );

                $this->warn(
                    'Rerun with --society=<existing ID>; nothing was written.'
                );

                return self::FAILURE;
            }

            $targetSocietyId = filter_var(
                $this->option('society'),
                FILTER_VALIDATE_INT
            );

            if (! $targetSocietyId || $targetSocietyId < 1) {
                throw new RuntimeException('Invalid --society ID.');
            }

            $society = DB::table('societies')
                ->find($targetSocietyId);

            if (! $society || $society->currency_code !== 'LSL') {
                throw new RuntimeException(
                    'Target society not found or has a non-LSL currency.'
                );
            }

            $sourceId = (int) $batch->source_id;
            $records = $this->readSnapshot($batchId, $batch);

            foreach (
                ['users', 'societies', 'members', 'cycles']
                as $table
            ) {
                if (count($records[$table]) === 0) {
                    throw new RuntimeException(
                        "Snapshot lacks {$table} records."
                    );
                }
            }

            if (
                count($records['societies']) !== 1
                || count($records['cycles']) !== 1
            ) {
                throw new RuntimeException(
                    'This command requires one legacy society and one cycle per batch.'
                );
            }

            $legacySociety = reset($records['societies']);
            $legacyCycle = reset($records['cycles']);

            if (
                mb_strtolower(trim((string) $legacySociety['name']))
                !== mb_strtolower(trim((string) $society->name))
            ) {
                if (! $this->option('allow-name-mismatch')) {
                    throw new RuntimeException(
                        'Target society name differs from legacy society. ' .
                        'Verify the identity and use --allow-name-mismatch only for a confirmed rename.'
                    );
                }

                $this->warn(
                    'Society name mismatch explicitly accepted; verify before applying.'
                );
            }

            $legacySocietyId = (string) $legacySociety['id'];
            $legacyCycleId = (string) $legacyCycle['id'];

            if (
                (string) $legacyCycle['society_id']
                !== $legacySocietyId
            ) {
                throw new RuntimeException(
                    'Legacy cycle does not belong to legacy society.'
                );
            }

            $targetCycle = $this->resolveCycle(
                $targetSocietyId,
                $legacyCycle,
                $sourceId,
                $records['cycles'][$legacyCycleId]['_hash']
            );

            $this->mappedTarget(
                $sourceId,
                'societies',
                $legacySocietyId,
                'societies',
                $records['societies'][$legacySocietyId]['_hash'],
                $targetSocietyId
            );

            $matches = [];
            $seenEmails = [];

            foreach ($records['users'] as $legacyId => $user) {
                $email = mb_strtolower(
                    trim((string) ($user['email'] ?? ''))
                );

                if (
                    ! filter_var($email, FILTER_VALIDATE_EMAIL)
                    || isset($seenEmails[$email])
                ) {
                    throw new RuntimeException(
                        'Missing, malformed or duplicate legacy user email.'
                    );
                }

                $seenEmails[$email] = true;

                $match = DB::table('users')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->get(['id', 'email']);

                if ($match->count() > 1) {
                    throw new RuntimeException(
                        "Multiple target users match legacy user #{$legacyId}."
                    );
                }

                $targetId = $match->first()?->id;

                $this->mappedTarget(
                    $sourceId,
                    'users',
                    (string) $legacyId,
                    'users',
                    $user['_hash'],
                    $targetId
                );

                $matches[(string) $legacyId] = [
                    'email' => $email,
                    'target_id' => $targetId,
                    'row' => $user,
                ];
            }

            $preview = [];

            foreach ($matches as $oldId => $info) {
                $preview[] = [
                    'users',
                    $oldId,
                    $info['target_id'] ?: '—',
                    $info['target_id']
                        ? 'Map existing user'
                        : 'Create new user (password reset required)',
                ];
            }

            foreach ($records['members'] as $oldId => $member) {
                if (
                    (string) $member['society_id']
                    !== $legacySocietyId
                ) {
                    throw new RuntimeException(
                        "Legacy membership #{$oldId} belongs to a different society."
                    );
                }

                $userId = (string) $member['user_id'];

                if (! isset($matches[$userId])) {
                    throw new RuntimeException(
                        "Membership #{$oldId} refers to a missing legacy user."
                    );
                }

                $newUserId = $matches[$userId]['target_id'];

                $existing = $newUserId
                    ? DB::table('society_memberships')
                        ->where('society_id', $targetSocietyId)
                        ->where('user_id', $newUserId)
                        ->first()
                    : null;

                $this->mappedTarget(
                    $sourceId,
                    'members',
                    (string) $oldId,
                    'society_memberships',
                    $member['_hash'],
                    $existing?->id
                );

                $preview[] = [
                    'members',
                    $oldId,
                    $existing?->id ?: '—',
                    $existing
                        ? 'Map existing membership'
                        : 'Create membership (no roles assigned)',
                ];
            }

            $preview[] = [
                'societies',
                $legacySocietyId,
                $targetSocietyId,
                'Map to selected existing society',
            ];

            $preview[] = [
                'cycles',
                $legacyCycleId,
                $targetCycle?->id ?: '—',
                $targetCycle
                    ? 'Map matching cycle'
                    : 'Create draft historical cycle',
            ];

            $this->table(
                ['Source table', 'Old ID', 'New ID', 'Operation'],
                $preview
            );

            $this->line(
                'Original cycle: ' .
                $legacyCycle['start_date'] .
                ' to ' .
                $legacyCycle['end_date'] .
                ' (old status: ' .
                ($legacyCycle['status'] ?? 'unknown') . ')'
            );

            $this->warn(
                'No loan, interest, contribution or penalty amounts will be posted.'
            );

            $this->warn(
                'Historical member roles are NOT automatically assigned.'
            );

            if (! $this->option('apply')) {
                $this->info(
                    'PREVIEW ONLY. Use --apply after checking target society/cycle and identity matches.'
                );

                return self::SUCCESS;
            }

            if (
                app()->environment('production')
                && ! $this->option('force')
            ) {
                throw new RuntimeException(
                    'Production writes require --force and an independently verified backup.'
                );
            }

            if (
                collect($matches)->contains(
                    fn ($user) => ! $user['target_id']
                )
                && ! $this->option('create-users')
            ) {
                throw new RuntimeException(
                    'Some target users are missing. Pass --create-users explicitly.'
                );
            }

            if (
                ! $targetCycle
                && ! $this->option('create-cycle')
            ) {
                throw new RuntimeException(
                    'No matching target cycle. Pass --create-cycle explicitly or --cycle=<ID>.'
                );
            }

            $result = DB::transaction(function () use (
                $sourceId,
                $records,
                $matches,
                $targetSocietyId,
                $legacySocietyId,
                $legacyCycleId,
                $legacyCycle,
                $targetCycle
            ) {
                $locked = DB::table('societies')
                    ->where('id', $targetSocietyId)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new RuntimeException(
                        'Target society was removed.'
                    );
                }

                $this->saveMap(
                    $sourceId,
                    'societies',
                    $legacySocietyId,
                    'societies',
                    $targetSocietyId,
                    $records['societies'][$legacySocietyId]['_hash']
                );

                $userIds = [];

                foreach ($matches as $oldId => $info) {
                    $newId = $info['target_id'];

                    if (! $newId) {
                        $newId = DB::table('users')->insertGetId([
                            'name' => (string) $info['row']['name'],
                            'email' => $info['email'],
                            'password' => Hash::make(
                                Str::random(80)
                            ),
                            'email_verified_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $userIds[$oldId] = (int) $newId;

                    $this->saveMap(
                        $sourceId,
                        'users',
                        $oldId,
                        'users',
                        (int) $newId,
                        $info['row']['_hash']
                    );
                }

                foreach ($records['members'] as $oldId => $member) {
                    $newUserId = $userIds[
                        (string) $member['user_id']
                    ];

                    $membership = DB::table('society_memberships')
                        ->where('society_id', $targetSocietyId)
                        ->where('user_id', $newUserId)
                        ->first();

                    $newId = $membership?->id;

                    if (! $newId) {
                        $status = (
                            ($member['status'] ?? '') === 'active'
                            && empty($member['deleted_at'])
                        ) ? 'active' : 'inactive';

                        $newId = DB::table('society_memberships')
                            ->insertGetId([
                                'society_id' => $targetSocietyId,
                                'user_id' => $newUserId,
                                'member_number' => 'LEGACY-' . $oldId,
                                'status' => $status,
                                'joined_at' =>
                                    $member['joined_date']
                                    ?? $member['created_at']
                                    ?? now(),
                                'left_at' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    $this->saveMap(
                        $sourceId,
                        'members',
                        (string) $oldId,
                        'society_memberships',
                        (int) $newId,
                        $member['_hash']
                    );
                }

                $cycleId = $targetCycle?->id;

                if (! $cycleId) {
                    $cycleId = DB::table('cycles')->insertGetId([
                        'society_id' => $targetSocietyId,
                        'name' => (string) $legacyCycle['name'],
                        'code' =>
                            'LEGACY-' .
                            $legacySocietyId .
                            '-' .
                            $legacyCycleId,
                        'start_date' => $legacyCycle['start_date'],
                        'end_date' => $legacyCycle['end_date'],
                        'status' => 'draft',
                        'active_slot' => null,
                        'activated_at' => null,
                        'closed_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->saveMap(
                    $sourceId,
                    'cycles',
                    $legacyCycleId,
                    'cycles',
                    (int) $cycleId,
                    $records['cycles'][$legacyCycleId]['_hash']
                );

                return $cycleId;
            }, 3);

            $this->info(
                'Foundation mapping committed. Target cycle ID: ' .
                $result
            );

            $this->info(
                'No financial transactions, interest credits or ledger entries were created.'
            );

            $this->warn(
                'New accounts must complete password reset/verification. Review historic roles and cycle policy separately.'
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function readSnapshot(
        int $batchId,
        object $batch
    ): array {
        $rows = array_fill_keys(
            ['users', 'societies', 'members', 'cycles'],
            []
        );

        $counts = (
            json_decode($batch->counts ?? '{}', true)['tables']
            ?? []
        );

        foreach (
            DB::table('legacy_import_staged_rows')
                ->where('batch_id', $batchId)
                ->whereIn('source_table', array_keys($rows))
                ->orderBy('id')
                ->get()
            as $entry
        ) {
            $plain = Crypt::decryptString(
                $entry->payload_encrypted
            );

            if (! hash_equals(
                $entry->source_row_sha256,
                hash('sha256', $plain)
            )) {
                throw new RuntimeException(
                    'Staged source fingerprint mismatch: ' .
                    $entry->source_table
                );
            }

            $data = json_decode(
                $plain,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                (string) ($data['id'] ?? '')
                !== (string) $entry->source_id_value
            ) {
                throw new RuntimeException(
                    'Staged source identity mismatch.'
                );
            }

            $data['_hash'] = $entry->source_row_sha256;

            $rows[$entry->source_table][
                (string) $entry->source_id_value
            ] = $data;
        }

        foreach ($rows as $table => $data) {
            if (
                isset($counts[$table])
                && count($data) !== (int) $counts[$table]
            ) {
                throw new RuntimeException(
                    "Snapshot table {$table} is incomplete."
                );
            }
        }

        return $rows;
    }

    private function resolveCycle(
        int $societyId,
        array $old,
        int $sourceId,
        string $hash
    ): ?object {
        $oldId = (string) $old['id'];
        $explicit = $this->option('cycle');

        if ($explicit !== null && $explicit !== '') {
            $cycle = DB::table('cycles')
                ->where('society_id', $societyId)
                ->where('id', (int) $explicit)
                ->first();

            if (! $cycle) {
                throw new RuntimeException(
                    'Selected target cycle does not exist in society.'
                );
            }
        } else {
            $candidates = DB::table('cycles')
                ->where('society_id', $societyId)
                ->whereDate('start_date', $old['start_date'])
                ->whereDate('end_date', $old['end_date'])
                ->get();

            if ($candidates->count() > 1) {
                throw new RuntimeException(
                    'Multiple target cycles have matching dates; choose --cycle=<ID>.'
                );
            }

            $cycle = $candidates->first();
        }

        if (
            $cycle
            && (
                substr((string) $cycle->start_date, 0, 10)
                    !== $old['start_date']
                || substr((string) $cycle->end_date, 0, 10)
                    !== $old['end_date']
            )
        ) {
            throw new RuntimeException(
                'Target cycle dates differ from the historical cycle.'
            );
        }

        $this->mappedTarget(
            $sourceId,
            'cycles',
            $oldId,
            'cycles',
            $hash,
            $cycle?->id
        );

        return $cycle;
    }

    private function mappedTarget(
        int $sourceId,
        string $table,
        string $oldId,
        string $targetTable,
        string $hash,
        ?int $expectedTarget
    ): void {
        $map = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)
            ->where('source_table', $table)
            ->where('source_id_value', $oldId)
            ->first();

        if (! $map) {
            return;
        }

        if (
            $map->target_table !== $targetTable
            || (
                $map->source_row_sha256
                && ! hash_equals(
                    $map->source_row_sha256,
                    $hash
                )
            )
            || (
                $expectedTarget !== null
                && (int) $map->target_id !== $expectedTarget
            )
        ) {
            throw new RuntimeException(
                "Previous mapping conflicts with {$table} #{$oldId}."
            );
        }

        if ($expectedTarget === null) {
            throw new RuntimeException(
                "Previously mapped {$table} #{$oldId} no longer has a valid target record."
            );
        }
    }

    private function saveMap(
        int $sourceId,
        string $table,
        string $oldId,
        string $targetTable,
        int $targetId,
        string $hash
    ): void {
        $this->mappedTarget(
            $sourceId,
            $table,
            $oldId,
            $targetTable,
            $hash,
            $targetId
        );

        $exists = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)
            ->where('source_table', $table)
            ->where('source_id_value', $oldId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('legacy_record_maps')->insert([
            'source_id' => $sourceId,
            'source_table' => $table,
            'source_id_value' => $oldId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'source_row_sha256' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
