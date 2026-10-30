# Mokhatlo 2.0 — Step 9E.1 Historical contribution period attribution

## What this package does

Your Step 9D report passed 30 financial audit checks. However, **30 historical contribution receipts** (M16,550) are not linked to verified monthly obligations, and original receipt dates do not prove the months funded. This add-on lets the active society manager attach **reviewed, evidence-backed month attribution** to existing historical receipts, including splitting one receipt over multiple months. Every attribution uses integer cents, is subject to locking/over-allocation prevention, and has a permanent author, evidence reference, date and reversal trail.

**No transaction values change, no receipt is inserted, no obligation is invented, no general ledger is posted, and no penalties are generated.** This is classification evidence only. Step 9E.2 will validate the old cycle policies, construct real due obligations and settle them using recorded attribution, without receiving money again.

## Files to copy (merge only)

- `database/migrations/2026_10_29_220000_create_legacy_receipt_period_attributions.php` — one audit table
- `app/Services/Legacy/LegacyReceiptPeriodRules.php` — pure validation rules
- `app/Services/Legacy/ReviewLegacyReceiptPeriods.php` — transactional attribution/reversal
- `app/Http/Controllers/LegacyContributionAttributionController.php` — society-scoped GET/POST handlers
- `resources/views/legacy/period-attribution.blade.php` — Bootstrap UI
- `tests/Unit/LegacyReceiptPeriodRulesTest.php` — unit tests
- `app/Services/Legacy/AuditLegacyFinancialImport.php` — **optional but recommended** revised Step 9D audit service that retains previous checks and adds one new integrity check plus a new progress gate for period evidence. Back up the installed Step 9D file before replacing.
- `routes/STEP9E1_ROUTE_SNIPPET.txt` — copy the route declarations to `routes/web.php` manually. Do not overwrite your route file.

This upgrade is designed for your Step 9C3 and Step 9D *fixed* services, including table `ledger_lines`. It has **not** been tested on the user's local Laravel database; always use a backup/staging system first.

## Install

1. Back up `mkhtlo_db` and the existing `app/Services/Legacy/AuditLegacyFinancialImport.php`. Ensure the successful Step 9D audit result has been retained.
2. Copy the included files to `C:\laragon\www\mkhtlo` (merge folders). Optionally replace Step 9D audit service with the included extension.
3. Add the supplied routes to `routes/web.php`, within the existing `auth`, `verified`, `society` middleware.
4. Run:

   ```powershell
   php artisan optimize:clear
   php artisan migrate
   php artisan test --filter=LegacyReceiptPeriodRulesTest
   php artisan route:list --name=legacy.contributions
   php artisan mokhatlo:legacy:audit --society=1
   ```

5. Navigate to `http://mkhtlo.test/legacy/reconciliation/contributions` while signed in with `society.manage` permission in the active society.
6. Choose an imported receipt and **only the actual verified month** (not inferred from `paid_on`), a portion of its value, and a traceable evidence reference. The form allows separate review entries for a single receipt spanning multiple months.
7. Corrections use *Reverse* with a reason; old review rows remain in the audit table.
8. Rerun the Step 9D audit to confirm the original financial figures and journal balances remain unchanged. The added period-evidence gate should decrease as receipts become fully attributed.

## Important distinctions

- The original Step 9D gate **"Unallocated historical contributions"** will remain at 30 until actual `contribution_obligation_id` links have been reconciled in the subsequent obligation phase. This is intentional: a verified period attribution is not yet a verified due obligation or a posted receipt allocation.
- The new gate **"Receipts awaiting month-by-month evidence"** tracks progress in this add-on, without changing the original receipt count or journal.
- M11,790 in legacy clearing remains **unverified cash**, M240 in penalty transactions is **not proven collected**, and the original M12,084 contractual loans remain outstanding pending settlement. This package does not clear those gates.
- The 2026 historical cycle runs through **December 31, 2026**; do not close it early.
- Monetary rows must never be deleted or rewritten to correct attribution; reversals preserve the record.
- A logged-in user with `society.manage` in the current team-scoped society is required. Transactions verify matching source import run, society, cycle and original mapped receipt before allowing writes.

## Potential compatibility note

The `ReviewLegacyReceiptPeriods` service checks `cycle_payout_approvals` for approved distributions. That table exists in the previously implemented Step 8B payout module. If your installation lacks it, do **not** delete the guard: install/verify the payout-approval schema first, or explicitly adapt the guard to the actual installed approval tables. Review the integration with your ordinary contribution compliance reports before treating period attributions as fulfillment of monthly obligations; this step does not update the `contribution_obligation_id` reference.
