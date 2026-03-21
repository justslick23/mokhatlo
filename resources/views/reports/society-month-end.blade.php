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

        /* ── Header band ───────────────────────────────────── */
        .header {
            background: #0f2d5e;
            padding: 0;
            margin-bottom: 0;
        }
        .header-inner {
            padding: 28px 36px 24px;
        }
        .header-accent {
            height: 5px;
            background: #e8a020;
            /* Gold accent stripe */
        }
        .org-label {
            font-size: 9px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #7fa8d4;
            margin-bottom: 6px;
        }
        .org-name {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: -0.3px;
            margin-bottom: 3px;
        }
        .report-title {
            font-size: 12px;
            color: #a8c8e8;
            letter-spacing: 0.5px;
        }
        .header-meta {
            margin-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.12);
            padding-top: 14px;
        }
        .header-meta table { width: 100%; }
        .header-meta td {
            color: #7fa8d4;
            font-size: 10px;
            padding: 0;
            border: none;
            background: none;
        }
        .header-meta .meta-value {
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
        }

        /* ── Page body ─────────────────────────────────────── */
        .page-body {
            padding: 28px 36px;
        }

        /* ── Section title ─────────────────────────────────── */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #0f2d5e;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e8a020;
        }

        /* ── KPI grid (4 cards per row using table) ────────── */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 24px;
            margin-left: -8px;
            margin-right: -8px;
        }
        .kpi-cell {
            width: 25%;
            vertical-align: top;
        }
        .kpi-card {
            background: #f4f7fc;
            border: 1px solid #dde5f0;
            border-radius: 5px;
            padding: 12px 14px;
            border-left: 3px solid #0f2d5e;
        }
        .kpi-card.gold   { border-left-color: #e8a020; background: #fffbf0; border-color: #f0d890; }
        .kpi-card.green  { border-left-color: #1a7c5a; background: #f0faf5; border-color: #b8dece; }
        .kpi-card.red    { border-left-color: #c0392b; background: #fdf4f3; border-color: #f0c8c5; }
        .kpi-label {
            font-size: 8.5px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #6b7a99;
            margin-bottom: 5px;
        }
        .kpi-value {
            font-size: 17px;
            font-weight: bold;
            color: #0f2d5e;
            line-height: 1;
        }
        .kpi-card.gold  .kpi-value { color: #8a5a00; }
        .kpi-card.green .kpi-value { color: #1a7c5a; }
        .kpi-card.red   .kpi-value { color: #c0392b; }
        .kpi-sub {
            font-size: 8px;
            color: #9aa5be;
            margin-top: 3px;
        }

        /* ── Summary rows ──────────────────────────────────── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid #dde5f0;
            border-radius: 5px;
        }
        .summary-table tr { border-bottom: 1px solid #edf0f7; }
        .summary-table tr:last-child { border-bottom: none; }
        .summary-table td {
            padding: 9px 14px;
            font-size: 11px;
            border: none;
        }
        .summary-table tr:nth-child(even) td { background: #f9fafc; }
        .s-label { color: #6b7a99; }
        .s-value { font-weight: bold; color: #1c2333; text-align: right; }
        .s-value.positive { color: #1a7c5a; }
        .s-value.negative { color: #c0392b; }
        .s-value.neutral  { color: #0f2d5e; }

        /* ── Data table ────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 10.5px;
        }
        .data-table thead tr {
            background: #0f2d5e;
        }
        .data-table thead th {
            color: #ffffff;
            padding: 9px 12px;
            text-align: left;
            font-size: 9px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            font-weight: bold;
            border: none;
        }
        .data-table thead th:last-child { text-align: right; }
        .data-table tbody tr { border-bottom: 1px solid #edf0f7; }
        .data-table tbody tr:nth-child(even) td { background: #f9fafc; }
        .data-table tbody td {
            padding: 8px 12px;
            color: #1c2333;
            border: none;
            vertical-align: middle;
        }
        .data-table tbody td:last-child { text-align: right; }
        .data-table tfoot td {
            padding: 9px 12px;
            font-weight: bold;
            border-top: 2px solid #0f2d5e;
            color: #0f2d5e;
            background: #f0f4fc;
            border-bottom: none;
        }
        .data-table tfoot td:last-child { text-align: right; }

        /* ── Status badges ─────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-overdue  { background: #fde8e7; color: #c0392b; }
        .badge-active   { background: #e6f5ee; color: #1a7c5a; }
        .badge-none     { background: #edf0f7; color: #6b7a99; }
        .badge-repaid   { background: #e8f4fd; color: #1a5a8a; }

        /* ── Divider ───────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #edf0f7;
            margin: 20px 0;
        }

        /* ── Spacer ────────────────────────────────────────── */
        .spacer { height: 20px; }

        /* ── Footer ────────────────────────────────────────── */
        .footer-band {
            background: #f4f7fc;
            border-top: 3px solid #e8a020;
            padding: 14px 36px;
            margin-top: 32px;
        }
        .footer-table { width: 100%; }
        .footer-table td {
            font-size: 9px;
            color: #9aa5be;
            padding: 0;
            border: none;
            background: none;
        }
        .footer-table .footer-right { text-align: right; }
        .confidential {
            font-size: 8.5px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #c0392b;
            font-weight: bold;
        }

        /* ── Watermark-style page label ────────────────────── */
        .page-label {
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #b0b8cc;
            text-align: right;
            margin-bottom: 18px;
        }

        /* ── Alert box ─────────────────────────────────────── */
        .alert-box {
            background: #fff8e6;
            border: 1px solid #f0d060;
            border-left: 4px solid #e8a020;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 10.5px;
            color: #7a5800;
        }
        .alert-box.info {
            background: #eef4fd;
            border-color: #b8cef0;
            border-left-color: #0f2d5e;
            color: #1c2333;
        }
    </style>
</head>
<body>

{{-- ══ HEADER ══════════════════════════════════════════════════ --}}
<div class="header-accent"></div>
<div class="header">
    <div class="header-inner">
        <div class="org-label">Financial Report</div>
        <div class="org-name">{{ $society->name }}</div>
        <div class="report-title">Month-End Financial Statement &mdash; {{ $period->format('F Y') }}</div>
        <div class="header-meta">
            <table>
                <tr>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Cycle</div>
                        <div class="meta-value">{{ $cycle->name }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Cycle Period</div>
                        <div class="meta-value">{{ $cycle->start_date->format('d M Y') }} &ndash; {{ $cycle->end_date->format('d M Y') }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Report Generated</div>
                        <div class="meta-value">{{ now()->format('d M Y, H:i') }}</div>
                    </td>
                    <td>
                        <div style="color:#7fa8d4;font-size:9px;">Total Members</div>
                        <div class="meta-value">{{ $summary['total_members'] }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="header-accent"></div>

{{-- ══ PAGE BODY ═══════════════════════════════════════════════ --}}
<div class="page-body">

    <div class="page-label">{{ $society->name }} &nbsp;&bull;&nbsp; {{ $period->format('F Y') }} &nbsp;&bull;&nbsp; Confidential</div>

    {{-- ── ALERT: defaulters ─────────────────────────────────── --}}
    @if ($summary['defaulters_count'] > 0)
    <div class="alert-box">
        &#9888;&nbsp; <strong>{{ $summary['defaulters_count'] }} member(s)</strong> did not make a contribution this month.
        Penalty proceedings may apply per the society's rules.
    </div>
    @endif

    {{-- ══ KPI CARDS ════════════════════════════════════════════ --}}
    <div class="section-title">Monthly Snapshot</div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card green">
                    <div class="kpi-label">Contributions</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['total_contributions'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-label">Repayments</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['total_repayments'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card red">
                    <div class="kpi-label">Disbursed</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['total_disbursed'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card gold">
                    <div class="kpi-label">Penalties</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['total_penalties'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-label">Pool Balance</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['pool_balance'], 2) }}</div>
                    <div class="kpi-sub">Cycle to date</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card green">
                    <div class="kpi-label">Available Balance</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['available_balance'], 2) }}</div>
                    <div class="kpi-sub">Available now</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card red">
                    <div class="kpi-label">Outstanding Loans</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($summary['total_outstanding'], 2) }}</div>
                    <div class="kpi-sub">{{ $summary['active_loans_count'] }} active loan(s)</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card {{ $summary['defaulters_count'] > 0 ? 'gold' : 'green' }}">
                    <div class="kpi-label">Defaulters</div>
                    <div class="kpi-value">{{ $summary['defaulters_count'] }}</div>
                    <div class="kpi-sub">of {{ $summary['total_members'] }} members</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    {{-- ══ MEMBER BREAKDOWN ═════════════════════════════════════ --}}
    <div class="section-title">Member-by-Member Breakdown</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Member Name</th>
                <th>Contributed</th>
                <th>Penalties</th>
                <th>Loan Status</th>
                <th>Outstanding Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['member_breakdown'] as $i => $row)
            <tr>
                <td style="color:#9aa5be;font-size:10px;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td style="font-weight:600;">{{ $row['member']->user->name }}</td>
                <td style="color:{{ $row['contributed'] > 0 ? '#1a7c5a' : '#c0392b' }};font-weight:bold;">
                    M&nbsp;{{ number_format($row['contributed'], 2) }}
                </td>
                <td style="color:{{ $row['penalties'] > 0 ? '#c0392b' : '#6b7a99' }};">
                    M&nbsp;{{ number_format($row['penalties'], 2) }}
                </td>
                <td>
                    @if ($row['loan_status'] === 'overdue')
                        <span class="badge badge-overdue">Overdue</span>
                    @elseif ($row['loan_status'] === 'active')
                        <span class="badge badge-active">Active</span>
                    @else
                        <span class="badge badge-none">None</span>
                    @endif
                </td>
                <td style="font-weight:bold;text-align:right;color:{{ $row['outstanding_balance'] > 0 ? '#c0392b' : '#6b7a99' }};">
                    M&nbsp;{{ number_format($row['outstanding_balance'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Totals</td>
                <td>M&nbsp;{{ number_format($summary['member_breakdown']->sum('contributed'), 2) }}</td>
                <td>M&nbsp;{{ number_format($summary['member_breakdown']->sum('penalties'), 2) }}</td>
                <td></td>
                <td>M&nbsp;{{ number_format($summary['member_breakdown']->sum('outstanding_balance'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- ══ ACTIVE LOANS DETAIL ══════════════════════════════════ --}}
    @if ($summary['active_loans']->isNotEmpty())
    <div class="section-title">Active &amp; Overdue Loans</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Member</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Total</th>
                <th>Repaid</th>
                <th>Outstanding</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['active_loans'] as $loan)
            <tr>
                <td style="font-weight:600;">{{ $loan->member->user->name }}</td>
                <td>M&nbsp;{{ number_format($loan->principal, 2) }}</td>
                <td>M&nbsp;{{ number_format($loan->interest, 2) }}</td>
                <td>M&nbsp;{{ number_format($loan->total_amount, 2) }}</td>
                <td style="color:#1a7c5a;">M&nbsp;{{ number_format($loan->amount_repaid, 2) }}</td>
                <td style="font-weight:bold;color:#c0392b;">M&nbsp;{{ number_format($loan->outstanding_balance, 2) }}</td>
                <td style="color:{{ $loan->isDue() ? '#c0392b' : '#1c2333' }};">
                    {{ $loan->due_date->format('d M Y') }}
                </td>
                <td>
                    @if ($loan->status === 'overdue')
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
                <td colspan="4">Total Outstanding</td>
                <td>M&nbsp;{{ number_format($summary['active_loans']->sum('amount_repaid'), 2) }}</td>
                <td>M&nbsp;{{ number_format($summary['total_outstanding'], 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    @endif

</div>

{{-- ══ FOOTER ═══════════════════════════════════════════════════ --}}
<div class="footer-band">
    <table class="footer-table">
        <tr>
            <td>
                <strong style="color:#0f2d5e;">{{ $society->name }}</strong><br>
                Month-End Report &mdash; {{ $period->format('F Y') }}
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