<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body        { margin: 0; padding: 0; background: #f4f6f9; font-family: DejaVu Sans, Arial, sans-serif; }
        .wrapper    { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .accent     { height: 5px; background: #e8a020; }
        .header     { background: #0f2d5e; padding: 28px 40px; }
        .header h1  { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .header p   { margin: 6px 0 0; color: #7fa8d4; font-size: 12px; }
        .body       { padding: 32px 40px; }
        .greeting   { font-size: 15px; color: #1a1a1a; margin-bottom: 14px; }
        .intro      { font-size: 13px; color: #444; line-height: 1.7; margin-bottom: 24px; }
        .alert-box  { background: #fff8e6; border: 1px solid #f0d060; border-left: 4px solid #e8a020; border-radius: 4px; padding: 14px 18px; margin-bottom: 24px; }
        .days       { font-size: 28px; font-weight: bold; color: #8a5a00; line-height: 1; }
        .days-label { font-size: 12px; color: #7a5800; margin-top: 2px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table td { padding: 9px 0; border-bottom: 1px solid #edf0f7; font-size: 13px; }
        .detail-table td:first-child { color: #6b7a99; width: 55%; }
        .detail-table td:last-child { font-weight: bold; color: #1c2333; text-align: right; }
        .detail-table tr:last-child td { border-bottom: none; }
        .note       { background: #f4f7fc; border-radius: 4px; padding: 12px 16px; font-size: 12px; color: #555; line-height: 1.6; }
        .footer     { background: #f4f6f9; padding: 18px 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="accent"></div>
    <div class="header">
        <h1>Loan Repayment Reminder</h1>
        <p>{{ $society->name }}</p>
    </div>
    <div class="body">
        <p class="greeting">Dear {{ $loan->member->user->name }},</p>
        <p class="intro">
            This is a reminder that your loan repayment with <strong>{{ $society->name }}</strong>
            is due soon. Please ensure payment is made before the due date to avoid penalties.
        </p>

        <div class="alert-box">
            <div class="days">{{ $daysLeft }}</div>
            <div class="days-label">day(s) until your loan repayment is due</div>
        </div>

        <table class="detail-table">
            <tr>
                <td>Loan Principal</td>
                <td>M {{ number_format($loan->principal, 2) }}</td>
            </tr>
            <tr>
                <td>Total Amount</td>
                <td>M {{ number_format($loan->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Amount Repaid</td>
                <td style="color:#1a7c5a;">M {{ number_format($loan->amount_repaid, 2) }}</td>
            </tr>
            <tr>
                <td>Outstanding Balance</td>
                <td style="color:#c0392b;">M {{ number_format($loan->outstanding_balance, 2) }}</td>
            </tr>
            <tr>
                <td>Due Date</td>
                <td>{{ $loan->due_date->format('d M Y') }}</td>
            </tr>
            <tr>
                <td>Late Penalty</td>
                <td>
                    @if ($society->penalty_type === 'fixed')
                        M {{ number_format($society->penalty_value, 2) }} fixed
                    @else
                        {{ $society->penalty_value }}% of outstanding balance
                    @endif
                </td>
            </tr>
        </table>

        <div class="note">
            &#9432;&nbsp; If you have already made your repayment, please disregard this notice.
            Contact your society treasurer if you have any questions.
        </div>
    </div>
    <div class="footer">
        Automated reminder from <strong>{{ $society->name }}</strong> &mdash; {{ now()->format('d M Y') }}<br>
        Please do not reply to this email.
    </div>
</div>
</body>
</html>