<?php

namespace App\Http\Controllers;

use App\Models\SocietyMembership;
use App\Services\Reports\HistoricalContributionSummary;
use App\Services\Reports\MemberFinancialStatement;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberStatementController extends Controller
{
    private function resolveMembership(Request $request): SocietyMembership
    {
        $society = $request->attributes->get('currentSociety');
        $canViewAll = $request->user()->can('reports.view');
        abort_unless(
            $canViewAll || $request->user()->can('reports.view_own'),
            403
        );

        $currentMembership = $request->attributes->get('currentMembership');
        $requestedId = $request->validate([
            'membership_id' => ['nullable', 'integer'],
        ])['membership_id'] ?? null;

        if (! $canViewAll) {
            abort_if(
                $requestedId !== null
                    && (int) $requestedId !== $currentMembership->id,
                403
            );
            return $society->memberships()
                ->with('user')
                ->findOrFail($currentMembership->id);
        }

        return $society->memberships()
            ->with('user')
            ->findOrFail($requestedId ?: $currentMembership->id);
    }

    public function show(
        Request $request,
        MemberFinancialStatement $service,
        HistoricalContributionSummary $historical
    ): View {
        $society = $request->attributes->get('currentSociety');
        $membership = $this->resolveMembership($request);
        $canViewAll = $request->user()->can('reports.view');

        $historicalCompliance = $historical->forMember(
            (int) $society->id,
            (int) $membership->id
        );

        return view('reports.member-statement', [
            'society' => $society,
            'membership' => $membership,
            'memberships' => $canViewAll
                ? $society->memberships()
                    ->with('user')
                    ->orderBy('member_number')
                    ->get()
                : collect(),
            'rows' => $service->build($society, $membership),
            'canViewAll' => $canViewAll,
            'historicalCompliance' => $historicalCompliance,
        ]);
    }

    public function export(
        Request $request,
        MemberFinancialStatement $service
    ): StreamedResponse {
        $society = $request->attributes->get('currentSociety');
        $membership = $this->resolveMembership($request);
        $rows = $service->build($society, $membership);
        $filename = 'mokhatlo-member-statement-'
            . $society->id . '-' . $membership->id . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date', 'Category', 'Description', 'Direction',
                'Amount (LSL)', 'Reference',
            ]);
            foreach ($rows as $row) {
                $safe = static function (?string $value): string {
                    $value = $value ?? '';
                    return preg_match('/^[\s]*[=+\-@]/u', $value)
                        ? "'" . $value : $value;
                };
                fputcsv($handle, [
                    $row['date'],
                    $safe($row['category']),
                    $safe($row['description']),
                    $row['direction'],
                    Money::fromCents($row['amount_cents']),
                    $safe($row['reference']),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
