# Mokhatlo 2.0 — Step 9D missing review table fix

## The problem
The Step 9D auditor references `legacy_contribution_reviews`, which was not created by the Step 9C importer variant that successfully imported the user's 30 historical contributions. Their genuine receipt records already live in `contribution_payments`, with original transaction IDs mapped in `legacy_record_maps`.

## The fix
Replace **only** `app/Services/Legacy/AuditLegacyFinancialImport.php` with the file in this archive. The auditor now derives the historical contribution review queue from `legacy_record_maps` joined to `contribution_payments`, scoped to the import's original source and target society/cycle. A null `contribution_obligation_id` indicates pending allocation. It additionally validates the original transaction ID, amount, member, and payment date individually against the encrypted original financial plan.

**No database migrations, new financial postings, transaction re-imports, or new review tables are required.** No live data is mutated by this fix.

## Install
1. Back up the current service PHP file.
2. Copy the bundled `app/Services/Legacy/AuditLegacyFinancialImport.php` into the same path beneath `C:\laragon\www\mkhtlo`.
3. From the Laravel root:
   ```powershell
   php artisan optimize:clear
   php artisan mokhatlo:legacy:audit --society=1
   ```
4. Then refresh `/legacy/reconciliation` after the audit runs.

Expected history for the source import (verify against actual output): 18 loans, 18 disbursements, 30 contribution receipts, 17 repayments, 15 interest credits, 5 penalty review records. The contribution-allocation queue should show 30 pending receipts. Financial audit checks must pass; the outstanding penalty questions and clearing balance remain unresolved.

Do not run `mokhatlo:legacy:import --apply` a second time to repair the dashboard. Do not create an empty `legacy_contribution_reviews` table; doing that would merely conceal the error and cause the auditor to report 30 missing review rows.
