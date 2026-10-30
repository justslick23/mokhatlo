<?php

namespace App\Http\Controllers;

use App\Services\Reports\FinancialDashboardInsights;
use App\Services\Reports\HistoricalContributionSummary;
use App\Services\Reports\MemberEarningsPreview;
use App\Services\Reports\MemberPayoutPreview;
use App\Services\Reports\SocietyFinancialReport;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function index(
        Request $request,
        SocietyFinancialReport $financial,
        FinancialDashboardInsights $analytics,
        MemberEarningsPreview $earnings,
        MemberPayoutPreview $payouts,
        HistoricalContributionSummary $historical
    ): View {
        abort_unless($request->user()->can('reports.view'), 403);

        $society = $request->attributes->get('currentSociety');
        $months = $request->integer('months', 6);
        if (! in_array($months, [3, 6, 12], true)) {
            $months = 6;
        }

        // Keep current-cycle report metrics intact until their source
        // services are reviewed; show historical coverage separately.
        $report = $financial->build($society);
        $insights = $analytics->build($society, $months);
        // Imported draft history must never trigger a payout preview from a normal report.
        $previewSafe = $report['cycle'] && $report['cycle']->status === 'active'
            && ! $report['historical_cycle'];
        $profitPreview = $previewSafe
            ? $earnings->build($society, $report['cycle']->id)
            : null;
        $payoutPreview = $previewSafe
            ? $payouts->build($society, $report['cycle']->id)
            : null;

        $historicalCompliance = $historical->forSociety((int) $society->id);

        return view('reports.index', compact(
            'society', 'report', 'insights', 'profitPreview',
            'payoutPreview', 'months', 'historicalCompliance'
        ));
    }

    public function export(Request $request, SocietyFinancialReport $service): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);
        $society = $request->attributes->get('currentSociety');
        $report = $service->build($society);
        $filename = 'mokhatlo-financial-summary-'
            . $society->id . '-'
            . now('Africa/Maseru')->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Metric', 'Amount (LSL)']);
            $labels = [
                'liquid_assets' => 'Recorded Liquid Assets',
                'contributions_received' => 'Contributions Received',
                'contributions_required' => 'Scheduled Contribution Minimums',
                'contributions_outstanding' => 'Outstanding Contribution Minimums',
                'contributions_overdue' => 'Overdue Contributions',
                'loan_principal_outstanding' => 'Loan Principal Outstanding',
                'loan_interest_outstanding' => 'Contractual Loan Interest Outstanding',
                'loan_interest_collected' => 'Borrower Interest Refunds Collected',
                'loan_instalments_overdue' => 'Overdue Loan Contractual Balances',
                'loan_penalties_outstanding' => 'Outstanding Loan Penalties',
                'contribution_penalties_outstanding' => 'Outstanding Contribution Penalties',
            ];
            foreach ($labels as $key => $label) {
                fputcsv($handle, [$label, Money::fromCents($report[$key])]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
