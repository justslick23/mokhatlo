<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body        { margin: 0; padding: 0; background: #f4f6f9; font-family: DejaVu Sans, Arial, sans-serif; }
        .wrapper    { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .accent     { height: 5px; background: #c0392b; }
        .header     { background: #0f2d5e; padding: 28px 40px; }
        .header h1  { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .header p   { margin: 6px 0 0; color: #7fa8d4; font-size: 12px; }
        .body       { padding: 32px 40px; }
        .greeting   { font-size: 15px; color: #1a1a1a; margin-bottom: 14px; }
        .intro      { font-size: 13px; color: #444; line-height: 1.7; margin-bottom: 24px; }
        .penalty-box {
            background: #fdf4f3;
            border: 1px solid #f0c8c5;
            border-left: 4px solid #c0392b;
            border-radius: 4px;
            padding: 18px 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .penalty-label  { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #c0392b; margin-bottom: 6px; font-weight: bold; }
        .penalty-amount { font-size: 36px; font-weight: bold; color: #c0392b; line-height: 1; }
        .penalty-sub    { font-size: 12px; color: #e07070; margin-top: 4px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table td { padding: 9px 0; border-bottom: 1px solid #edf0f7; font-size: 13px; }
        .detail-table td:first-child { color: #6b7a99; width: 55%; }
        .detail-table td:last-child { font-weight: bold; color: #1c2333; text-align: right; }
        .detail-table tr:last-child td { border-bottom: none; }
        .action-box { background: #f4f7fc; border-radius: 4px; padding: 14px 18px; font-size: 12px; color: #444; line-height: 1.7; }
        .action-box strong { color: #0f2d5e; }
        .footer     { background: #f4f6f9; padding: 18px 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="accent"></div>
    <div class="header">
        <h1>Loan Penalty Notice</h1>
        <p>{{ $society->name }}</p>
    </div>
    <div class="body">
        <p class="greeting">Dear {{ $loan->member->user->name }},</p>
        <p class="intro">
            A penalty has been applied to your loan account with <strong>{{ $society->name }}</strong>
            due to a missed repayment deadline. Please review the details below and take action immediately.
        </p>

        <!-- Penalty callout -->
        <div class="penalty-box">
            <div class="penalty-label">Loan Penalty Applied</div>
            <div class="penalty-amount">M {{ number_format($penaltyAmount, 2) }}</div>
            <div class="penalty-sub">Applied on {{ now()->format('d M Y') }}</div>
        </div>

        <table class="detail-table">
            <tr>
                <td>Society</td>
                <td>{{ $society->name }}</td>
            </tr>
            <tr>
                <td>Original Principal</td>
                <td>M {{ number_format($loan->principal, 2) }}</td>
            </tr>
            <tr>
                <td>Penalty Type</td>
                <td>
                    @if ($society->penalty_type === 'fixed')
                        Fixed Amount
                    @else
                        {{ $society->penalty_value }}% of Outstanding Balance
                    @endif
                </td>
            </tr>
            <tr>
                <td>Penalty Applied</td>
                <td style="color:#c0392b;">M {{ number_format($penaltyAmount, 2) }}</td>
            </tr>
            <tr>
                <td>Updated Outstanding Balance</td>
                <td style="color:#c0392b;">M {{ number_format($loan->outstanding_balance, 2) }}</td>
            </tr>
            <tr>
                <td>Original Due Date</td>
                <td>{{ $loan->due_date->format('d M Y') }}</td>
            </tr>
            <tr>
                <td>Loan Status</td>
                <td style="color:#c0392b;">{{ ucfirst($loan->status) }}</td>
            </tr>
        </table>

        <div class="action-box">
            <strong>Immediate action required:</strong><br>
            Your loan is now marked as <strong>overdue</strong>. Please settle your outstanding balance
            of <strong>M {{ number_format($loan->outstanding_balance, 2) }}</strong> as soon as possible.
            Further penalties will continue to accrue until the balance is fully repaid.<br><br>
            Contact your society treasurer immediately to arrange repayment.
        </div>
    </div>
    <div class="footer">
        Automated notice from <strong>{{ $society->name }}</strong> &mdash; {{ now()->format('d M Y') }}<br>
        Please do not reply to this email.
    </div>
</div>
</body>
</html>