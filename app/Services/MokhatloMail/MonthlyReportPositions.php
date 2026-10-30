<?php

namespace App\Services\MokhatloMail;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Historic AS-OF balances: no current LoanBalances::summary() leakage. */
final class MonthlyReportPositions
{
    private function cents($rows, string $field = 'amount'): int
    {
        return $rows->sum(fn ($p) => Money::toCents((string) $p->{$field}));
    }

    /** @return array<string,int> */
    public function member(int $societyId, int $cycleId, int $membershipId, string $end): array
    {
        $savings = $this->cents(DB::table('contribution_payments')
            ->where('society_id', $societyId)->where('cycle_id', $cycleId)
            ->where('society_membership_id', $membershipId)
            ->whereDate('paid_on', '<=', $end)->get(['amount']));

        $interest = $this->cents(DB::table('loan_interest_credits as c')
            ->join('loans as l', 'l.id', '=', 'c.loan_id')
            ->join('loan_repayments as r', 'r.id', '=', 'c.loan_repayment_id')
            ->where('c.society_id', $societyId)->where('l.society_id', $societyId)
            ->where('r.society_id', $societyId)->where('l.cycle_id', $cycleId)
            ->where('c.beneficiary_membership_id', $membershipId)
            ->whereDate('r.paid_on', '<=', $end)->get(['c.amount']));

        $loans = DB::table('loans as l')
            ->join('loan_disbursements as d', 'd.loan_id', '=', 'l.id')
            ->where('l.society_id', $societyId)->where('d.society_id', $societyId)
            ->where('l.cycle_id', $cycleId)->where('l.society_membership_id', $membershipId)
            ->whereDate('d.disbursed_on', '<=', $end)
            ->distinct()->get(['l.id','l.quoted_total_amount']);
        $loanDue = 0;
        foreach ($loans as $loan) {
            $repaid = $this->cents(DB::table('loan_repayments')
                ->where('society_id', $societyId)->where('loan_id', $loan->id)
                ->whereDate('paid_on', '<=', $end)->get(['amount']));
            $loanDue += max(0, Money::toCents((string) $loan->quoted_total_amount) - $repaid);
        }

        // Native penalties only. Reviewed legacy penalties must be linked to
        // the native subledger first; unlinked evidence is not a second charge.
        $loanPenaltyDue = 0;
        $lp = DB::table('loan_penalties as p')
            ->join('loans as l', 'l.id', '=', 'p.loan_id')
            ->where('p.society_id', $societyId)->where('l.society_id', $societyId)
            ->where('l.cycle_id', $cycleId)->where('l.society_membership_id', $membershipId)
            ->whereDate('p.assessed_on', '<=', $end)
            ->get(['p.id','p.assessed_amount']);
        foreach ($lp as $p) {
            $paid = $this->cents(DB::table('loan_penalty_payments')
                ->where('society_id', $societyId)->where('loan_penalty_id', $p->id)
                ->whereDate('paid_on', '<=', $end)->get(['amount']));
            $waived = $this->cents(DB::table('loan_penalty_waivers')
                ->where('society_id', $societyId)->where('loan_penalty_id', $p->id)
                ->whereDate('created_at', '<=', $end)->get(['amount']));
            $loanPenaltyDue += max(0, Money::toCents((string) $p->assessed_amount) - $paid - $waived);
        }

        $contributionPenaltyDue = 0;
        $cp = DB::table('contribution_penalties as p')
            ->join('contribution_obligations as o', 'o.id', '=', 'p.contribution_obligation_id')
            ->where('p.society_id', $societyId)->where('o.society_id', $societyId)
            ->where('p.cycle_id', $cycleId)->where('o.society_membership_id', $membershipId)
            ->whereDate('p.assessed_on', '<=', $end)
            ->get(['p.id','p.assessed_amount']);
        foreach ($cp as $p) {
            $paid = $this->cents(DB::table('contribution_penalty_payments')
                ->where('society_id', $societyId)->where('contribution_penalty_id', $p->id)
                ->whereDate('paid_on', '<=', $end)->get(['amount']));
            $waived = $this->cents(DB::table('contribution_penalty_waivers')
                ->where('society_id', $societyId)->where('contribution_penalty_id', $p->id)
                ->whereDate('created_at', '<=', $end)->get(['amount']));
            $contributionPenaltyDue += max(0, Money::toCents((string) $p->assessed_amount) - $paid - $waived);
        }

        $totalDebt = $loanDue + $loanPenaltyDue + $contributionPenaltyDue;
        $gross = $savings + $interest;

        return [
            'savings_cents' => $savings,
            'interest_cents' => $interest,
            'loan_due_cents' => $loanDue,
            'loan_penalty_due_cents' => $loanPenaltyDue,
            'contribution_penalty_due_cents' => $contributionPenaltyDue,
            'total_debt_cents' => $totalDebt,
            'indicative_gross_cents' => $gross,
            'illustrative_after_debts_cents' => max(0, $gross - $totalDebt),
            'debt_shortfall_cents' => max(0, $totalDebt - $gross),
        ];
    }

    /** Financial ledger balances as posted on/before month-end, not today. */
    public function ledger(int $societyId, string $end): array
    {
        $accounts = ['CASH_ON_HAND','BANK_ACCOUNT','MOBILE_MONEY','LOAN_PRINCIPAL_RECEIVABLE',
            'MEMBER_SAVINGS','MEMBER_INTEREST_PAYABLE','LEGACY_IMPORT_CLEARING'];
        $result = array_fill_keys($accounts, 0);
        $rows = DB::table('ledger_lines as ll')
            ->join('ledger_transactions as t', 't.id', '=', 'll.ledger_transaction_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'll.ledger_account_id')
            ->where('t.society_id', $societyId)->where('a.society_id', $societyId)
            ->whereDate('t.posting_date', '<=', $end)->whereIn('a.code', $accounts)
            ->get(['a.code','ll.debit_amount','ll.credit_amount']);
        foreach ($rows as $line) {
            $net = Money::toCents((string) $line->debit_amount)
                - Money::toCents((string) $line->credit_amount);
            $result[$line->code] += in_array($line->code,
                ['MEMBER_SAVINGS','MEMBER_INTEREST_PAYABLE'], true) ? -$net : $net;
        }
        $result['liquid_assets'] = $result['CASH_ON_HAND'] + $result['BANK_ACCOUNT']
            + $result['MOBILE_MONEY'];

        // Member-facing historical bank balance. This NEVER mutates the ledger:
        // the raw BANK_ACCOUNT, liquid_assets and LEGACY_IMPORT_CLEARING values
        // above are preserved for accounting and audit consistency.
        $bankHistory = (new HistoricalBankBalanceForReport())
            ->resolve($societyId, $end, $result);
        $result['bank_for_report_cents'] = $bankHistory['amount_cents'];
        $result['bank_for_report_status'] = $bankHistory['status'];
        $result['bank_for_report_note'] = $bankHistory['note'];
        $result['money_held_for_report_cents'] = $bankHistory['amount_cents']
            + $result['CASH_ON_HAND'] + $result['MOBILE_MONEY'];

        return $result;
    }
}
