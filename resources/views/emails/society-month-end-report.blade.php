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
        .stat-card.warn  { background: #fff7ed; border-color: #fed7aa; }
        .stat-card.warn .stat-value { color: #b45309; }
        .stat-card.success { background: #f0fdf4; border-color: #bbf7d0; }
        .stat-card.success .stat-value { color: #0a7c59; }
        .note       { background: #f8faff; border-left: 4px solid #1a4480; padding: 12px 16px; font-size: 12px; color: #555; line-height: 1.6; margin-bottom: 24px; border-radius: 0 4px 4px 0; }
        .footer     { background: #f4f6f9; padding: 20px 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
        .footer a   { color: #1a4480; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <h1>{{ $society->name }}</h1>
        <p>Month-End Report &mdash; {{ $period->format('F Y') }}</p>
    </div>

    <!-- Body -->
    <div class="body">
        <p class="greeting">Dear {{ auth()->user()->name ?? 'Officer' }},</p>

        <p class="intro">
            Please find attached the month-end financial report for <strong>{{ $society->name }}</strong>
            for the period ending <strong>{{ $period->format('d F Y') }}</strong>.
            Below is a snapshot of the key figures. Full details are in the attached PDF.
        </p>

        <!-- Stat grid -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total Contributions</div>
                <div class="stat-value">M {{ number_format($summary['total_contributions'], 2) }}</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Available Balance</div>
                <div class="stat-value">M {{ number_format($summary['available_balance'], 2) }}</div>
            </div>
            <div class="stat-card warn">
                <div class="stat-label">Penalties Collected</div>
                <div class="stat-value">M {{ number_format($summary['total_penalties'], 2) }}</div>
            </div>
            <div class="stat-card warn">
                <div class="stat-label">Outstanding Loans</div>
                <div class="stat-value">M {{ number_format($summary['total_outstanding'], 2) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Loans</div>
                <div class="stat-value">{{ $summary['active_loans_count'] }}</div>
            </div>
            <div class="stat-card {{ $summary['defaulters_count'] > 0 ? 'warn' : 'success' }}">
                <div class="stat-label">Defaulters This Month</div>
                <div class="stat-value">{{ $summary['defaulters_count'] }}</div>
            </div>
        </div>

        <div class="note">
            📎 The full report including the member-by-member breakdown is attached as a PDF.
            Please review and retain for your records.
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        This report was automatically generated on {{ now()->format('d M Y') }}
        for <strong>{{ $society->name }}</strong>.<br>
        Please do not reply to this email.
    </div>

</div>
</body>
</html>