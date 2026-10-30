<?php

namespace App\Http\Controllers;

use App\Models\ContributionPayment;
use App\Models\Loan;
use App\Models\LoanInterestCredit;
use App\Services\Reports\FinancialDashboardInsights;
use App\Services\Reports\HistoricalContributionSummary;
use App\Services\Reports\MemberEarningsPreview;
use App\Services\Reports\SocietyFinancialReport;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        SocietyFinancialReport $financial,
        FinancialDashboardInsights $analytics,
        MemberEarningsPreview $earnings,
        HistoricalContributionSummary $historical
    ): View {
        $society = $request->attributes->get('currentSociety');
        $membership = $request->attributes->get('currentMembership');
        $months = $request->integer('months', 6);
        if (! in_array($months, [3, 6, 12], true)) {
            $months = 6;
        }
        $canViewFinance = $request->user()->can('reports.view');

        if ($canViewFinance) {
            $report = $financial->build($society);
            $insights = $analytics->build($society, $months);
            $previewSafe = $report['cycle'] && $report['cycle']->status === 'active'
                && ! $report['historical_cycle'];
            $profitPreview = $previewSafe
                ? $earnings->build($society, $report['cycle']->id)
                : null;

            $historicalCompliance = $historical->forSociety((int) $society->id);

            return view('dashboard', compact(
                'society', 'membership', 'canViewFinance', 'report',
                'insights', 'profitPreview', 'months', 'historicalCompliance'
            ));
        }

        // Ordinary member dashboard: only their own financial data.
        $mySavingsCents = 0;
        $myInterestCents = 0;

        $contributions = ContributionPayment::query()
            ->where('society_id', $society->id)
            ->where('society_membership_id', $membership->id)
            ->get(['amount']);

        foreach ($contributions as $payment) {
            // Actual receipts already include historical savings.
            // Do NOT add legacy obligation links as extra cash receipts.
            $mySavingsCents += Money::toCents($payment->amount);
        }

        $credits = LoanInterestCredit::query()
            ->where('society_id', $society->id)
            ->where('beneficiary_membership_id', $membership->id)
            ->get(['amount']);

        foreach ($credits as $credit) {
            $myInterestCents += Money::toCents($credit->amount);
        }

        $myLoansCount = Loan::query()
            ->where('society_id', $society->id)
            ->where('society_membership_id', $membership->id)
            ->count();

        $historicalCompliance = $historical->forMember(
            (int) $society->id,
            (int) $membership->id
        );

        return view('dashboard', compact(
            'society', 'membership', 'canViewFinance', 'mySavingsCents',
            'myInterestCents', 'myLoansCount', 'historicalCompliance'
        ));
    }
}
