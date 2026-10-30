<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Your monthly statement - {{ $report['period'] }}</title>
    <style>
        @page {
            margin: 35px 41px 48px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #222331;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            line-height: 1.46;
        }

        .top {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 20px;
        }

        .top td {
            vertical-align: top;
            padding: 0;
        }

        .logo {
            color: #574b9e;
            letter-spacing: .3px;
            font-size: 19px;
            font-weight: bold;
        }

        .logo small {
            display: block;
            font-size: 8px;
            color: #747584;
            font-weight: normal;
            letter-spacing: 1.2px;
            margin-top: 3px;
        }

        .document {
            text-align: right;
        }

        .document h1 {
            font-size: 21px;
            line-height: 1.18;
            margin: 0;
            font-weight: bold;
            color: #222331;
        }

        .document .month {
            color: #574b9e;
            font-weight: bold;
            margin-top: 6px;
            font-size: 11px;
        }

        .rule {
            border-bottom: 2px solid #574b9e;
            height: 1px;
            margin: 0 0 17px;
        }

        .two-col {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .two-col td {
            width: 50%;
            padding: 0 10px 0 0;
            vertical-align: top;
        }

        .cap {
            color: #666a75;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: .7px;
            font-weight: bold;
        }

        .value {
            font-size: 10px;
            margin-top: 4px;
            font-weight: bold;
        }

        .hint {
            font-size: 8px;
            color: #626574;
            line-height: 1.5;
        }

        .heading {
            font-size: 12px;
            font-weight: bold;
            margin: 13px 0 7px;
            padding-bottom: 7px;
            border-bottom: 1px solid #cdd0d8;
            page-break-after: avoid;
        }

        .payout {
            border-collapse: collapse;
            width: 100%;
            margin: 0 0 6px;
        }

        .payout td {
            vertical-align: middle;
            padding: 12px 15px;
        }

        .payout .focus {
            width: 52%;
            background: #5750a0;
            color: white;
        }

        .payout .focus .big {
            font-size: 22px;
            font-weight: bold;
            padding-top: 5px;
        }

        .payout .focus .small {
            color: #f0ecff;
        }

        .payout .side {
            border: 1px solid #e1e2e9;
            border-left: 8px solid #fff;
        }

        .payout .side .big {
            font-size: 16px;
            color: #242334;
            font-weight: bold;
            padding-top: 5px;
        }

        .summary {
            border-collapse: collapse;
            width: 100%;
            margin: 12px 0 3px;
        }

        .summary td {
            width: 33.333%;
            vertical-align: top;
            padding: 4px 10px 9px 0;
            border-bottom: 1px solid #dfe1e6;
        }

        .summary .big {
            margin-top: 4px;
            font-weight: bold;
            font-size: 14px;
            color: #282637;
        }

        .table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 4px;
            font-size: 8.5px;
        }

        .table thead {
            display: table-header-group;
        }

        .table th {
            border-top: 1px solid #9e9cae;
            border-bottom: 1px solid #9e9cae;
            padding: 7px 5px;
            color: #554a95;
            text-align: left;
            font-size: 8px;
        }

        .table td {
            border-bottom: 1px solid #e8e8ed;
            padding: 5.7px 5px;
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
            border-top: 1px solid #b9b7c9;
        }

        .small {
            font-size: 7.5px;
            color: #656979;
        }

        .note {
            border-left: 2px solid #574b9e;
            padding: 9px 11px;
            margin-top: 9px;
            background: #f7f7fb;
            font-size: 8px;
            color: #4e5061;
        }

        .footer {
            position: fixed;
            bottom: -31px;
            left: 0;
            right: 0;
            border-top: 1px solid #dddddf;
            padding-top: 7px;
            color: #797982;
            font-size: 7px;
        }

        .footer-right {
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
                <h1>Your monthly statement</h1>
                <div class="month">{{ \Carbon\CarbonImmutable::parse($report['from'])->format('F Y') }}</div>
                <div class="hint">Up to {{ \Carbon\CarbonImmutable::parse($report['to'])->format('d F Y') }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <table class="two-col">
        <tr>
            <td>
                <div class="cap">Prepared for</div>
                <div class="value">{{ $report['member'] }}</div>
                <div class="hint">Member {{ $report['member_number'] }}</div>
            </td>
            <td>
                <div class="cap">Your society</div>
                <div class="value">{{ $report['society'] }}</div>
                <div class="hint">Savings year: {{ $report['cycle'] }} &nbsp;&middot;&nbsp; Private - for you only
                </div>
            </td>
        </tr>
    </table>

    @if (($report['schema_version'] ?? 1) < 2)
        <div class="note">This copy was prepared using the previous report format. The detailed payout estimate below
            needs a new month-end report. Your earlier record has not been changed.</div>
    @endif

    <div class="heading">Your savings and possible payout</div>
    <table class="payout">
        <tr>
            <td class="focus">
                <div class="small">Estimated payout after settling what you owe*</div>
                <div class="big">
                    @if (($report['schema_version'] ?? 1) >= 2)
                        M {{ number_format(($report['position']['illustrative_after_debts_cents'] ?? 0) / 100, 2) }}
                    @else
                        Not available
                    @endif
                </div>
                <div class="small" style="font-size:7.5px;margin-top:4px">Estimate at the end of this month</div>
            </td>
            <td class="side">
                <div class="cap" style="text-transform:none;letter-spacing:0">Your savings + interest earned</div>
                <div class="big">M
                    {{ number_format(($report['position']['indicative_gross_cents'] ?? ($report['savings_to_date_cents'] ?? 0) + ($report['interest_credit_to_date_cents'] ?? 0)) / 100, 2) }}
                </div>
                <div class="small">Before settling any outstanding loans or penalties</div>
            </td>
        </tr>
    </table>
    <table class="summary">
        <tr>
            <td>
                <div class="cap">Saved so far</div>
                <div class="big">M
                    {{ number_format(($report['position']['savings_cents'] ?? ($report['savings_to_date_cents'] ?? 0)) / 100, 2) }}
                </div>
            </td>
            <td>
                <div class="cap">Interest earned</div>
                <div class="big">M
                    {{ number_format(($report['position']['interest_cents'] ?? ($report['interest_credit_to_date_cents'] ?? 0)) / 100, 2) }}
                </div>
                <div class="small">From your own loan payments</div>
            </td>
            <td>
                <div class="cap">Still to pay</div>
                <div class="big">M
                    {{ number_format(($report['position']['total_debt_cents'] ?? ($report['contractual_loan_due_cents'] ?? 0)) / 100, 2) }}
                </div>
                <div class="small">Loans and penalties</div>
            </td>
        </tr>
    </table>

    <div class="heading">What happened this month</div>
    <table class="table">
        <tbody>
            <tr>
                <td>Savings you paid</td>
                <td class="amount">M {{ number_format(($report['flows']['contributions_cents'] ?? 0) / 100, 2) }}</td>
            </tr>
            <tr>
                <td>Loan money you received</td>
                <td class="amount">M {{ number_format(($report['flows']['loan_disbursements_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Loan payments you made</td>
                <td class="amount">M {{ number_format(($report['flows']['loan_repayments_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Penalty payments you made</td>
                <td class="amount">M {{ number_format(($report['flows']['penalties_received_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Interest included in your loan payments <span class="small">(already part of the loan payments
                        above)</span></td>
                <td class="amount">M
                    {{ number_format(($report['flows']['loan_interest_component_cents'] ?? 0) / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="heading">What you still owe at month-end</div>
    <table class="table">
        <tbody>
            <tr>
                <td>Unpaid loan balance, including loan interest</td>
                <td class="amount">M
                    {{ number_format(($report['position']['loan_due_cents'] ?? ($report['contractual_loan_due_cents'] ?? 0)) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Unpaid loan penalties</td>
                <td class="amount">M {{ number_format(($report['position']['loan_penalty_due_cents'] ?? 0) / 100, 2) }}
                </td>
            </tr>
            <tr>
                <td>Unpaid savings penalties</td>
                <td class="amount">M
                    {{ number_format(($report['position']['contribution_penalty_due_cents'] ?? 0) / 100, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Total still to pay</td>
                <td class="amount">M
                    {{ number_format(($report['position']['total_debt_cents'] ?? ($report['contractual_loan_due_cents'] ?? 0)) / 100, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="heading">All your transactions this month <span class="small">({{ count($report['entries'] ?? []) }}
            items)</span></div>
    <table class="table">
        <thead>
            <tr>
                <th style="width:68px">Date</th>
                <th style="width:108px">What happened</th>
                <th>Details</th>
                <th style="width:81px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($report['entries'] ?? []) as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td><strong>{{ \App\Support\MokhatloReportWords::transactionType($row) }}</strong><br><span
                            class="small">{{ \App\Support\MokhatloReportWords::memberAction($row) }}</span></td>
                    <td>{{ \App\Support\MokhatloReportWords::description($row) }}@if (!empty($row['reference']))
                            <br><span class="small">Payment ref: {{ $row['reference'] }}</span>
                        @endif
                    </td>
                    <td class="amount">M {{ number_format(($row['amount_cents'] ?? 0) / 100, 2) }}</td>
                </tr>
                @empty
                    <tr>
                        <td colspan="4" class="small">No transactions were recorded for you this month.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="note"><strong>A note about your estimated payout.</strong> *It shows what might remain from your
            savings and earned interest after settling recorded loans and penalties as at {{ $report['to'] }}. Your society
            has not approved a payout or automatically deducted these debts. Any future savings, interest, shared penalty
            money or final adjustments can change the amount. If what you owe is more than your savings and interest, the
            remaining amount would still need to be paid.</div>
        <div class="footer">{{ $report['society'] }} &nbsp;&middot;&nbsp; {{ $report['period'] }} <span
                class="footer-right">PRIVATE MEMBER COPY &nbsp;&middot;&nbsp; Mokhatlo 2.0</span></div>
    </body>

    </html>
