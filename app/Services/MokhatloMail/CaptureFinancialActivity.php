<?php

namespace App\Services\MokhatloMail;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CaptureFinancialActivity
{
    public function capture(Model $model, ?string $trigger = null): void
    {
        if (! config('mokhatlo_mail.enabled') || ! config('mokhatlo_mail.started_at')) {
            return;
        }
        $stamp = $trigger === 'status' ? $model->updated_at : $model->created_at;
        if (! $stamp || $stamp->lt(CarbonImmutable::parse(config('mokhatlo_mail.started_at')))) {
            return;
        }
        if ($model->getAttribute('legacy_source')) {
            return;
        }

        $type = class_basename($model);
        $kinds = [
            'ContributionPayment' => 'contribution_received',
            'Loan' => 'loan_requested',
            'LoanDisbursement' => 'loan_disbursed',
            'LoanRepayment' => 'loan_repayment_received',
            'ContributionPenaltyPayment' => 'contribution_penalty_received',
            'LoanPenaltyPayment' => 'loan_penalty_received',
            'ContributionPenaltyWaiver' => 'contribution_penalty_waived',
            'LoanPenaltyWaiver' => 'loan_penalty_waived',
        ];
        $kind = $trigger === 'status'
            ? 'loan_status_' . (string) $model->getAttribute('status')
            : ($kinds[$type] ?? null);
        if (! $kind) {
            return;
        }
        $societyId = (int) $model->getAttribute('society_id');
        if ($societyId < 1) {
            return;
        }
        $loanId = $type === 'Loan' ? $model->getKey() : $model->getAttribute('loan_id');
        if (! $loanId && in_array($type, ['LoanPenaltyPayment', 'LoanPenaltyWaiver'], true)) {
            $penaltyId = $model->getAttribute('loan_penalty_id');
            $loanId = $penaltyId
                ? DB::table('loan_penalties')->where('id', $penaltyId)->value('loan_id')
                : null;
        }
        $loan = $loanId ? DB::table('loans')->where('society_id', $societyId)
            ->where('id', $loanId)->first(['id', 'cycle_id', 'society_membership_id', 'loan_number']) : null;
        $membershipId = $model->getAttribute('society_membership_id') ?: $loan?->society_membership_id;
        if (! $membershipId && in_array($type, ['ContributionPenaltyPayment', 'ContributionPenaltyWaiver'], true)) {
            $penaltyId = $model->getAttribute('contribution_penalty_id');
            $membershipId = $penaltyId
                ? DB::table('contribution_penalties as cp')
                    ->join('contribution_obligations as o', 'o.id', '=', 'cp.contribution_obligation_id')
                    ->where('cp.id', $penaltyId)->value('o.society_membership_id') : null;
        }
        $member = $membershipId ? DB::table('society_memberships as sm')
            ->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('sm.society_id', $societyId)->where('sm.id', $membershipId)
            ->first(['u.name']) : null;
        $dateColumn = match ($type) {
            'ContributionPayment', 'LoanRepayment', 'ContributionPenaltyPayment', 'LoanPenaltyPayment' => 'paid_on',
            'LoanDisbursement' => 'disbursed_on',
            default => null,
        };
        $date = $dateColumn ? $model->getAttribute($dateColumn) : null;
        $occurred = $date ? CarbonImmutable::parse($date)->toDateString()
            : now('Africa/Maseru')->toDateString();
        $amount = $type === 'Loan' ? $model->getAttribute('principal_amount') : $model->getAttribute('amount');
        $payload = [
            'type' => $kind,
            'member' => $member?->name ?: 'Member',
            'amount' => $amount === null ? null : (string) $amount,
            'loan_number' => $loan?->loan_number ?? $model->getAttribute('loan_number'),
            'loan_status' => $type === 'Loan' ? $model->getAttribute('status') : null,
            'transaction_date' => $occurred,
        ];
        // Retain the exact originating cycle even when the society changes its active cycle.
        $cycleId = $model->getAttribute('cycle_id') ?: $loan?->cycle_id;
        DB::transaction(function () use ($societyId, $kind, $type, $model, $cycleId, $occurred, $payload): void {
            $inserted = DB::table('society_mail_events')->insertOrIgnore([
                'society_id' => $societyId, 'cycle_id' => $cycleId,
                'kind' => $kind, 'source_type' => $type, 'source_id' => $model->getKey(),
                'occurred_on' => $occurred, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // A single transaction makes source-event capture and recipient fanout atomic.
            // Never add recipients retroactively to an already-captured event.
            if ($inserted) {
                $eventId = DB::table('society_mail_events')->where([
                    'society_id' => $societyId, 'kind' => $kind,
                    'source_type' => $type, 'source_id' => $model->getKey(),
                ])->value('id');
                app(RecipientOutbox::class)->forEvent($societyId, (int) $eventId);
            }
        }, 3);
    }
}
