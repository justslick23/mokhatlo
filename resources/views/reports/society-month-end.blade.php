{{-- resources/views/reports/society-month-end.blade.php --}}
{{-- FIXED: All variables properly bound with null-safety and proper iteration --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1c2333;
            background: #ffffff;
            line-height: 1.5;
        }

        .header { background: #0f2d5e; padding: 0; margin-bottom: 0; }
        .header-inner { padding: 28px 36px 24px; }
        .header-accent { height: 5px; background: #e8a020; }
        .org-label { font-size: 9px; letter-spacing: 2.5px; text-transform: uppercase; color: #7fa8d4; margin-bottom: 6px; }
        .org-name { font-size: 22px; font-weight: bold; color: #ffffff; letter-spacing: -0.3px; margin-bottom: 3px; }
        .report-title { font-size: 12px; color: #a8c8e8; letter-spacing: 0.5px; }
        .header-meta { margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.12); padding-top: 14px; }
        .header-meta table { width: 100%; }
        .header-meta td { color: #7fa8d4; font-size: 10px; padding: 0; border: none; background: none; }
        .header-meta .meta-value { color: #ffffff; font-weight: bold; font-size: 11px; }

        .page-body { padding: 28px 36px; }

        .section-title {
            font-size: 9px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;
            color: #0f2d5e; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #e8a020;
        }

        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 24px; margin-left: -8px; margin-right: -8px; }
        .kpi-cell { width: 25%; vertical-align: top; }
        .kpi-card { background: #f4f7fc; border: 1px solid #dde5f0; border-radius: 5px; padding: 12px 14px; border-left: 3px solid #0f2d5e; }
        .kpi-card.gold   { border-left-color: #e8a020; background: #fffbf0; border-color: #f0d890; }
        .kpi-card.green  { border-left-color: #1a7c5a; background: #f0faf5; border-color: #b8dece; }
        .kpi-card.red    { border-left-color: #c0392b; background: #fdf4f3; border-color: #f0c8c5; }
        .kpi-label { font-size: 8.5px; letter-spacing: 0.8px; text-transform: uppercase; color: #6b7a99; margin-bottom: 5px; }
        .kpi-value { font-size: 17px; font-weight: bold; color: #0f2d5e; line-height: 1; }
        .kpi-card.gold  .kpi-value { color: #8a5a00; }
        .kpi-card.green .kpi-value { color: #1a7c5a; }
        .kpi-card.red   .kpi-value { color: #c0392b; }
        .kpi-sub { font-size: 8px; color: #9aa5be; margin-top: 3px; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 10.5px; }
        .data-table thead tr { background: #0f2d5e; }
        .data-table thead th {
            color: #ffffff; padding: 9px 12px; text-align: left;
            font-size: 9px; letter-spacing: 0.8px; text-transform: uppercase; font-weight: bold; border: none;
        }
        .data-table thead th:last-child { text-align: right; }
        .data-table tbody tr { border-bottom: 1px solid #edf0f7; }
        .data-table tbody tr:nth-child(even) td { background: #f9fafc; }
        .data-table tbody td { padding: 8px 12px; color: #1c2333; border: none; vertical-align: middle; }
        .data-table tbody td:last-child { text-align: right; }
        .data-table tfoot td {
            padding: 9px 12px; font-weight: bold; border-top: 2px solid #0f2d5e;
            color: #0f2d5e; background: #f0f4fc; border-bottom: none;
        }
        .data-table tfoot td:last-child { text-align: right; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .badge-overdue  { background: #fde8e7; color: #c0392b; }
        .badge-active   { background: #e6f5ee; color: #1a7c5a; }
        .badge-none     { background: #edf0f7; color: #6b7a99; }
        .badge-repaid   { background: #e8f4fd; color: #1a5a8a; }

        .spacer { height: 20px; }

        .alert-box {
            background: #fff8e6; border: 1px solid #f0d060; border-left: 4px solid #e8a020;
            border-radius: 4px; padding: 10px 14px; margin-bottom: 20px; font-size: 10.5px; color: #7a5800;
        }

        .page-label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #b0b8cc; text-align: right; margin-bottom: 18px; }

        .footer-band { background: #f4f7fc; border-top: 3px solid #e8a020; padding: 14px 36px; margin-top: 32px; }
        .footer-table { width: 100%; }
        .footer-table td { font-size: 9px; color: #9aa5be; padding: 0; border: none; background: none; }
        .footer-table .footer-right { text-align: right; }
        .confidential { font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #c0392b; font-weight: bold; }

        .summary-strip {
            background: #0f2d5e; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px;
        }
        .summary-strip table { width: 100%; }
        .summary-strip td { padding: 5px 12px; border: none; background: none; }
        .summary-strip .s-label { color: #7fa8d4; font-size: 9.5px; }
        .summary-strip .s-value { color: #ffffff; font-weight: bold; font-size: 13px; text-align: right; }
        .summary-strip .s-value.gold { color: #e8a020; }
        .summary-strip .s-value.green { color: #4ade80; }

        .empty-message {
            text-align: center;
            padding: 20px;
            color: #9aa5be;
            font-style: italic;
            background: #f9fafc;
        }
    </style>
</head>
<body>

{{-- ══ HEADER ══════════════════════════════════════════════════ --}}
<div class="header-accent"></div>
<div class="header">
    <div class="header-inner">
        <div class="org-label">Financial Report</div>
        <div class="org-name">{{ $society->name ?? 'Society' }}</div>
        <div class="report-title">Month-End Financial Statement &mdash; {{ $period->format('F Y') ?? 'Report' }}</div>
        <div class="header-meta">
            <table>
                <tr>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Cycle</div>
                        <div class="meta-value">{{ $cycle->name ?? 'N/A' }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Cycle Period</div>
                        <div class="meta-value">{{ $cycle->start_date->format('d M Y') ?? 'N/A' }} &ndash; {{ $cycle->end_date->format('d M Y') ?? 'N/A' }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Report Generated</div>
                        <div class="meta-value">{{ now()->format('d M Y, H:i') }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Total Members</div>
                        <div class="meta-value">{{ $summary['total_members'] ?? 0 }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="header-accent"></div>

{{-- ══ PAGE BODY ═══════════════════════════════════════════════ --}}
<div class="page-body">

    <div class="page-label">{{ $society->name ?? 'Society' }} &nbsp;&bull;&nbsp; {{ $period->format('F Y') ?? 'Report' }} &nbsp;&bull;&nbsp; Confidential</div>

    {{-- ── ALERT: defaulters ─────────────────────────────────── --}}
    @php
        $defaultersCount = $summary['defaulters_count'] ?? 0;
    @endphp
    
    @if ($defaultersCount > 0)
    <div class="alert-box">
        &#9888;&nbsp; <strong>{{ $defaultersCount }} member(s)</strong> did not make a contribution this month.
        Penalty proceedings may apply per the society's rules.
    </div>
    @endif

    {{-- ══ KPI CARDS — THIS MONTH ═══════════════════════════════ --}}
    <div class="section-title">Monthly Snapshot</div>

    @php
        $totalContributions = $summary['total_contributions'] ?? 0;
        $totalRepayments = $summary['total_repayments'] ?? 0;
        $totalDisbursed = $summary['total_disbursed'] ?? 0;
        $totalPenalties = $summary['total_penalties'] ?? 0;
        $totalInterestCollected = $summary['total_interest_collected'] ?? 0;
        $poolBalance = $summary['pool_balance'] ?? 0;
        $availableBalance = $summary['available_balance'] ?? 0;
        $totalOutstanding = $summary['total_outstanding'] ?? 0;
        $activeLoanCount = $summary['active_loans_count'] ?? 0;
    @endphp

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card green">
                    <div class="kpi-label">Contributions</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalContributions, 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-label">Repayments</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalRepayments, 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card red">
                    <div class="kpi-label">Disbursed</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalDisbursed, 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card gold">
                    <div class="kpi-label">Penalties</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalPenalties, 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card gold">
                    <div class="kpi-label">Interest Collected</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalInterestCollected, 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-label">Pool Balance</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($poolBalance, 2) }}</div>
                    <div class="kpi-sub">Cycle to date</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card green">
                    <div class="kpi-label">Available Balance</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($availableBalance, 2) }}</div>
                    <div class="kpi-sub">Available now</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card red">
                    <div class="kpi-label">Outstanding Loans</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($totalOutstanding, 2) }}</div>
                    <div class="kpi-sub">{{ $activeLoanCount }} active loan(s)</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══ CYCLE-TO-DATE SUMMARY STRIP ══════════════════════════ --}}
    <div class="section-title">Cycle-to-Date Income Summary</div>

    @php
        $cycleContributions = $summary['cycle_contributions'] ?? 0;
        $cycleInterestCollected = $summary['cycle_interest_collected'] ?? 0;
        $cyclePenalties = $summary['cycle_penalties'] ?? 0;
    @endphp

    <div class="summary-strip">
        <table>
            <tr>
                <td class="s-label">Total Contributions</td>
                <td class="s-value">M&nbsp;{{ number_format($cycleContributions, 2) }}</td>
                <td class="s-label">Interest Collected (Cycle)</td>
                <td class="s-value gold">M&nbsp;{{ number_format($cycleInterestCollected, 2) }}</td>
            </tr>
            <tr>
                <td class="s-label">Penalties Collected (Cycle)</td>
                <td class="s-value gold">M&nbsp;{{ number_format($cyclePenalties, 2) }}</td>
                <td class="s-label">Available Balance</td>
                <td class="s-value green">M&nbsp;{{ number_format($availableBalance, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="spacer"></div>

    {{-- ══ MEMBER BREAKDOWN ═════════════════════════════════════ --}}
    <div class="section-title">Member-by-Member Breakdown</div>

    @php
        $memberBreakdown = $summary['member_breakdown'] ?? collect();
    @endphp

    @if($memberBreakdown->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Member Name</th>
                <th>Contributed</th>
                <th>Penalties</th>
                <th>Interest Paid</th>
                <th>Loan Status</th>
                <th>Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($memberBreakdown as $i => $row)
            <tr>
                <td style="color:#9aa5be;font-size:10px;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td style="font-weight:600;">{{ $row['member']->user->name ?? 'N/A' }}</td>
                <td style="color:{{ ($row['contributed'] ?? 0) > 0 ? '#1a7c5a' : '#c0392b' }};font-weight:bold;">
                    M&nbsp;{{ number_format($row['contributed'] ?? 0, 2) }}
                </td>
                <td style="color:{{ ($row['penalties'] ?? 0) > 0 ? '#c0392b' : '#6b7a99' }};">
                    M&nbsp;{{ number_format($row['penalties'] ?? 0, 2) }}
                </td>
                <td style="color:{{ ($row['interest_paid'] ?? 0) > 0 ? '#8a5a00' : '#6b7a99' }};">
                    M&nbsp;{{ number_format($row['interest_paid'] ?? 0, 2) }}
                </td>
                <td>
                    @php $loanStatus = $row['loan_status'] ?? 'none'; @endphp
                    @if ($loanStatus === 'overdue')
                        <span class="badge badge-overdue">Overdue</span>
                    @elseif ($loanStatus === 'active')
                        <span class="badge badge-active">Active</span>
                    @else
                        <span class="badge badge-none">None</span>
                    @endif
                </td>
                <td style="font-weight:bold;text-align:right;color:{{ ($row['outstanding_balance'] ?? 0) > 0 ? '#c0392b' : '#6b7a99' }};">
                    M&nbsp;{{ number_format($row['outstanding_balance'] ?? 0, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Totals</td>
                <td>M&nbsp;{{ number_format($memberBreakdown->sum('contributed') ?? 0, 2) }}</td>
                <td>M&nbsp;{{ number_format($memberBreakdown->sum('penalties') ?? 0, 2) }}</td>
                <td>M&nbsp;{{ number_format($memberBreakdown->sum('interest_paid') ?? 0, 2) }}</td>
                <td></td>
                <td>M&nbsp;{{ number_format($memberBreakdown->sum('outstanding_balance') ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <div class="empty-message">No member data available.</div>
    @endif

    {{-- ══ ACTIVE LOANS DETAIL ══════════════════════════════════ --}}
    @php
        $activeLoans = $summary['active_loans'] ?? collect();
    @endphp

    @if ($activeLoans->count() > 0)
    <div class="section-title">Active &amp; Overdue Loans</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Member</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Interest Paid</th>
                <th>Repaid</th>
                <th>Outstanding</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activeLoans as $loan)
            <tr>
                <td style="font-weight:600;">{{ $loan->member->user->name ?? 'N/A' }}</td>
                <td>M&nbsp;{{ number_format($loan->principal ?? 0, 2) }}</td>
                <td>M&nbsp;{{ number_format($loan->interest ?? 0, 2) }}</td>
                <td style="color:#8a5a00;">M&nbsp;{{ number_format($loan->interest_paid ?? 0, 2) }}</td>
                <td style="color:#1a7c5a;">M&nbsp;{{ number_format($loan->amount_repaid ?? 0, 2) }}</td>
                <td style="font-weight:bold;color:#c0392b;">M&nbsp;{{ number_format($loan->outstanding_balance ?? 0, 2) }}</td>
                <td style="color:{{ ($loan->isDue() ?? false) ? '#c0392b' : '#1c2333' }};">
                    {{ $loan->due_date->format('d M Y') ?? 'N/A' }}
                </td>
                <td>
                    @php $status = $loan->status ?? 'active'; @endphp
                    @if ($status === 'overdue')
                        <span class="badge badge-overdue">Overdue</span>
                    @else
                        <span class="badge badge-active">Active</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Totals</td>
                <td></td>
                <td>M&nbsp;{{ number_format($activeLoans->sum('interest_paid') ?? 0, 2) }}</td>
                <td>M&nbsp;{{ number_format($activeLoans->sum('amount_repaid') ?? 0, 2) }}</td>
                <td>M&nbsp;{{ number_format($totalOutstanding, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    @else
    <div class="section-title">Active Loans</div>
    <div class="empty-message">No active or overdue loans.</div>
    @endif

</div>

{{-- ══ FOOTER ═══════════════════════════════════════════════════ --}}
<div class="footer-band">
    <table class="footer-table">
        <tr>
            <td>
                <strong style="color:#0f2d5e;">{{ $society->name ?? 'Society' }}</strong><br>
                Month-End Report &mdash; {{ $period->format('F Y') ?? 'Report' }}
            </td>
            <td class="footer-right">
                <span class="confidential">Confidential</span><br>
                Generated {{ now()->format('d M Y \a\t H:i') }}
            </td>
        </tr>
    </table>
</div>

</body>
</html>