<?php

namespace App\Http\Controllers;

use App\Models\CyclePayoutBatch;
use App\Models\CyclePayoutDraft;
use App\Services\Payouts\ClosePaidCycle;
use App\Services\Payouts\RecordCyclePayoutPayment;
use App\Services\Payouts\StartCyclePayoutBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CyclePayoutSettlementController extends Controller
{
    public function start(
        Request $request,
        int $draft,
        StartCyclePayoutBatch $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.execute'),
            403
        );

        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $batch = $service->execute(
            $society,
            $request->user(),
            $draft,
            $data['request_key']
        );

        return redirect()
            ->route('payouts.batches.show', $batch->id)
            ->with(
                'status',
                'Payout settlement started. ' .
                'No member payment has been recorded yet.'
            );
    }

    public function show(
        Request $request,
        int $batch
    ): View {
        abort_unless(
            $request->user()->can('payouts.view'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $record = CyclePayoutBatch::query()
            ->where('society_id', $society->id)
            ->with([
                'cycle',
                'draft.items.payments.recorder',
                'approval.approver',
                'starter',
                'completer',
                'payments',
                'draft.items.membership',
                'payments.item',
                'payments.recorder',
            ])
            ->findOrFail($batch);

        $paidTotal = $record->payments->sum('amount_cents');

        $remainingTotal = max(
            0,
            $record->gross_total_cents - $paidTotal
        );

        $canExecute = $record->status === 'processing'
            && $request->user()->can('payouts.execute');

        $canClose = $record->status === 'processing'
            && $remainingTotal === 0
            && (int) $record->started_by
                !== (int) $request->user()->id
            && $request->user()->can('payouts.close');

        $paymentKeys = $record->draft->items
            ->mapWithKeys(fn ($item) => [
                $item->id => (string) Str::uuid(),
            ])
            ->all();

        return view('payouts.batch', [
            'society' => $society,
            'batch' => $record,
            'paidTotal' => $paidTotal,
            'remainingTotal' => $remainingTotal,
            'canExecute' => $canExecute,
            'canClose' => $canClose,
            'paymentKeys' => $paymentKeys,
        ]);
    }

    public function pay(
        Request $request,
        int $batch,
        int $item,
        RecordCyclePayoutPayment $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.execute'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        // Validation, permission and idempotency checks
        // also run inside the payment service.
        $service->execute(
            $society,
            $request->user(),
            $batch,
            $item,
            $request->all()
        );

        return redirect()
            ->route('payouts.batches.show', $batch)
            ->with(
                'status',
                'Member payout recorded and posted to the ledger.'
            );
    }

    public function complete(
        Request $request,
        int $batch,
        ClosePaidCycle $service
    ): RedirectResponse {
        abort_unless(
            $request->user()->can('payouts.close'),
            403
        );

        $request->validate([
            'confirmed' => ['accepted'],
        ]);

        $society = $request->attributes->get('currentSociety');

        $service->execute(
            $society,
            $request->user(),
            $batch
        );

        return redirect()
            ->route('payouts.batches.show', $batch)
            ->with(
                'status',
                'All payouts reconciled. Savings cycle closed.'
            );
    }
}
