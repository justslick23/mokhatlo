<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyContributionAttributionController;
use App\Http\Controllers\LegacyContributionObligationController;
use App\Http\Controllers\LegacyReconciliationController;
use App\Http\Controllers\SocietyController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'societies.index' : 'login');
});

/*
|--------------------------------------------------------------------------
| Guest Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:8,1')->name('login.attempt');

    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1')->name('register.attempt');

    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])
        ->middleware('throttle:5,1')->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email verification routes are accessible before email verification.
    Route::get('/email/verify', function () {
        return view('auth.verify');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()
            ->intended(route('societies.index'))
            ->with('status', 'Email verified successfully.');
    })->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link has been sent.');
    })->middleware('throttle:6,1')
        ->name('verification.send');

    /*
    |----------------------------------------------------------------------
    | Verified User Routes (Society discovery and invitations)
    |----------------------------------------------------------------------
    */
    Route::middleware('verified')->group(function () {
        Route::get('/societies', [SocietyController::class, 'index'])
            ->name('societies.index');
        Route::get('/societies/create', [SocietyController::class, 'create'])
            ->name('societies.create');
        Route::post('/societies', [SocietyController::class, 'store'])
            ->middleware('throttle:10,1')->name('societies.store');
        Route::post('/societies/{society}/switch', [SocietyController::class, 'switch'])
            ->name('societies.switch');

        // Accepting an invitation must not require an existing society membership.
        Route::get('/invitations/{token}', [
            \App\Http\Controllers\SocietyInvitationController::class, 'show',
        ])->name('invitations.show');

        Route::post('/invitations/{token}/accept', [
            \App\Http\Controllers\SocietyInvitationController::class, 'accept',
        ])->middleware('throttle:10,1')->name('invitations.accept');

        /*
        |------------------------------------------------------------------
        | Routes Requiring an Active Society
        |------------------------------------------------------------------
        | The 'society' middleware resolves currentSociety and sets the
        | Spatie permission-team context for the active membership.
        */
        Route::middleware('society')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');

            // Members and invitations
            Route::get('/members', [
                \App\Http\Controllers\MemberController::class, 'index',
            ])->name('members.index');

            Route::get('/members/invite', [
                \App\Http\Controllers\SocietyInvitationController::class, 'create',
            ])->name('members.invite');

            Route::post('/members/invite', [
                \App\Http\Controllers\SocietyInvitationController::class, 'store',
            ])->middleware('throttle:10,1')->name('members.invite.store');

            // Savings cycles
            Route::get('/cycles', [
                \App\Http\Controllers\CycleController::class, 'index',
            ])->name('cycles.index');

            Route::get('/cycles/create', [
                \App\Http\Controllers\CycleController::class, 'create',
            ])->name('cycles.create');

            Route::post('/cycles', [
                \App\Http\Controllers\CycleController::class, 'store',
            ])->name('cycles.store');

            Route::post('/cycles/{cycle}/activate', [
                \App\Http\Controllers\CycleController::class, 'activate',
            ])->whereNumber('cycle')->name('cycles.activate');

            // Savings contributions
            Route::get('/contributions', [
                \App\Http\Controllers\ContributionController::class, 'index',
            ])->name('contributions.index');

            Route::get('/contributions/{obligation}/record', [
                \App\Http\Controllers\ContributionController::class, 'create',
            ])->whereNumber('obligation')->name('contributions.create');

            Route::post('/contributions/{obligation}/record', [
                \App\Http\Controllers\ContributionController::class, 'store',
            ])->whereNumber('obligation')->middleware('throttle:20,1')
                ->name('contributions.store');

            // Contribution penalties
            Route::get('/penalties', [
                \App\Http\Controllers\ContributionPenaltyController::class, 'index',
            ])->name('penalties.index');

            Route::get('/penalties/{penalty}', [
                \App\Http\Controllers\ContributionPenaltyController::class, 'show',
            ])->whereNumber('penalty')->name('penalties.show');

            Route::post('/penalties/{penalty}/payments', [
                \App\Http\Controllers\ContributionPenaltyController::class, 'recordPayment',
            ])->whereNumber('penalty')->middleware('throttle:20,1')
                ->name('penalties.payments.store');

            Route::post('/penalties/{penalty}/waivers', [
                \App\Http\Controllers\ContributionPenaltyController::class, 'waive',
            ])->whereNumber('penalty')->middleware('throttle:10,1')
                ->name('penalties.waivers.store');

            // Loan management
            Route::get('/loans', [
                \App\Http\Controllers\LoanController::class, 'index',
            ])->name('loans.index');

            Route::get('/loans/create', [
                \App\Http\Controllers\LoanController::class, 'create',
            ])->name('loans.create');

            Route::post('/loans', [
                \App\Http\Controllers\LoanController::class, 'store',
            ])->middleware('throttle:10,1')->name('loans.store');

            Route::get('/loans/{loan}', [
                \App\Http\Controllers\LoanController::class, 'show',
            ])->whereNumber('loan')->name('loans.show');

            Route::post('/loans/{loan}/approve', [
                \App\Http\Controllers\LoanController::class, 'approve',
            ])->whereNumber('loan')->middleware('throttle:10,1')
                ->name('loans.approve');

            Route::post('/loans/{loan}/reject', [
                \App\Http\Controllers\LoanController::class, 'reject',
            ])->whereNumber('loan')->middleware('throttle:10,1')
                ->name('loans.reject');

            // Loan disbursements and repayments
            Route::post('/loans/{loan}/disburse', [
                \App\Http\Controllers\LoanPaymentController::class, 'disburse',
            ])->whereNumber('loan')->middleware('throttle:10,1')
                ->name('loans.disburse');

            Route::get('/loans/{loan}/repayments/create', [
                \App\Http\Controllers\LoanPaymentController::class, 'createRepayment',
            ])->whereNumber('loan')->name('loans.repayments.create');

            Route::post('/loans/{loan}/repayments', [
                \App\Http\Controllers\LoanPaymentController::class, 'storeRepayment',
            ])->whereNumber('loan')->middleware('throttle:20,1')
                ->name('loans.repayments.store');

            // Loan penalties
            Route::get('/loan-penalties', [
                \App\Http\Controllers\LoanPenaltyController::class, 'index',
            ])->name('loans.penalties.index');

            Route::get('/loan-penalties/{penalty}', [
                \App\Http\Controllers\LoanPenaltyController::class, 'show',
            ])->whereNumber('penalty')->name('loans.penalties.show');

            Route::post('/loan-penalties/{penalty}/payments', [
                \App\Http\Controllers\LoanPenaltyController::class, 'pay',
            ])->whereNumber('penalty')->middleware('throttle:20,1')
                ->name('loans.penalties.pay');

            Route::post('/loan-penalties/{penalty}/waivers', [
                \App\Http\Controllers\LoanPenaltyController::class, 'waive',
            ])->whereNumber('penalty')->middleware('throttle:10,1')
                ->name('loans.penalties.waive');

            // Financial reporting and transaction history
            Route::get('/reports', [
                \App\Http\Controllers\FinancialReportController::class, 'index',
            ])->name('reports.index');

            Route::get('/reports/export', [
                \App\Http\Controllers\FinancialReportController::class, 'export',
            ])->name('reports.export');

            Route::get('/reports/member-statement', [
                \App\Http\Controllers\MemberStatementController::class, 'show',
            ])->name('reports.member-statement');

            Route::get('/reports/member-statement/export', [
                \App\Http\Controllers\MemberStatementController::class, 'export',
            ])->name('reports.member-statement.export');

            Route::get('/transactions', [
                \App\Http\Controllers\FinancialTransactionController::class, 'index',
            ])->name('transactions.index');

            // Cycle payout draft, reconciliation and approval
            Route::get('/payouts', [
                \App\Http\Controllers\CyclePayoutController::class, 'index',
            ])->name('payouts.index');

            Route::post('/payouts/drafts', [
                \App\Http\Controllers\CyclePayoutController::class, 'store',
            ])->middleware('throttle:10,1')->name('payouts.drafts.store');

            Route::get('/payouts/drafts/{draft}', [
                \App\Http\Controllers\CyclePayoutController::class, 'show',
            ])->whereNumber('draft')->name('payouts.drafts.show');

            Route::post('/payouts/drafts/{draft}/reconcile', [
                \App\Http\Controllers\CyclePayoutController::class, 'reconcile',
            ])->whereNumber('draft')->middleware('throttle:10,1')
                ->name('payouts.drafts.reconcile');

            Route::post('/payouts/drafts/{draft}/approve', [
                \App\Http\Controllers\CyclePayoutController::class, 'approve',
            ])->whereNumber('draft')->middleware('throttle:5,1')
                ->name('payouts.drafts.approve');

            // Cycle payout settlement
            Route::post('/payouts/drafts/{draft}/start', [
                \App\Http\Controllers\CyclePayoutSettlementController::class, 'start',
            ])->whereNumber('draft')->middleware('throttle:5,1')
                ->name('payouts.drafts.start');

            Route::get('/payouts/batches/{batch}', [
                \App\Http\Controllers\CyclePayoutSettlementController::class, 'show',
            ])->whereNumber('batch')->name('payouts.batches.show');

            Route::post('/payouts/batches/{batch}/items/{item}/payments', [
                \App\Http\Controllers\CyclePayoutSettlementController::class, 'pay',
            ])->whereNumber('batch')->whereNumber('item')
                ->middleware('throttle:15,1')
                ->name('payouts.batches.items.pay');

            Route::post('/payouts/batches/{batch}/complete', [
                \App\Http\Controllers\CyclePayoutSettlementController::class, 'complete',
            ])->whereNumber('batch')->middleware('throttle:5,1')
                ->name('payouts.batches.complete');

            /*
            |--------------------------------------------------------------
            | Historical Import Reconciliation (Step 9D + Step 9E.1)
            |--------------------------------------------------------------
            | Both controllers enforce society.manage for the active society.
            */
            Route::get('/legacy/reconciliation', [
                LegacyReconciliationController::class, 'index',
            ])->name('legacy.reconciliation.index');

            Route::get('/legacy/reconciliation/contributions', [
                LegacyContributionAttributionController::class, 'index',
            ])->name('legacy.contributions.index');

            Route::post('/legacy/reconciliation/runs/{run}/receipts/{payment}/periods', [
                LegacyContributionAttributionController::class, 'store',
            ])->whereNumber('run')->whereNumber('payment')
                ->name('legacy.contributions.store');

            Route::post('/legacy/reconciliation/runs/{run}/receipts/{payment}/periods/{attribution}/reverse', [
                LegacyContributionAttributionController::class, 'reverse',
            ])->whereNumber('run')->whereNumber('payment')->whereNumber('attribution')
                ->name('legacy.contributions.reverse');

            // Step 9E.2 — Reviewed month to obligation linking
            Route::get('/legacy/reconciliation/obligations', [
                LegacyContributionObligationController::class, 'index',
            ])->name('legacy.obligations.index');

            Route::post('/legacy/reconciliation/obligations/runs/{run}/attributions/{attribution}/link', [
                LegacyContributionObligationController::class, 'link',
            ])->whereNumber('run')->whereNumber('attribution')
                ->middleware('throttle:15,1')->name('legacy.obligations.link');

            Route::post('/legacy/reconciliation/obligations/runs/{run}/attributions/{attribution}/create-link', [
                LegacyContributionObligationController::class, 'createAndLink',
            ])->whereNumber('run')->whereNumber('attribution')
                ->middleware('throttle:10,1')->name('legacy.obligations.create-link');

            Route::post('/legacy/reconciliation/obligations/runs/{run}/links/{link}/reverse', [
                LegacyContributionObligationController::class, 'reverse',
            ])->whereNumber('run')->whereNumber('link')
                ->middleware('throttle:10,1')->name('legacy.obligations.reverse');
        });
    });
});
