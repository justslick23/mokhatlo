# Mokhatlo 2.0 — Step 9D Post-Import Audit & Review Dashboard

## Purpose

Audit the financial import you already completed. This package contains **no migrations, imports, money-posting services, or write endpoints**. Do not run `migrate:fresh`. It only reads the database and the encrypted source plan under `storage/app/private/legacy-audits`.

**Files to copy to your existing Laravel application:**
- `app/Services/Legacy/AuditLegacyFinancialImport.php`
- `app/Console/Commands/AuditLegacyFinancialImport.php`
- `app/Http/Controllers/LegacyReconciliationController.php`
- `resources/views/legacy/reconciliation.blade.php`
- `resources/views/legacy/reconciliation-empty.blade.php`

Do not replace your existing `routes/web.php` or `resources/views/layouts/app.blade.php`. Add the route declaration from `routes/STEP9D_ROUTE_SNIPPET.txt` to your current `routes/web.php`. Add the optional sidebar link by hand if desired.

## Run

```powershell
cd C:\laragon\www\mkhtlo
php artisan optimize:clear
php artisan mokhatlo:legacy:audit --society=1
php artisan route:list --name=legacy
```

Visit `/legacy/reconciliation` while logged in as the active society **owner** (or another account explicitly granted `society.manage`). A Spatie team-scoped permission and active society membership are required.

Optional sidebar link within existing `@can('society.manage')`:

```blade
@can('society.manage')
<a class="side-link {{ request()->routeIs('legacy.reconciliation.*') ? 'active' : '' }}" href="{{ route('legacy.reconciliation.index') }}">Historical reconciliation</a>
@endcan
```

## Checks

- Compare actual record counts and cent-accurate sums to original encrypted plan: 18 loans, 18 disbursements, 30 contributions, 17 repayments, 15 borrower interest credits, and review evidence.
- Compare the principal/interest allocation split to actual repay receipts; check that interest beneficiaries are the original borrowing members.
- Verify original ID-map and imported loan contract identity/quoted amounts.
- Check journal double-entry balances and duplicate `source_type:source_id` pairs.
- Verify net balances of `MEMBER_SAVINGS`, `LOAN_PRINCIPAL_RECEIVABLE`, `MEMBER_INTEREST_PAYABLE`, and `LEGACY_IMPORT_CLEARING`; ensure no bank/cash/mobile-money journal lines were created for this imported cycle.
- Display the 30 pending contribution allocations, 5 penalty review records, and open-loan positions **without treating them as available cash or approved payouts**.

## Expected for batch #3

- Contributions: M16,550
- Loan disbursement: M34,400
- Loan repayments: M29,640
- Interest credit component included in repayments: M4,940
- Principal repayment: M24,700
- Loan principal receivable debit after recorded repayments: M9,700
- Legacy clearing net debit: M11,790 (**unverified bank cash**)
- Savings liability credit: M16,550
- Borrower interest liability credit: M4,940
- Open original contract balances: M12,084, including M444 in unverified assessed/remaining penalties and M1,940 uncollected contractual interest.
- Penalty transactions requiring collected-cash evidence: 3 totaling M240; these are **not included** in ledger income.

## Important qualifications

- **A passing audit does not mean the cycle is ready for payouts or closing.** Clearing bank evidence, historical contribution period allocation, pending penalty evidence and outstanding loans remain separate close gates.
- Do **not** update historical payment `amount` to allocate a period. A future audited allocation workflow must link an existing receipt to a legitimate obligation without posting money a second time.
- Do **not** settle outstanding loans just to make the dashboard show zero. Never net them against member entitlements automatically.
- The database import was confirmed by your terminal output, but this package has not run against your local Laragon database. PHP syntax was validated in the build environment. Please run the CLI audit and inspect the output before relying on the dashboard.
- Sensitive member finance data are visible on the page. The controller enforces `society.manage` for the active society; do not remove this guard.

## Troubleshooting

- If `No historical financial import exists`: verify the selected society and that `legacy_financial_import_runs` has a row.
- If `Original financial plan...`: ensure the encrypted plan file is still present, and your APP_KEY hasn't changed.
- If the account code check fails: inspect existing account codes; do not create duplicate liability accounts simply to suppress it.
- If a journal balance fails: preserve the original records and reconcile; do not re-run financial importer to try to fix it.
