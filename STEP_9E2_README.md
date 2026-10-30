# Mokhatlo 2.0 — Step 9E.2: Historical Obligation Reconciliation

**Source audit:** Cycle #2, 31/31 checks PASS, 30 fully evidenced historical contribution receipts (M16,550), 30 not yet linked to monthly obligations. The original ledger imported 65 balanced journals. This package does NOT repost cash or journal entries.

## What it does

- Adds a **many-to-many reviewed bridge** between `legacy_receipt_period_attributions` (Step 9E.1), `contribution_payments`, and `contribution_obligations`.
- A single original receipt can span months, and multiple receipts can fund one monthly obligation.
- Link to an existing obligation only when society, cycle, member, and month match, with evidence.
- If an obligation does not exist, an administrator may explicitly create it **only by supplying the historically verified required monthly amount, due date, and supporting reference**. The amount due is NEVER inferred from a payment amount or the current policy.
- Supports link reversals with actor, timestamp, and written reason. A linked E1 attribution cannot be reversed until the E2 link is first reversed.
- All write actions require `society.manage`, the active society, and a draft, unapproved historical cycle; transactions lock society and receipt rows. No new loan, receipt, penalty, journal, interest credit, or bank posting is created.
- Updates the Step 9D audit's **Unallocated historical contributions** review gate from a raw FK-null count to verified summed link coverage. Its original FK-integrity check is retained under a clearer name; another check flags orphan, duplicate, cross-member, reversed or over-allocated obligation links.

**Important:** A historical receipt's `contribution_payments.contribution_obligation_id` remains NULL intentionally. One column cannot represent one receipt paying multiple months. Existing ordinary contribution compliance/reporting code may still inspect that one column. You MUST later adapt those reports, monthly compliance rules and overdue-penalty calculations to use the new bridge. **Do not enable automated historical penalty assessments, payout approval, or cycle closure after this step alone.** Also, reviewing only paid months does not establish an obligation for months with no historical receipt; verify any missing required months separately.

## Install

1. **Back up `mkhtlo_db` and relevant code.** This package is designed from schema information and existing Step 9C/9D/9E.1 packages, but cannot be executed against your local Laragon DB here. Test on a development/staging copy first.
2. Merge the package's `app/`, `database/`, `resources/`, and `tests/` directories into `C:\laragon\www\mkhtlo`. Back up `app/Services/Legacy/AuditLegacyFinancialImport.php` and `app/Services/Legacy/ReviewLegacyReceiptPeriods.php` before replacement. The replacements are supplied in full and are based on **Step 9E.1** versions.
3. Add routes from `routes/STEP9E2_ROUTE_SNIPPET.txt` **inside the existing authenticated/verified/society middleware group**. Alternatively, review the optional `routes/web_STEP9E2_COMPLETE_REFERENCE.php` based on your prior `web.php` and merge selectively. Do not blindly replace an updated local `web.php`.
4. Run:

   ```powershell
   php artisan optimize:clear
   php artisan migrate
   php artisan test --filter=LegacyObligationReviewRulesTest
   php artisan route:list --name=legacy.obligations
   php artisan mokhatlo:legacy:audit --society=1
   ```

5. Browse `http://mkhtlo.test/legacy/reconciliation/obligations` as a logged-in society manager. For each reviewed period, first match any existing obligation. If none exists, create one with the **historically verified minimum and due date** plus an evidence reference. If evidence is unavailable, leave it pending. Never enter an arbitrary monthly minimum simply to clear the gate.
6. After each batch of reviews, rerun `php artisan mokhatlo:legacy:audit --society=1`. The new **Historical obligation link inconsistencies** check must stay 0, original money checks must PASS, and the **Unallocated historical contributions** gate should decline from 30 as entire receipts become linked.

## Notes and expected results

- There are **30 original receipts** but potentially **more than 30 month attributions**: count monthly links separately from original receipts.
- Each original receipt is considered obligation-linked only after ALL its active period-attributed portions sum to the original receipt amount through valid links.
- 18 loans, 17 repayments, 15 interest credits, 65 journal headers, M16,550 savings liability, M4,940 interest liability, M9,700 principal receivable and M11,790 legacy clearing should remain unchanged.
- 5 penalty reviews, M12,084 contractual outstanding loans, and independently unverified clearing remain unresolved.
- New obligations may affect other application reports even without posting money. Do not retroactively generate old penalties until historical policy, grace periods and whether funds arrived before deadlines have been reviewed.

## Troubleshooting

- **404:** `php artisan route:list --name=legacy.obligations`, confirm active society #1 and a matching import run.
- **403:** authenticated user needs `society.manage` scoped to the current society.
- **No historical obligations:** expected for an imported draft cycle; enter genuine historical monthly terms with evidence. Never use receipt amount as the policy minimum.
- **Attribution is already linked:** reverse Step 9E.2 link before reversing the original period evidence.
- **Duplicate obligation:** check `contribution_obligations` for duplicate member/cycle/period and resolve with a reviewed plan; do not force the link.
- **Audit link discrepancies:** do not close cycle, post payouts or manually change journal lines. Inspect `legacy_receipt_obligation_links` and receipt/member/period mappings.

## Future Step 9E.3

- Adapt the ordinary contribution compliance/penalty calculation and statement queries to read verified many-to-many allocations (and conventional direct-FK receipts) **without double counting**.
- Assess missing months with evidence-backed historical minimum/due/grace rules; no automatic penalty generation from inferred defaults.
- Resolve penalty collections and verify legacy clearing with independent cash/bank/mobile evidence.
