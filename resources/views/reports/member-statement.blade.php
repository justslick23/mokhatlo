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

        /* ── Header ────────────────────────────────────────── */
        .header-accent-top {
            height: 5px;
            background: #e8a020;
        }
        .header {
            background: #0f2d5e;
            padding: 28px 36px 24px;
        }
        .header-accent-bottom {
            height: 5px;
            background: #e8a020;
        }
        .header-table { width: 100%; }
        .header-table td {
            vertical-align: top;
            padding: 0;
            border: none;
            background: none;
        }
        .statement-badge {
            background: #e8a020;
            color: #fff;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .member-name {
            font-size: 21px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: -0.2px;
            line-height: 1.1;
            margin-bottom: 3px;
        }
        .society-name {
            font-size: 12px;
            color: #7fa8d4;
        }
        .header-right-cell { text-align: right; }
        .period-label {
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #7fa8d4;
            margin-bottom: 4px;
        }
        .period-value {
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
        }
        .cycle-value {
            font-size: 10px;
            color: #a8c8e8;
            margin-top: 3px;
        }

        /* ── Page body ─────────────────────────────────────── */
        .page-body { padding: 28px 36px; }

        .page-label {
            font-size: 8px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #b0b8cc;
            text-align: right;
            margin-bottom: 18px;
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

        /* ── KPI row ───────────────────────────────────────── */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 24px;
            margin-left: -8px;
        }
        .kpi-cell { width: 25%; vertical-align: top; }
        .kpi-card {
            background: #f4f7fc;
            border: 1px solid #dde5f0;
            border-radius: 5px;
            padding: 12px 14px;
            border-left: 3px solid #0f2d5e;
        }
        .kpi-card.green  { border-left-color: #1a7c5a; background: #f0faf5; border-color: #b8dece; }
        .kpi-card.red    { border-left-color: #c0392b; background: #fdf4f3; border-color: #f0c8c5; }
        .kpi-card.gold   { border-left-color: #e8a020; background: #fffbf0; border-color: #f0d890; }
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
        .kpi-card.green .kpi-value { color: #1a7c5a; }
        .kpi-card.red   .kpi-value { color: #c0392b; }
        .kpi-card.gold  .kpi-value { color: #8a5a00; }
        .kpi-sub { font-size: 8px; color: #9aa5be; margin-top: 3px; }

        /* ── Data table ────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 10.5px;
        }
        .data-table thead tr { background: #0f2d5e; }
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
        .data-table thead th.right { text-align: right; }
        .data-table tbody tr { border-bottom: 1px solid #edf0f7; }
        .data-table tbody tr:nth-child(even) td { background: #f9fafc; }
        .data-table tbody td {
            padding: 8px 12px;
            color: #1c2333;
            border: none;
            vertical-align: middle;
        }
        .data-table tbody td.right { text-align: right; }
        .data-table tfoot td {
            padding: 9px 12px;
            font-weight: bold;
            border-top: 2px solid #0f2d5e;
            color: #0f2d5e;
            background: #f0f4fc;
            border-bottom: none;
        }
        .data-table tfoot td.right { text-align: right; }

        .empty-row td {
            text-align: center;
            color: #9aa5be;
            padding: 16px;
            font-style: italic;
            background: #f9fafc;
        }

        /* ── Share box ─────────────────────────────────────── */
        .share-box {
            background: #0f2d5e;
            border-radius: 6px;
            padding: 20px 22px;
            margin-bottom: 24px;
        }
        .share-box-title {
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #7fa8d4;
            margin-bottom: 14px;
            font-weight: bold;
        }
        .share-row-table { width: 100%; }
        .share-row-table td {
            padding: 5px 0;
            border: none;
            background: none;
        }
        .share-row-label { color: #a8c8e8; font-size: 10.5px; }
        .share-row-value {
            text-align: right;
            font-weight: bold;
            color: #ffffff;
            font-size: 11px;
        }
        .share-row-value.large {
            font-size: 18px;
            color: #e8a020;
        }
        .share-divider {
            border-top: 1px solid rgba(255,255,255,0.12);
            margin: 8px 0;
        }

        /* ── Loan banner ───────────────────────────────────── */
        .loan-banner {
            border-radius: 5px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 10.5px;
            line-height: 1.6;
        }
        .loan-banner.warn {
            background: #fff8e6;
            border: 1px solid #f0d060;
            border-left: 4px solid #e8a020;
            color: #7a5800;
        }
        .loan-banner.ok {
            background: #f0faf5;
            border: 1px solid #b8dece;
            border-left: 4px solid #1a7c5a;
            color: #0a4a30;
        }

        /* ── Badges ────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .badge-overdue { background: #fde8e7; color: #c0392b; }
        .badge-active  { background: #e6f5ee; color: #1a7c5a; }
        .badge-repaid  { background: #e8f4fd; color: #1a5a8a; }

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
    </style>
</head>
<body>

{{-- ══ HEADER ══════════════════════════════════════════════════ --}}
<div class="header-accent-top"></div>
<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:65%;">
                <div class="statement-badge">Member Statement</div>
                <div class="member-name">{{ $member->user->name }}</div>
                <div class="society-name">{{ $society->name }}</div>
            </td>
            <td class="header-right-cell" style="width:35%;">
                <div class="period-label">Reporting Period</div>
                <div class="period-value">{{ $period->format('F Y') }}</div>
                <div class="cycle-value">Cycle: {{ $cycle->name }}</div>
                <div style="margin-top:8px;color:#7fa8d4;font-size:9px;">
                    {{ $cycle->start_date->format('d M Y') }} &ndash; {{ $cycle->end_date->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>
</div>
<div class="header-accent-bottom"></div>

{{-- ══ PAGE BODY ═══════════════════════════════════════════════ --}}
<div class="page-body">

    <div class="page-label">{{ $member->user->name }} &nbsp;&bull;&nbsp; {{ $society->name }} &nbsp;&bull;&nbsp; {{ $period->format('F Y') }} &nbsp;&bull;&nbsp; Confidential</div>

    {{-- ── Loan status banner ────────────────────────────────── --}}
    @if ($memberData['active_loan'])
        @php $loan = $memberData['active_loan']; @endphp
        <div class="loan-banner warn">
            <strong>&#9888;&nbsp; Active Loan:</strong>
            You have an outstanding balance of <strong>M&nbsp;{{ number_format($loan->outstanding_balance, 2) }}</strong>
            due on <strong>{{ $loan->due_date->format('d M Y') }}</strong>.
            Status: <span class="badge {{ $loan->status === 'overdue' ? 'badge-overdue' : 'badge-active' }}">{{ ucfirst($loan->status) }}</span>.
            Please ensure timely repayment to avoid additional penalties.
        </div>
    @else
        <div class="loan-banner ok">
            <strong>&#10003;&nbsp; No Outstanding Loans.</strong>
            You have no active loans this cycle. You are eligible to apply for a loan.
        </div>
    @endif

    {{-- ══ KPI SNAPSHOT ══════════════════════════════════════════ --}}
    <div class="section-title">Monthly Snapshot</div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-card {{ $memberData['total_contributed_month'] > 0 ? 'green' : 'red' }}">
                    <div class="kpi-label">Contributed</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($memberData['total_contributed_month'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card {{ $memberData['total_penalties_month'] > 0 ? 'red' : 'green' }}">
                    <div class="kpi-label">Penalties</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($memberData['total_penalties_month'], 2) }}</div>
                    <div class="kpi-sub">This month</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card">
                    <div class="kpi-label">Total Contributed</div>
                    <div class="kpi-value">M&nbsp;{{ number_format($memberData['total_contributed_cycle'], 2) }}</div>
                    <div class="kpi-sub">Cycle to date</div>
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-card gold">
                    <div class="kpi-label">Pool Share</div>
                    <div class="kpi-value">{{ $memberData['share_percent'] }}%</div>
                    <div class="kpi-sub">M&nbsp;{{ number_format($memberData['share_value'], 2) }} est. value</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    {{-- ══ CONTRIBUTIONS ════════════════════════════════════════ --}}
    <div class="section-title">Contributions This Month</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Notes</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($memberData['contributions'] as $i => $txn)
            <tr>
                <td style="color:#9aa5be;font-size:10px;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $txn->transaction_date->format('d M Y') }}</td>
                <td style="color:#6b7a99;">{{ $txn->notes ?? '—' }}</td>
                <td class="right" style="font-weight:bold;color:#1a7c5a;">M&nbsp;{{ number_format($txn->amount, 2) }}</td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="4">No contributions recorded this month.</td></tr>
            @endforelse
        </tbody>
        @if ($memberData['contributions']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="right">M&nbsp;{{ number_format($memberData['total_contributed_month'], 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- ══ PENALTIES ════════════════════════════════════════════ --}}
    <div class="section-title">Penalties This Month</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Notes</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($memberData['penalties'] as $i => $txn)
            <tr>
                <td style="color:#9aa5be;font-size:10px;">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $txn->transaction_date->format('d M Y') }}</td>
                <td style="color:#6b7a99;">{{ $txn->notes ?? '—' }}</td>
                <td class="right" style="font-weight:bold;color:#c0392b;">M&nbsp;{{ number_format($txn->amount, 2) }}</td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="4">No penalties this month.</td></tr>
            @endforelse
        </tbody>
        @if ($memberData['penalties']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="right">M&nbsp;{{ number_format($memberData['total_penalties_month'], 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- ══ LOANS ════════════════════════════════════════════════ --}}
    <div class="section-title">Loans This Cycle</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Principal</th>
                <th>Interest</th>
                <th>Total</th>
                <th>Repaid</th>
                <th class="right">Outstanding</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($memberData['loans'] as $loan)
            <tr>
                <td>M&nbsp;{{ number_format($loan->principal, 2) }}</td>
                <td>M&nbsp;{{ number_format($loan->interest, 2) }}</td>
                <td>M&nbsp;{{ number_format($loan->total_amount, 2) }}</td>
                <td style="color:#1a7c5a;">M&nbsp;{{ number_format($loan->amount_repaid, 2) }}</td>
                <td class="right" style="font-weight:bold;color:{{ $loan->outstanding_balance > 0 ? '#c0392b' : '#1a7c5a' }};">
                    M&nbsp;{{ number_format($loan->outstanding_balance, 2) }}
                </td>
                <td style="color:{{ $loan->isDue() ? '#c0392b' : '#1c2333' }};">
                    {{ $loan->due_date->format('d M Y') }}
                </td>
                <td>
                    @if ($loan->status === 'overdue')
                        <span class="badge badge-overdue">Overdue</span>
                    @elseif ($loan->status === 'repaid')
                        <span class="badge badge-repaid">Repaid</span>
                    @else
                        <span class="badge badge-active">Active</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="7">No loans recorded this cycle.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ══ SHARE IN SOCIETY ═════════════════════════════════════ --}}
    <div class="section-title">Your Share in the Society</div>

    <div class="share-box">
        <div class="share-box-title">Pool Participation Summary</div>
        <table class="share-row-table">
            <tr>
                <td class="share-row-label">Total Contributed (cycle to date)</td>
                <td class="share-row-value">M&nbsp;{{ number_format($memberData['total_contributed_cycle'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="2"><div class="share-divider"></div></td>
            </tr>
            <tr>
                <td class="share-row-label">Your Share of the Pool</td>
                <td class="share-row-value">{{ $memberData['share_percent'] }}%</td>
            </tr>
            <tr>
                <td colspan="2"><div class="share-divider"></div></td>
            </tr>
            <tr>
                <td class="share-row-label" style="font-size:12px;color:#ffffff;">Estimated Share Value</td>
                <td class="share-row-value large">M&nbsp;{{ number_format($memberData['share_value'], 2) }}</td>
            </tr>
        </table>
    </div>

</div>

{{-- ══ FOOTER ═══════════════════════════════════════════════════ --}}
<div class="footer-band">
    <table class="footer-table">
        <tr>
            <td>
                <strong style="color:#0f2d5e;">{{ $member->user->name }}</strong><br>
                {{ $society->name }} &mdash; Member Statement {{ $period->format('F Y') }}
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