<!doctype html><html lang="en"><head><meta charset="utf-8"></head><body style="font-family:Arial,Helvetica,sans-serif;color:#17213a;background:#f6f8fb;margin:0;padding:24px">
<div style="max-width:620px;margin:auto;background:white;border:1px solid #dce3ee;border-radius:12px;overflow:hidden">
<div style="background:#101133;padding:20px 26px;color:white"><strong style="font-size:20px">MOKHATLO</strong><div style="font-size:12px;margin-top:5px;color:#e4d3a4">Society Financial Updates</div></div>
<div style="padding:24px"><h2 style="margin-top:0">{{ $notice['headline'] }}</h2><p>{{ $notice['intro'] }}</p>
@isset($notice['event'])
<table style="border-collapse:collapse;width:100%;font-size:14px">
<tr><td style="padding:8px;border-bottom:1px solid #eee">Member</td><td style="padding:8px;border-bottom:1px solid #eee"><strong>{{ $notice['event']['member'] }}</strong></td></tr>
@if($notice['event']['amount'] !== null)<tr><td style="padding:8px;border-bottom:1px solid #eee">Amount (LSL)</td><td style="padding:8px;border-bottom:1px solid #eee"><strong>M {{ number_format((float) $notice['event']['amount'], 2) }}</strong></td></tr>@endif
@if($notice['event']['loan_number'])<tr><td style="padding:8px;border-bottom:1px solid #eee">Loan</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $notice['event']['loan_number'] }}</td></tr>@endif
<tr><td style="padding:8px;border-bottom:1px solid #eee">Transaction date</td><td style="padding:8px;border-bottom:1px solid #eee">{{ $notice['event']['transaction_date'] }}</td></tr>
</table>
@endisset
<p style="margin-top:25px;color:#657184;font-size:12px">{{ $notice['footer'] }}</p></div></div></body></html>
