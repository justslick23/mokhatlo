<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyTransactionType;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class ReconcileLegacyMokhatlo extends Command
{
    protected $signature = 'mokhatlo:legacy:reconcile
        {batchId=3 : Existing snapshot batch ID}
        {--limit=25 : Maximum loan rows to display}';

    protected $description =
        'Reconcile historical Mokhatlo loans, members and transactions without posting financial data';

    private function cents(array $row, string $column): ?int
    {
        if (
            ! array_key_exists($column, $row)
            || $row[$column] === null
            || $row[$column] === ''
        ) {
            return null;
        }

        return Money::toCents((string) $row[$column]);
    }

    private function money(?int $cents): string
    {
        return $cents === null
            ? 'Unknown'
            : 'M ' . number_format($cents / 100, 2);
    }

    public function handle(): int
    {
        $batchId = (int) $this->argument('batchId');

        $batch = DB::table('legacy_import_batches')
            ->where('id', $batchId)
            ->first();

        if (! $batch) {
            $this->error("Snapshot batch #{$batchId} was not found.");
            return self::FAILURE;
        }

        if (! in_array(
            $batch->status,
            ['completed', 'needs_review'],
            true
        )) {
            $this->error(
                'The selected snapshot is not ready for reconciliation.'
            );
            return self::FAILURE;
        }

        $records = [
            'societies' => [],
            'members' => [],
            'cycles' => [],
            'loans' => [],
            'transactions' => [],
        ];

        try {
            DB::table('legacy_import_staged_rows')
                ->where('batch_id', $batchId)
                ->orderBy('id')
                ->chunkById(250, function ($staged) use (&$records) {
                    foreach ($staged as $entry) {
                        $plain = Crypt::decryptString(
                            $entry->payload_encrypted
                        );

                        if (! hash_equals(
                            (string) $entry->source_row_sha256,
                            hash('sha256', $plain)
                        )) {
                            throw new RuntimeException(
                                "Snapshot integrity check failed: " .
                                "{$entry->source_table}/" .
                                "{$entry->source_id_value}"
                            );
                        }

                        if (! isset($records[$entry->source_table])) {
                            continue;
                        }

                        $row = json_decode(
                            $plain,
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );

                        if (
                            (string) ($row['id'] ?? '')
                            !== (string) $entry->source_id_value
                        ) {
                            throw new RuntimeException(
                                'Source row ID mismatch.'
                            );
                        }

                        $records[$entry->source_table][
                            (string) $entry->source_id_value
                        ] = $row;
                    }
                });

            if (
                count($records['loans']) === 0
                || count($records['transactions']) === 0
            ) {
                throw new RuntimeException(
                    'The snapshot does not contain loans and transactions.'
                );
            }

            /*
             * Classify all original transactions.
             *
             * Interest components are tracked separately
             * and must never be added to cash receipts.
             */
            $kinds = [];
            $linked = [];
            $memberContributions = [];
            $unlinked = [];
            $penaltyIds = [];
            $payoutIds = [];

            foreach ($records['transactions'] as $id => $row) {
                $kind = LegacyTransactionType::classify(
                    $row['type'] ?? null
                );

                $amount = $this->cents($row, 'amount');

                if ($amount === null) {
                    throw new RuntimeException(
                        "Transaction #{$id} has no valid amount."
                    );
                }

                if (! isset($kinds[$kind])) {
                    $kinds[$kind] = [
                        'count' => 0,
                        'total_cents' => 0,
                    ];
                }

                $kinds[$kind]['count']++;
                $kinds[$kind]['total_cents'] += $amount;

                $loanId = (string) ($row['loan_id'] ?? '');
                $memberId = (string) ($row['member_id'] ?? '');

                if ($kind === 'contribution_received') {
                    if (
                        $memberId === ''
                        || ! isset($records['members'][$memberId])
                    ) {
                        $unlinked[] = [
                            'transaction_id' => $id,
                            'reason' => 'Contribution has no valid member',
                        ];
                    } else {
                        $memberContributions[$memberId] =
                            ($memberContributions[$memberId] ?? 0)
                            + $amount;
                    }
                }

                if ($kind === 'penalty_unverified') {
                    $penaltyIds[] = $id;
                }

                if ($kind === 'year_end_payout_unverified') {
                    $payoutIds[] = $id;
                }

                if (in_array($kind, [
                    'loan_disbursement',
                    'loan_repayment_received',
                    'interest_component',
                ], true)) {
                    if (
                        $loanId === ''
                        || ! isset($records['loans'][$loanId])
                    ) {
                        $unlinked[] = [
                            'transaction_id' => $id,
                            'reason' => "Unlinked {$kind}",
                        ];

                        continue;
                    }

                    if (! isset($linked[$loanId][$kind])) {
                        $linked[$loanId][$kind] = [
                            'count' => 0,
                            'total_cents' => 0,
                            'by_date' => [],
                        ];
                    }

                    $linked[$loanId][$kind]['count']++;
                    $linked[$loanId][$kind]['total_cents'] += $amount;

                    $date = substr(
                        (string) ($row['transaction_date'] ?? ''),
                        0,
                        10
                    );

                    $linked[$loanId][$kind]['by_date'][$date] =
                        (
                            $linked[$loanId][$kind]['by_date'][$date]
                            ?? 0
                        ) + $amount;

                    $loanMember = (string) (
                        $records['loans'][$loanId]['member_id'] ?? ''
                    );

                    if (
                        $memberId !== ''
                        && $memberId !== $loanMember
                    ) {
                        $unlinked[] = [
                            'transaction_id' => $id,
                            'reason' =>
                                "Transaction member differs from loan #{$loanId} borrower",
                        ];
                    }
                }

                if ($kind === 'unknown') {
                    $unlinked[] = [
                        'transaction_id' => $id,
                        'reason' =>
                            'Unrecognised transaction type: ' .
                            ($row['type'] ?? ''),
                    ];
                }
            }

            /*
             * Prepare loan-by-loan reconciliation.
             */
            $loans = [];
            $loanIssues = [];
            $memberLoans = [];

            foreach ($records['loans'] as $id => $loan) {
                $principal = $this->cents($loan, 'principal');
                $contractInterest = $this->cents($loan, 'interest');
                $total = $this->cents($loan, 'total_amount');
                $storedRepaid = $this->cents($loan, 'amount_repaid');
                $storedInterestPaid = $this->cents(
                    $loan,
                    'interest_paid'
                );
                $outstanding = $this->cents(
                    $loan,
                    'outstanding_balance'
                );
                $penaltyRemaining = $this->cents(
                    $loan,
                    'penalty_amount'
                );

                $issued = $linked[$id]['loan_disbursement'] ?? [
                    'count' => 0,
                    'total_cents' => 0,
                    'by_date' => [],
                ];

                $repaid = $linked[$id]['loan_repayment_received'] ?? [
                    'count' => 0,
                    'total_cents' => 0,
                    'by_date' => [],
                ];

                $interest = $linked[$id]['interest_component'] ?? [
                    'count' => 0,
                    'total_cents' => 0,
                    'by_date' => [],
                ];

                $flags = [];

                if ($issued['count'] !== 1) {
                    $flags[] = 'Expected exactly one disbursement entry';
                }

                if (
                    $principal !== null
                    && $principal !== $issued['total_cents']
                ) {
                    $flags[] =
                        'Disbursement total differs from principal';
                }

                if (
                    $storedRepaid !== null
                    && $storedRepaid !== $repaid['total_cents']
                ) {
                    $flags[] =
                        'Stored amount_repaid differs from repayment transactions';
                }

                if (
                    $storedInterestPaid !== null
                    && $storedInterestPaid !== $interest['total_cents']
                ) {
                    $flags[] =
                        'Stored interest_paid differs from interest components';
                }

                if (
                    $contractInterest !== null
                    && $interest['total_cents'] > $contractInterest
                ) {
                    $flags[] =
                        'Interest components exceed contractual interest';
                }

                foreach ($interest['by_date'] as $date => $interestOnDate) {
                    $repaymentOnDate = $repaid['by_date'][$date] ?? 0;

                    if ($interestOnDate > $repaymentOnDate) {
                        $flags[] =
                            "Interest on {$date} exceeds associated repayments";
                    }
                }

                if (
                    $total !== null
                    && $principal !== null
                    && $contractInterest !== null
                    && $total !== $principal + $contractInterest
                ) {
                    $flags[] =
                        'Contractual total differs from principal plus interest';
                }

                if (
                    $penaltyRemaining !== null
                    && $penaltyRemaining > 0
                ) {
                    $flags[] =
                        'Remaining penalty requires separate reconciliation';
                }

                if (
                    $outstanding !== null
                    && $outstanding < 0
                ) {
                    $flags[] = 'Negative historical loan balance';
                }

                if (
                    ($loan['status'] ?? '') === 'repaid'
                    && $outstanding !== null
                    && $outstanding > 0
                ) {
                    $flags[] =
                        'Loan marked repaid with outstanding balance';
                }

                $memberId = (string) ($loan['member_id'] ?? '');

                $memberLoans[$memberId] =
                    ($memberLoans[$memberId] ?? 0) + 1;

                $loans[] = [
                    'loan_id' => $id,
                    'member_id' => $memberId,
                    'status' => $loan['status'] ?? 'unknown',
                    'principal_cents' => $principal,
                    'disbursement_cents' => $issued['total_cents'],
                    'repayments_cents' => $repaid['total_cents'],
                    'interest_components_cents' =>
                        $interest['total_cents'],
                    'stored_interest_paid_cents' =>
                        $storedInterestPaid,
                    'stored_repaid_cents' => $storedRepaid,
                    'outstanding_cents' => $outstanding,
                    'penalty_remaining_cents' =>
                        $penaltyRemaining,
                    'issue_date' => $loan['issue_date'] ?? null,
                    'due_date' => $loan['due_date'] ?? null,
                    'flags' => array_values(array_unique($flags)),
                ];

                foreach (array_unique($flags) as $flag) {
                    $loanIssues[] = [
                        'loan_id' => $id,
                        'issue' => $flag,
                    ];
                }
            }

            /*
             * Match contributions to the original member.
             * Do not guess the contribution month.
             */
            $members = [];

            foreach ($records['members'] as $id => $member) {
                $members[] = [
                    'member_id' => $id,
                    'society_id' => $member['society_id'] ?? null,
                    'status' => $member['status'] ?? 'unknown',
                    'contributions_cents' =>
                        $memberContributions[$id] ?? 0,
                    'loan_count' => $memberLoans[$id] ?? 0,
                ];
            }

            usort($loans, fn ($a, $b) =>
                (int) $a['loan_id'] <=> (int) $b['loan_id']
            );

            usort($members, fn ($a, $b) =>
                (int) $a['member_id'] <=> (int) $b['member_id']
            );

            /*
             * Save a private report.
             * No names, emails or raw source payloads
             * are included in this summary.
             */
            $report = [
                'batch_id' => $batchId,
                'source_fingerprint' => $batch->file_sha256,
                'transaction_kinds' => $kinds,
                'members' => $members,
                'loans' => $loans,
                'unlinked_transactions' => $unlinked,
                'loan_issues' => $loanIssues,
                'unverified_penalty_transaction_ids' =>
                    $penaltyIds,
                'unverified_payout_transaction_ids' =>
                    $payoutIds,
                'financial_records_imported' => false,
            ];

            $directory = storage_path(
                'app/private/legacy-audits'
            );

            File::ensureDirectoryExists(
                $directory,
                0700
            );

            $path = $directory .
                DIRECTORY_SEPARATOR .
                "batch-{$batchId}-reconciliation.json";

            File::put(
                $path,
                json_encode(
                    $report,
                    JSON_PRETTY_PRINT
                    | JSON_THROW_ON_ERROR
                )
            );

            $this->info(
                "Legacy Reconciliation — Batch #{$batchId}"
            );

            $this->newLine();
            $this->info('Transaction Totals');

            $typeRows = [];

            foreach ($kinds as $kind => $totals) {
                $typeRows[] = [
                    $kind,
                    $totals['count'],
                    $this->money($totals['total_cents']),
                ];
            }

            $this->table(
                ['Category', 'Records', 'Source Total'],
                $typeRows
            );

            $this->warn(
                'Interest components are included in repayments, not additional cash.'
            );

            $this->newLine();
            $this->info('Savings By Member');

            $this->table(
                ['Member ID', 'Status', 'Contributions', 'Loans'],
                array_map(
                    fn ($member) => [
                        $member['member_id'],
                        $member['status'],
                        $this->money(
                            $member['contributions_cents']
                        ),
                        $member['loan_count'],
                    ],
                    $members
                )
            );

            $this->newLine();
            $this->info('Loan Reconciliation');

            $limit = max(
                1,
                (int) $this->option('limit')
            );

            $this->table(
                [
                    'Loan',
                    'Member',
                    'Principal',
                    'Disbursed',
                    'Repayments',
                    'Interest Component',
                    'Outstanding',
                    'Flags',
                ],
                array_map(
                    fn ($loan) => [
                        $loan['loan_id'],
                        $loan['member_id'],
                        $this->money($loan['principal_cents']),
                        $this->money(
                            $loan['disbursement_cents']
                        ),
                        $this->money(
                            $loan['repayments_cents']
                        ),
                        $this->money(
                            $loan['interest_components_cents']
                        ),
                        $this->money(
                            $loan['outstanding_cents']
                        ),
                        count($loan['flags']),
                    ],
                    array_slice($loans, 0, $limit)
                )
            );

            $this->newLine();

            $this->table(
                ['Review category', 'Count'],
                [
                    ['Loan reconciliation issues', count($loanIssues)],
                    ['Unlinked / inconsistent transactions', count($unlinked)],
                    ['Unverified penalty transactions', count($penaltyIds)],
                    ['Unverified year-end payout records', count($payoutIds)],
                ]
            );

            foreach (array_slice($loanIssues, 0, 20) as $issue) {
                $this->warn(
                    "Loan #{$issue['loan_id']}: {$issue['issue']}"
                );
            }

            foreach (array_slice($unlinked, 0, 20) as $item) {
                $this->warn(
                    "Transaction #{$item['transaction_id']}: {$item['reason']}"
                );
            }

            $this->newLine();
            $this->info("Private report: {$path}");
            $this->info(
                'Reconciliation complete. No money has been imported.'
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Reconciliation failed: ' .
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
