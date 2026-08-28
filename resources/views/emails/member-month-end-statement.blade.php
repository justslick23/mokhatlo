{{-- emails/member-month-end-statement.blade.php --}}
{{-- FIXED: All variables with proper null-safety checks --}}
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
.success{background:#f0fdf4;border-color:#bbf7d0}
.success .value{color:#0a7c59}
.warn{background:#fff7ed;border-color:#fed7aa}
.warn .value{color:#b45309}
.danger{background:#fef2f2;border-color:#fecaca}
.danger .value{color:#b91c1c}
.footer{padding:20px;text-align:center;font-size:11px;color:#999}
</style>
</head>
<body>
<div class="wrapper">

<div class="header">
<h1>Your Monthly Statement</h1>
<p>{{ $society->name ?? 'Society' }} — {{ $period->format('F Y') ?? 'Report' }}</p>
</div>

<div class="body">

@php
    $totalContributedMonth = $memberData['total_contributed_month'] ?? 0;
    $totalPenaltiesMonth = $memberData['total_penalties_month'] ?? 0;
    $totalContributedCycle = $memberData['total_contributed_cycle'] ?? 0;
    $shareValue = $memberData['share_value'] ?? 0;
    $contributionDebt = $memberData['contribution_debt'] ?? 0;
    $contributionPenaltyDebt = $memberData['contribution_penalty_debt'] ?? 0;
    $loanDebt = $memberData['loan_debt'] ?? 0;
    $grandTotalDebt = $memberData['grand_total_debt'] ?? 0;
@endphp

<div class="stat-grid">

<div class="stat-card success">
<div class="label">Contributed This Month</div>
<div class="value">M {{ number_format($totalContributedMonth, 2) }}</div>
</div>

<div class="stat-card warn">
<div class="label">Penalties This Month</div>
<div class="value">M {{ number_format($totalPenaltiesMonth, 2) }}</div>
</div>

<div class="stat-card">
<div class="label">Cycle Contributions</div>
<div class="value">M {{ number_format($totalContributedCycle, 2) }}</div>
</div>

<div class="stat-card">
<div class="label">Share Value</div>
<div class="value">M {{ number_format($shareValue, 2) }}</div>
</div>

{{-- DEBT SECTION --}}
<div class="stat-card danger">
<div class="label">Contribution Debt</div>
<div class="value">M {{ number_format($contributionDebt, 2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Penalty Debt</div>
<div class="value">M {{ number_format($contributionPenaltyDebt, 2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Loan Debt</div>
<div class="value">M {{ number_format($loanDebt, 2) }}</div>
</div>

<div class="stat-card danger">
<div class="label">Total Debt Owed</div>
<div class="value">M {{ number_format($grandTotalDebt, 2) }}</div>
</div>

</div>

</div>

<div class="footer">
Generated automatically {{ now()->format('d M Y') }}
</div>

</div>
</body>
</html>