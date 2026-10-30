# Mokhatlo — Month-end bank balance in monthly PDFs

This update fixes the Society Monthly Update, replacing the misleading **M0.00** historical bank figure with a reconstructed balance when a documented, approved, posted **bank-only** legacy reconciliation allows it.

## Verified basis (your current Society #1)

- The approved bank-only reconciliation moved **M11,790** from `LEGACY_IMPORT_CLEARING` to `BANK_ACCOUNT` on **29 October 2026**.
- The historical transactions through 30 September total **M9,590** net cash movement.
- The October 1–29 net additional movement is **M2,200**.
- Reconstruct September: **M11,790 − M2,200 = M9,590**.

**The reconstructed figure assumes every imported cash movement passed through the bank, and that there are no unrecorded bank fees, transfers or other transactions.** It is calculated, not a separately verified September bank statement. If the reconciliation allocated any opening funds to physical cash or mobile money, if an unexplained clearing entry exists, or if there are multiple import reconciliations, the reconstruction deliberately does not run.

## Installation

1. Back up your Laravel project and database.
2. Extract the ZIP somewhere outside your Laravel project (e.g. `C:\laragon\patches\mokhatlo-bank-history`).
3. Copy the included `app`, `resources` and `tests` folders into `C:\laragon\www\mkhtlo`. The only existing files overwritten are:
   - `app/Services/MokhatloMail/MonthlyReportPositions.php`
   - `resources/views/pdf/mokhatlo-society.blade.php`
4. Run:

```powershell
php artisan optimize:clear
php artisan test --filter=HistoricalBankReverseMathTest
php artisan mokhatlo:reports:bank-history --society=1 --year=2026
php artisan mokhatlo:mail:pdf-preview --society=1 --cycle=2 --month=2026-09
php artisan mokhatlo:mail:pdf-preview --society=1 --cycle=2 --month=2026-10
```

The last two commands generate local PDF previews **only**. No transaction or ledger entries are created, and no emails are sent. Existing frozen PDF snapshots remain unchanged.

## What is changed

- **Bank balance at month-end**: shows bank ledger balance for periods on/after the bank opening; earlier balances are reconstructed backwards from the independently reviewed bank anchor, using original dated clearing entries.
- **Money held** in the historical summary uses that reconstructed bank amount plus any separately recorded cash/mobile balances (the reconstruction is disabled when these exist before the anchor).
- **No double counting**: `LEGACY_IMPORT_CLEARING` stays in the audit ledger and isn't added as a second line in the member-facing balance table.
- Raw `BANK_ACCOUNT`, `liquid_assets` and `LEGACY_IMPORT_CLEARING` remain **unchanged** for audit consistency.
- The personal report, estimated payouts and approved PDF visual style remain unchanged.

## Important notes

The `mokhatlo:reports:bank-history` preview displays month-ends **through the entire selected year**, including future months; only completed months or dates on/earlier than today are meaningful. If you ran new bank withdrawals or payments after the reconciliation, their correct dates are needed to compute the closing balances.

If a bank statement later shows that the historic amount differs from the reconstruction, correct the supporting records through a controlled reconciliation. Do **not** backdate the 29 October reconciliation journal just to change past reports.

The included SQL guards rely on the existing `legacy_clearing_reconciliations` and native ledger tables from your installed Mokhatlo import modules. Laravel integration and DomPDF rendering must be checked against your local database.
