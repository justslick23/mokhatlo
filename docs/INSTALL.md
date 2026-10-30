# Step 9E.3 — ordinary screen historical coverage integration

This patch reuses the installed Step 9E.3 `ContributionObligationCoverage` service. It does NOT repost savings or interest and does NOT recalculate penalties.

## Installation
1. Back up the existing four controllers and database.
2. Copy `app/` and `resources/` into the Laravel project, merging folders.
3. Add the Blade include below to the existing views that should display the new historical summary:

   `@include('reports.partials.historical-contribution-coverage')`

   Suggested views: `resources/views/dashboard.blade.php`, `resources/views/reports/index.blade.php`, and `resources/views/reports/member-statement.blade.php`.

4. On the existing `resources/views/contributions/index.blade.php`, the `paid_total` attribute for displayed historical cycle obligations now includes reviewed historical receipt links. `historical_paid_total`, `direct_paid_total`, `outstanding_total`, and `historical_excess_total` are also available on each displayed `$obligation`. These are read-only presentation attributes.
5. No new routes or migrations.
6. Run `php artisan optimize:clear`, `php artisan test --filter=ObligationCoverageMathTest`, `php artisan mokhatlo:legacy:compliance-preview --society=1 --cycle=2`, and `php artisan mokhatlo:legacy:audit --society=1`.

## Limitations
- `SocietyFinancialReport`, `FinancialDashboardInsights`, `MemberEarningsPreview`, `MemberPayoutPreview`, and `MemberFinancialStatement` source implementations were not supplied. They may still calculate historical outstanding contributions from direct payment FKs only. The separate historical panel is correct; it does NOT certify those old service metrics.
- Existing 30 historical obligations are covered based on reviewed links. Presence or absence of obligations outside the 30 is NOT established.
- Late-payment and penalty policies require separate historical review.
- The normal member statement transaction rows/CSV have been preserved unchanged to avoid doubling payment flows. Any period-aware breakdown must be added at the `MemberFinancialStatement` service level.
- This package has been checked with `php -l` only, not run against the local Laragon database.

## Verification: expected local output
After selecting `?cycle_id=2` on `/contributions`, the 30 historical obligations should show reviewed coverage instead of M0.00 direct-FK paid. For cycle #2 the compliance preview showed: 30/30 obligations covered; M15,050 required; M16,550 total receipt coverage; M1,500 excess; M0 outstanding for those obligations.

The historical summary panel is read-only and stays separate from active-cycle financial report metrics. Check the reconciliation dashboard after enabling the UI:

```
php artisan optimize:clear
php artisan route:list --path=contributions
php artisan mokhatlo:legacy:compliance-preview --society=1 --cycle=2
php artisan mokhatlo:legacy:audit --society=1
```

The report controllers have been extended to pass a `historicalCompliance` variable to their views. Without inserting the `@include(...)` line, the new panel will not appear.

## IMPORTANT: complete ordinary reporting integration
The existing code for the following services is still needed for a full review and correction of ordinary financial metrics:
- `app/Services/Reports/SocietyFinancialReport.php`
- `app/Services/Reports/FinancialDashboardInsights.php`
- `app/Services/Reports/MemberFinancialStatement.php`
- `app/Services/Reports/MemberEarningsPreview.php`
- `app/Services/Reports/MemberPayoutPreview.php`

Do not change `contributions_received` or member savings balances by adding historical links to payments; historical links do not represent additional money. Adjust only obligation coverage, outstanding status, due/overdue computation, and period labels as appropriate. Do not run historical penalty backfills on an inferred policy.
