<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\Society;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewLoanApplication
{
    public function execute(
        Society $society,
        User $reviewer,
        int $loanId,
        string $decision,
        ?string $notes = null
    ): Loan {
        if (! in_array(
            $decision,
            ['approve', 'reject'],
            true
        )) {
            throw ValidationException::withMessages([
                'decision' => 'Invalid review decision.',
            ]);
        }

        $notes = trim($notes ?? '');

        if ($decision === 'reject' && strlen($notes) < 10) {
            throw ValidationException::withMessages([
                'review_notes' =>
                    'A rejection reason of at least 10 characters is required.',
            ]);
        }

        return DB::transaction(function () use (
            $society,
            $reviewer,
            $loanId,
            $decision,
            $notes
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSociety->status !== 'active') {
                throw ValidationException::withMessages([
                    'society' => 'This society is not active.',
                ]);
            }

            $reviewerMembership = $society->memberships()
                ->where('user_id', $reviewer->id)
                ->where('status', 'active')
                ->first();

            if (! $reviewerMembership) {
                abort(403, 'Reviewer is not an active member.');
            }

            $loan = Loan::query()
                ->where('society_id', $society->id)
                ->whereKey($loanId)
                ->lockForUpdate()
                ->firstOrFail();

            $loan->load(['membership', 'cycle']);

            if ($loan->status !== 'pending') {
                throw ValidationException::withMessages([
                    'loan' => 'This application has already been reviewed.',
                ]);
            }

            if ($loan->cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' => 'The savings cycle is not active.',
                ]);
            }

            if ($loan->membership->status !== 'active') {
                throw ValidationException::withMessages([
                    'member' => 'The applicant is not an active member.',
                ]);
            }

            
           /*      if (
                    $loan->membership->user_id === $reviewer->id
                    || $loan->submitted_by === $reviewer->id
                ) {
                    throw ValidationException::withMessages([
                        'loan' =>
                            'You cannot review a loan you borrowed or captured.',
                    ]);
                } */


            $newStatus = $decision === 'approve'
                ? 'approved'
                : 'rejected';

            $loan->update([
                'status' => $newStatus,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes ?: null,
            ]);

            $loan->events()->create([
                'society_id' => $society->id,
                'actor_id' => $reviewer->id,
                'event_type' => $newStatus,
                'from_status' => 'pending',
                'to_status' => $newStatus,
                'notes' => $notes ?: null,
            ]);

            return $loan;
        }, 3);
    }
}
