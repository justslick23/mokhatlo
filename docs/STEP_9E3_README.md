# Step 9E.3 — Ordinary Contribution Compliance Bridge (read-only)

Grounded in the **Step 9E.2** migrations: `legacy_receipt_obligation_links` and `legacy_receipt_period_attributions`; normal `contribution_payments.contribution_obligation_id`; `contribution_obligations`. Your last audit passed 32/32 checks, with no unallocated historical contributions and no historical month attribution errors.

## What this package does
- Adds `App\Services\Contributions\ContributionObligationCoverage`, a read-only service that combines conventional directly assigned receipts and **active reviewed historical obligation links** by obligation, society, cycle and member, respecting original payment dates.
- Excludes reversed links and reversed period attributions and prevents the same historical receipt from being counted via a direct-FK and historical link.
- Provides `mokhatlo:legacy:compliance-preview` for a standalone comparison before changing ordinary reports.
- Adds pure arithmetic tests; no migrations, routing, financial postings, or writes.

## Install
1. Back up your Laravel code. Merge `app/` and `tests/` into `C:\laragon\www\mkhtlo`.
2. Run:
   ```powershell
   php artisan optimize:clear
   php artisan test --filter=ObligationCoverageMathTest
   php artisan mokhatlo:legacy:compliance-preview --society=1 --cycle=2
   php artisan mokhatlo:legacy:audit --society=1
   ```
3. Compare the historical coverage shown by the command with your verified 9E.2 linked obligations.

## Integrate into existing report code (NOT done automatically)
The service method is:

```php
$coverage = app(\App\Services\Contributions\ContributionObligationCoverage::class)
    ->forCycle($societyId, $cycleId, '2026-10-29');
// $coverage['rows'][i]['paid_cents'] and ['outstanding_cents']
```

Replace only the part of **ordinary compliance/statement read queries** that assumes every payment has a non-NULL `contribution_obligation_id`. Do not simply add new historical totals to an existing total if that existing query already joins them. Use one consolidated source of obligation coverage to prevent double counting. This package does not modify `FinancialReportController`, `ContributionController`, penalty services, or payout calculations because their current implementations weren't supplied; obtain the actual files before patching.

## Remaining gates
- **Ordinary compliance report bridge verified** stays **pending** until the real FinancialReportController/statement/compliance calculations are changed and tested against this service.
- Existing historical obligation rows do not prove *all required months* existed. Missing-month determination requires verified historical participant coverage and policy snapshot.
- Automated historical penalties, historical cycle activation, payout approval, and cycle close remain blocked until penalty, clearing, and loan issues are resolved.
- M11,790 is still a **legacy clearing** balance, not confirmed bank cash. Five historical penalty review items remain unresolved and M12,084 in loans/charges outstanding.

No database mutation occurs anywhere in this module.
