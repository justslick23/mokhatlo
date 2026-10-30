<?php

namespace App\Http\Controllers;

use App\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->can('transactions.view'),
            403
        );

        $society = $request->attributes->get('currentSociety');

        $filters = $request->validate([
            'cycle_id' => ['nullable', 'integer'],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            
          'type' => [
            'nullable',
            'in:contribution_payment,contribution_penalty_payment,loan_disbursement,loan_repayment,loan_penalty_payment',
        ],

        ]);

        $cycles = $society->cycles()
            ->orderByDesc('start_date')
            ->get();

        $cycleId = null;

        if (! empty($filters['cycle_id'])) {
            $cycleId = $society->cycles()
                ->whereKey($filters['cycle_id'])
                ->firstOrFail()
                ->id;
        }

        $transactions = LedgerTransaction::query()
            ->where('society_id', $society->id)
            ->when(
                $cycleId,
                fn ($query) => $query->where(
                    'cycle_id',
                    $cycleId
                )
            )
            ->when(
                ! empty($filters['date_from']),
                fn ($query) => $query->whereDate(
                    'posting_date',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                ! empty($filters['date_to']),
                fn ($query) => $query->whereDate(
                    'posting_date',
                    '<=',
                    $filters['date_to']
                )
            )
            ->when(
                ! empty($filters['type']),
                fn ($query) => $query->where(
                    'source_type',
                    $filters['type']
                )
            )
            ->with([
                'creator',
                'lines.account',
            ])
            ->orderByDesc('posting_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.index', compact(
            'society',
            'cycles',
            'transactions',
            'filters'
        ));
    }
}
