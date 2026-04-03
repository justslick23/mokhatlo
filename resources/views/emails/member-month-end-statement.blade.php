<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body        { margin: 0; padding: 0; background: #f4f6f9; font-family: DejaVu Sans, Arial, sans-serif; }
        .wrapper    { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header     { background: #1a4480; padding: 32px 40px; }
        .header h1  { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
        .header p   { margin: 6px 0 0; color: #a8c4e8; font-size: 13px; }
        .body       { padding: 32px 40px; }
        .greeting   { font-size: 15px; color: #1a1a1a; margin-bottom: 16px; }
        .intro      { font-size: 13px; color: #444; line-height: 1.7; margin-bottom: 24px; }
        .stat-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }
        .stat-card  { background: #f0f4ff; border: 1px solid #c7d4f0; border-radius: 6px; padding: 14px 16px; }
        .stat-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-value { font-size: 18px; font-weight: 700; color: #1a4480; }
        .stat-card.warn    { background: #fff7ed; border-color: #fed7aa; }
        .stat-card.warn .stat-value { color: #b45309; }
        .stat-card.success { background: #f0fdf4; border-color: #bbf7d0; }
        .stat-card.success .stat-value { color: #0a7c59; }
        .stat-card.gold    { background: #fffbeb; border-color: #fde68a; }
        .stat-card.gold .stat-value { color: #92400e; }
        .loan-banner        { border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; line-height: 1.6; }
        .loan-banner.active { background: #fff7ed; border: 1px solid #fed7aa; color: #92400e; }
        .loan-banner.clear  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #065f46; }
        .share-box  { background: #f8faff; border: 1px solid #c7d4f0; border-radius: 6px; padding: 16px 18px; margin-bottom: 24px; }
        .share-box .row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
        .share-box .row:last-child { margin-bottom: 0; font-weight: 700; color: #1a4480; }
        .note       { background: #f8faff; border-left: 4px solid #1a4480; padding: 12px 16px; font-size: 12px; color: #555; line-height: 1.6; margin-bottom: 24px; border-radius: 0 4px 4px 0; }
        .footer     { background: #f4f6f9; padding: 20px 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <h1>Your Monthly Statement</h1>
        <p>{{ $society->name }} &mdash; {{ $period->format('F Y') }}</p>
    </div>

    <!-- Body -->
    <div class="body">
        <p class="greeting">Dear {{ $member->user->name }},</p>

        <p class="intro">
            Your statement for <strong>{{ $period->format('F Y') }}</strong> from
            <strong>{{ $society->name }}</strong> is attached. Here's a summary of
            your activity this month.
        </p>

        <!-- Stat grid -->
        <div class="stat-grid">
            <div class="stat-card {{ $memberData['total_contributed_month'] > 0 ? 'success' : 'warn' }}">
                <div class="stat-label">Contributed This Month</div>
                <div class="stat-value">M {{ number_format($memberData['total_contributed_month'], 2) }}</div>
            </div>
            <div class="stat-card {{ $memberData['total_penalties_month'] > 0 ? 'warn' : 'success' }}">
                <div class="stat-label">Penalties This Month</div>
                <div class="stat-value">M {{ number_format($memberData['total_penalties_month'], 2) }}</div>
            </div>
            <div class="stat-card {{ $memberData['total_interest_month'] > 0 ? 'gold' : 'success' }}">
                <div class="stat-label">Interest Paid This Month</div>
                <div class="stat-value">M {{ number_format($memberData['total_interest_month'], 2) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Contributed (Cycle)</div>
                <div class="stat-value">M {{ number_format($memberData['total_contributed_cycle'], 2) }}</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Your Pool Share</div>
                <div class="stat-value">{{ $memberData['share_percent'] }}%</div>
            </div>
            <div class="stat-card gold">
                <div class="stat-label">Total Interest Paid (Cycle)</div>
                <div class="stat-value">M {{ number_format($memberData['total_interest_cycle'], 2) }}</div>
            </div>
        </div>

        <!-- Loan status banner -->
        @if ($memberData['active_loan'])
            <div class="loan-banner active">
                ⚠️ <strong>Active Loan:</strong>
                You have an outstanding loan balance of
                <strong>M {{ number_format($memberData['active_loan']->outstanding_balance, 2) }}</strong>
                due on <strong>{{ $memberData['active_loan']->due_date->format('d M Y') }}</strong>.
                Status: <strong>{{ ucfirst($memberData['active_loan']->status) }}</strong>.
                Please ensure repayment is made on time to avoid penalties.
            </div>
        @else
            <div class="loan-banner clear">
                ✅ <strong>No outstanding loans.</strong>
                You have no active loans this cycle. You are eligible to apply for a loan.
            </div>
        @endif

        <!-- Share in pool -->
        <div class="share-box">
            <div class="row">
                <span>Total Contributed (cycle to date)</span>
                <span>M {{ number_format($memberData['total_contributed_cycle'], 2) }}</span>
            </div>
            <div class="row">
                <span>Total Interest Paid (cycle to date)</span>
                <span>M {{ number_format($memberData['total_interest_cycle'], 2) }}</span>
            </div>
            <div class="row">
                <span>Your Share of Pool</span>
                <span>{{ $memberData['share_percent'] }}%</span>
            </div>
            <div class="row">
                <span>Estimated Share Value</span>
                <span>M {{ number_format($memberData['share_value'], 2) }}</span>
            </div>
        </div>

        <div class="note">
            📎 Your full statement including a transaction breakdown is attached as a PDF.
            Please keep this for your records. If you have any questions contact your
            society treasurer.
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        This statement was automatically generated on {{ now()->format('d M Y') }}
        for <strong>{{ $society->name }}</strong>.<br>
        Please do not reply to this email.
    </div>

</div>
</body>
</html>