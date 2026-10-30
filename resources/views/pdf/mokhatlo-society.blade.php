<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Society monthly update - {{ $report['period'] }}</title>
    <style>
        @page {
            margin: 35px 39px 47px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #252535;
            font-size: 8.5px;
            line-height: 1.45;
            margin: 0;
        }

        .top {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 19px;
        }

        .top td {
            padding: 0;
            vertical-align: top;
        }

        .logo {
            color: #574b9e;
            font-size: 19px;
            font-weight: bold;
        }

        .logo small {
            display: block;
            font-size: 8px;
            letter-spacing: 1.2px;
            color: #747584;
            font-weight: normal;
            margin-top: 3px;
        }

        .document {
            text-align: right;
        }

        .document h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.15;
        }

        .document .month {
            color: #574b9e;
            font-size: 11px;
            margin-top: 6px;
            font-weight: bold;
        }

        .rule {
            border-bottom: 2px solid #574b9e;
            margin-bottom: 17px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .meta td {
            padding: 0 8px 0 0;
            vertical-align: top;
        }

        .cap {
            color: #696a75;
            text-transform: uppercase;
            letter-spacing: .65px;
            font-size: 7.6px;
            font-weight: bold;
        }

        .val {
            font-size: 9px;
            font-weight: bold;
            margin-top: 4px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        .summary td {
            width: 33.3%;
            vertical-align: top;
            padding: 10px 8px 11px 0;
            border-top: 1px solid #c3c0d8;
            border-bottom: 1px solid #e3e4e8;
        }

        .summary .big {
            font-size: 15px;
            font-weight: bold;
            color: #292638;
            margin-top: 6px;
        }

        .section {
            margin: 19px 0 8px;
            padding-bottom: 7px;
            border-bottom: 1px solid #cdd0d8;
            font-size: 11.5px;
            font-weight: bold;
            page-break-after: avoid;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.1px;
            margin: 5px 0 9px;
        }

        .table thead {
            display: table-header-group;
        }

        .table th {
            padding: 7px 5px;
            text-align: left;
            border-top: 1px solid #a39fbb;
            border-bottom: 1px solid #a39fbb;
            color: #554b90;
            font-size: 7.8px;
        }

        .table td {
            padding: 7px 5px;
            border-bottom: 1px solid #e7e7ed;
            vertical-align: top;
        }

        .table tr {
            page-break-inside: avoid;
        }

        .table .amount {
            text-align: right;
            white-space: nowrap;
        }

        .table .total td {
            font-weight: bold;
            border-top: 1px solid #a39fbb;
        }

        .keep-together {
            page-break-inside: avoid;
        }

        .member-title {
            font-size: 9px;
            color: #554b90;
            font-weight: bold;
            border-bottom: 1px solid #dfdce9;
            margin: 13px 0 5px;
            padding-bottom: 5px;
            page-break-after: avoid;
        }

        .small {
            font-size: 7.5px;
            color: #696b77;
        }

        .note {
            margin: 13px 0 0;
            padding: 9px 11px;
            background: #f7f7fb;
            border-left: 2px solid #574b9e;
            color: #4f5061;
            font-size: 7.7px;
        }

        .footer {
            position: fixed;
            bottom: -31px;
            left: 0;
            right: 0;
            border-top: 1px solid #dddddf;
            padding-top: 7px;
            color: #777780;
            font-size: 7px;
        }

        .right {
            float: right;
        }
    </style>
</head>

<body>
    <table class="top">
        <tr>
            <td>
                <div class="logo">Mokhatlo.<small>SAVINGS SOCIETY</small></div>
            </td>
            <td class="document">
                <h1>Society monthly update</h1>
                <div class="month">{{ \Carbon\CarbonImmutable::parse($report['from'])->format('F Y') }}</div>
                <div class="small">Up to {{ \Carbon\CarbonImmutable::parse($report['to'])->format('d F Y') }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <table class="meta">
        <tr>
            <td style="width:43%">
                <div class="cap">Society</div>
                <div class="val">{{ $report['society'] }}</div>
            </td>
            <td style="width:31%">
                <div class="cap">Savings year</div>
                <div class="val">{{ $report['cycle'] }}</div>
            </td>
            <td>
                <div class="cap">Members</div>
                <div class="val">{{ $report['members_count'] }} active &nbsp;&middot;&nbsp; For members only</div>
            </td>
        </tr>
    </table>
    @if (($report['schema_version'] ?? 1) < 2)
        <div class="note">This is an older saved report. The full member-by-member details were not included when it
            was first prepared. Missing entries here do not necessarily mean that nothing happened.</div>
    @endif

    <table class="summary">
        <tr>
            <td>
                <div class="cap">Savings paid this month</div>
                <div class="big">M {{ number_format(($report['flows']['contributions_cents'] ?? 0) / 100, 2) }}</div>
            </td>
            <td>
                <div class="cap">Loan payments received</div>
                <div class="big">M {{ number_format(($report['flows']['loan_repayments_cents'] ?? 0) / 100, 2) }}
                </div>
            </td>
            <td>
                <div class="cap">Bank balance at month-end</div>
                <div class="big">M
                    {{ number_format(($report['position']['bank_for_report_cents'] ?? ($report['position']['BANK_ACCOUNT'] ?? 0)) / 100, 2) }}
                </div>
                <div class="small">
                    {{ ($report['position']['bank_for_report_status'] ?? '') === 'reconstructed' ? 'Calculated from recorded society transactions' : 'From bank entries recorded by month-end' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section">Money received and paid out this month</div>
    <table class="table">
        <tbody>
            <tr>
                <td>Members' savings paid in ({{ $report['flows']['contributions_count'] ?? 0 }} payments)</td>
                <td class="amount">M {{ number_format(($report['flows']['contributions_cents'] ?? 0) / 100, 2) }}</td>
            </tr>
            <tr>
                <td>Loan payments received ({{ $report['flows']['loan_repayments_count'] ?? 0 }} payments)</td>
                <td class="amount">M {{ number_format(($report['flows']['loan_repayments_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Penalty payments recorded</td>
                <td class="amount">M {{ number_format(($report['flows']['penalties_received_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>New loan money paid to members ({{ $report['flows']['loan_disbursements_count'] ?? 0 }} loans)</td>
                <td class="amount">(M
                    {{ number_format(($report['flows']['loan_disbursements_cents'] ?? 0) / 100, 2) }})</td>
            </tr>
            <tr class="total">
                <td>Money in minus new loans paid out <span class="small">(based on recorded payments)</span></td>
                <td class="amount">M {{ number_format(($report['flows']['net_captured_cash_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
    <p class="small">The interest part of loan repayments is already included in the loan payments above, not added
        again. Recorded payments and bank balances can differ while older money or corrections are being checked.</p>

    <div class="section">Where the society's money stands</div>
    <table class="table">
        <thead>
            <tr>
                <th>What it means</th>
                <th class="amount">At start of month</th>
                <th class="amount">At end of month</th>
            </tr>
        </thead>
        <tbody>
            @php
                $balanceRows = [
                    ['key' => 'BANK_ACCOUNT', 'display' => 'bank_for_report_cents', 'name' => 'Bank account balance'],
                    ['key' => 'CASH_ON_HAND', 'display' => 'CASH_ON_HAND', 'name' => 'Cash kept by the society'],
                    ['key' => 'MOBILE_MONEY', 'display' => 'MOBILE_MONEY', 'name' => 'Money in mobile wallets'],
                    [
                        'key' => 'liquid_assets',
                        'display' => 'money_held_for_report_cents',
                        'name' => 'Total money held',
                    ],
                    [
                        'key' => 'LOAN_PRINCIPAL_RECEIVABLE',
                        'display' => 'LOAN_PRINCIPAL_RECEIVABLE',
                        'name' => 'Loan principal still owed to the society',
                    ],
                    [
                        'key' => 'MEMBER_SAVINGS',
                        'display' => 'MEMBER_SAVINGS',
                        'name' => 'Total savings belonging to members',
                    ],
                    [
                        'key' => 'MEMBER_INTEREST_PAYABLE',
                        'display' => 'MEMBER_INTEREST_PAYABLE',
                        'name' => 'Interest earned by members',
                    ],
                ];
            @endphp
            @foreach ($balanceRows as $balanceRow)
                <tr @if ($balanceRow['key'] === 'liquid_assets') class="total" @endif>
                    <td>{{ $balanceRow['name'] }}</td>
                    <td class="amount">M
                        {{ number_format(($report['opening'][$balanceRow['display']] ?? ($report['opening'][$balanceRow['key']] ?? 0)) / 100, 2) }}
                    </td>
                    <td class="amount">M
                        {{ number_format(($report['position'][$balanceRow['display']] ?? ($report['position'][$balanceRow['key']] ?? 0)) / 100, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (
        ($report['opening']['bank_for_report_status'] ?? '') === 'reconstructed' ||
            ($report['position']['bank_for_report_status'] ?? '') === 'reconstructed')
        <p class="small"><strong>About the bank figures:</strong> Older balances are reconstructed from the society's
            saved transactions and the independently checked bank balance recorded later. They are estimates, not
            historical bank-statement confirmations. Unrecorded fees or transfers can change the actual balance.
            Historical clearing is not included a second time.</p>
    @else
        <p class="small">Bank and other money balances are based on entries recorded by each month-end. Loan money
            still owed is not money already in the bank.</p>
    @endif

    <div class="section">What each member paid or received this month</div>
    <table class="table">
        <thead>
            <tr>
                <th>Member</th>
                <th class="amount">Savings paid</th>
                <th class="amount">New loans</th>
                <th class="amount">Loan payments</th>
                <th class="amount">Penalties paid</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['members'] ?? []) as $member)
                <tr>
                    <td><strong>{{ $member['name'] }}</strong><br><span class="small">{{ $member['number'] }}</span>
                    </td>
                    <td class="amount">M {{ number_format(($member['flows']['contributions_cents'] ?? 0) / 100, 2) }}
                    </td>
                    <td class="amount">M
                        {{ number_format(($member['flows']['loan_disbursements_cents'] ?? 0) / 100, 2) }}</td>
                    <td class="amount">M {{ number_format(($member['flows']['loan_repayments_cents'] ?? 0) / 100, 2) }}
                    </td>
                    <td class="amount">M
                        {{ number_format(($member['flows']['penalties_received_cents'] ?? 0) / 100, 2) }}</td>
                </tr>
            @empty<tr>
                    <td colspan="5">No members are listed for this society.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (count($report['members'] ?? []) <= 8)
        <div class="keep-together">
    @endif
    <div class="section">Everyone's position at month-end</div>
    <table class="table">
        <thead>
            <tr>
                <th>Member</th>
                <th class="amount">Saved so far</th>
                <th class="amount">Interest earned</th>
                <th class="amount">Shared penalties*</th>
                <th class="amount">Still owes</th>
                <th class="amount">Estimated payout*</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['members'] ?? []) as $member)
                <tr>
                    <td><strong>{{ $member['name'] }}</strong><br><span class="small">{{ $member['number'] }}</span>
                    </td>
                    <td class="amount">M {{ number_format(($member['position']['savings_cents'] ?? 0) / 100, 2) }}</td>
                    <td class="amount">M {{ number_format(($member['position']['interest_cents'] ?? 0) / 100, 2) }}</td>
                    <td class="amount">
                        @if(
                            ($report['schema_version'] ?? 0) >= 3
                            && ($member['position']['penalty_share_cents'] ?? null) !== null
                        )
                            M {{ number_format(
                                ($member['position']['penalty_share_cents'] ?? 0) / 100,
                                2
                            ) }}
                        @else
                            Not available
                        @endif
                    </td>
                    <td class="amount">M {{ number_format(($member['position']['total_debt_cents'] ?? 0) / 100, 2) }}
                    </td>
                    <td class="amount"><strong>M
                            {{ number_format(($member['position']['illustrative_after_debts_cents'] ?? 0) / 100, 2) }}</strong>
                    </td>
                </tr>
            @empty<tr>
                    <td colspan="6">No member figures are available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <p class="small">*Estimated payout = savings + interest earned on that member's loan payments + their possible
        share of collected society penalties - unpaid loans and penalties. It is a guide, not an approved payment.
        Historical penalty receipts may still need cash checks, and loans are not automatically deducted. The share is
        only calculated when the stored society rule is 'shared equally'.</p>
    @if (count($report['members'] ?? []) <= 8)
        </div>
    @endif

    <div class="section">How shared penalty money affects estimated payouts</div>
    <table class="table">
        <tbody>
            <tr>
                <td>Penalty payments recorded up to month-end (this savings year)</td>
                <td class="amount">M {{ number_format(($report['penalty_sharing']['pool_cents'] ?? 0) / 100, 2) }}</td>
            </tr>
            <tr>
                <td>Number of members considered in the sharing estimate</td>
                <td class="amount">{{ $report['penalty_sharing']['eligible_count'] ?? 'Not available' }}</td>
            </tr>
            <tr>
                <td>Society sharing rule</td>
                <td class="amount">
                    {{ ($report['penalty_sharing']['method'] ?? '') === 'equal_all' ? 'Shared equally' : 'Needs confirmation' }}
                </td>
            </tr>
          
        </tbody>
    </table>
    <p class="small">Only paid penalties are considered for a possible share; penalties still owed are not shared.
        Older receipts not yet matched to the society's cash records remain provisional. Shares may change before
        year-end.</p>

    <div class="section">Every member's transactions this month</div>
    <p class="small">Each savings payment, loan given out, loan repayment and penalty payment is listed below. Interest
        earned and cancelled penalties are also shown for clarity, but are not additional payments. Payment references,
        contact details and private loan notes are not shared.</p>
    @forelse(($report['members'] ?? []) as $member)
        <div class="member-title">{{ $member['name'] }} &nbsp;&middot;&nbsp; {{ $member['number'] }} <span
                class="small">({{ count($member['transactions'] ?? []) }} items)</span></div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:65px">Date</th>
                    <th style="width:110px">What happened</th>
                    <th>Details</th>
                    <th style="width:83px">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($member['transactions'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td><strong>{{ \App\Support\MokhatloReportWords::transactionType($row) }}</strong><br><span
                                class="small">{{ \App\Support\MokhatloReportWords::societyAction($row) }}</span></td>
                        <td>{{ \App\Support\MokhatloReportWords::description($row) }}</td>
                        <td class="amount">M {{ number_format(($row['amount_cents'] ?? 0) / 100, 2) }}</td>
                    </tr>
                @empty<tr>
                        <td colspan="4" class="small">No transactions were recorded for this member during the
                            month.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <p class="small">No transactions are available for this month.</p>
    @endforelse

    <div class="note"><strong>How to read this report.</strong> The monthly list shows when payments or loans
        happened. The opening and closing figures show what the system had recorded at those dates, including any
        corrections or checks of older funds. These can differ without anyone paying extra money. Interest earned by a
        borrower is shown for their future payout and is not counted as another payment into the society. Detailed
        system checks remain available to the society's administrators.</div>
    <div class="footer">{{ $report['society'] }} &nbsp;&middot;&nbsp; {{ $report['period'] }} <span class="right">FOR
            SOCIETY MEMBERS &nbsp;&middot;&nbsp; Mokhatlo 2.0</span></div>
</body>

</html>
