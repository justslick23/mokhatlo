{{-- ========================================================= --}}
{{-- emails/society-month-end-report.blade.php --}}
{{-- UPDATED WITH TOTAL DEBT SECTION --}}
{{-- ========================================================= --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body{margin:0;padding:0;background:#f4f6f9;font-family:DejaVu Sans,Arial;}
.wrapper{max-width:600px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:#1a4480;padding:32px 40px}
.header h1{margin:0;color:#fff;font-size:22px}
.header p{margin:6px 0 0;color:#a8c4e8;font-size:13px}
.body{padding:32px 40px}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stat-card{padding:14px 16px;border-radius:6px;background:#f0f4ff;border:1px solid #c7d4f0}
.label{font-size:11px;color:#6b7280;text-transform:uppercase}
.value{font-size:18px;font-weight:700;color:#1a4480}
.warn{background:#fff7ed;border-color:#fed7aa}
.warn .value{color:#b45309}
.success{background:#f0fdf4;border-color:#bbf7d0}
.success .value{color:#0a7c59}
.danger{background:#fef2f2;border-color:#fecaca}
.danger .value{color:#b91c1c}
.footer{padding:20px;text-align:center;font-size:11px;color:#999}
</style>
</head>
<body>
<div class="wrapper">

<div class="header">
<h1>{{ $society->name }}</h1>
<p>Month-End Report — {{ $period->format('F Y') }}</p>
</div>

<div class="body">

<div class="stat-grid">

<div class="stat-card">
<div class="label">Contributions</div>
<div class="value">M {{ number_format($summary['total_contributions'],2) }}</div>
</div>

<div class="stat-card success">
<div class="label">Available Balance</div>
<div class="value">M {{ number_format($summary['available_balance'],2) }}</div>
</div>

<div class="stat-card warn">
<div class="label">Outstanding Loans</div>
<div class="value">M {{ number_format($summary['total_outstanding'],2) }}</div>
</div>

<div class="stat-card">
<div class="label">Interest Collected</div>
<div class="value">M {{ number_format($summary['total_interest_collected'],2) }}</div>
</div>

{{-- NEW --}}
<div class="stat-card danger">
<div class="label">Total Member Debt</div>
<div class="value">M {{ number_format($summary['grand_total_debt'],2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Contribution Debt</div>
<div class="value">M {{ number_format($summary['total_contribution_debt'],2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Penalty Debt</div>
<div class="value">M {{ number_format($summary['total_contribution_penalty_debt'],2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Loan Debt</div>
<div class="value">M {{ number_format($summary['total_loan_debt'],2) }}</div>
</div>

</div>

</div>

<div class="footer">
Generated automatically {{ now()->format('d M Y') }}
</div>

</div>
</body>
</html>
