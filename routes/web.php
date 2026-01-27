<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SocietyController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RepaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\YearEndController;
use App\Http\Controllers\CycleController;
use App\Http\Controllers\HomeController;

Auth::routes();

/*
|--------------------------------------------------------------------------
| Password Reset Routes
|--------------------------------------------------------------------------
*/
Route::post('/forgot-password', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    
    \Password::sendResetLink($request->only('email'));
    
    return back()->with('status', 'Password reset link sent!');
})->middleware('guest')->name('password.email');

Route::get('/reset-password/{token}', function ($token) {
    return view('auth.reset-password', ['token' => $token]);
})->middleware('guest')->name('password.reset');

Route::post('/reset-password', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
        'token' => 'required',
    ]);

    $status = \Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        }
    );

    return $status === \Password::PASSWORD_RESET
        ? redirect()->route('login')->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
})->middleware('guest')->name('password.update');

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Society Switching
    |--------------------------------------------------------------------------
    */
    Route::post('/switch-society', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'society_id' => 'required|exists:societies,id',
        ]);

        $society = \App\Models\Society::findOrFail($request->society_id);

        abort_unless(auth()->user()->isMemberOf($society), 403);

        session([
            'current_society_id' => $society->id,
            'current_cycle_id'   => null, // reset cycle when switching
        ]);

        return redirect()->route('societies.dashboard', $society);
    })->name('societies.switch');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Society Management
    |--------------------------------------------------------------------------
    */
    Route::resource('societies', SocietyController::class)->except('show');

    Route::get('/societies/{society}/dashboard', [SocietyController::class, 'dashboard'])
        ->name('societies.dashboard');

    Route::get('/societies/{society}/settings', [SocietyController::class, 'settings'])
        ->name('societies.settings');

    Route::put('/societies/{society}/settings', [SocietyController::class, 'updateSettings'])
        ->name('societies.settings.update');

    /*
    |--------------------------------------------------------------------------
    | Society Scoped Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('societies/{society}')
        ->name('societies.')
        ->group(function () {

            /*
            |--------------------------------------------------------------------------
            | Cycles (Chairman only)
            |--------------------------------------------------------------------------
            */
            Route::get('/cycles', [CycleController::class, 'index'])->name('cycles.index');
            Route::get('/cycles/create', [CycleController::class, 'create'])->name('cycles.create');
            Route::post('/cycles', [CycleController::class, 'store'])->name('cycles.store');
            Route::get('/cycles/{cycle}', [CycleController::class, 'show'])->name('cycles.show');
            
            Route::put('/cycles/{cycle}/close', [CycleController::class, 'close'])->name('cycles.close');

            /*
            |--------------------------------------------------------------------------
            | Members
            |--------------------------------------------------------------------------
            */
            Route::get('/members', [MemberController::class, 'index'])->name('members.index');
            Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
            Route::post('/members', [MemberController::class, 'store'])->name('members.store');
            Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
            Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

            Route::put('/members/{member}/role', [MemberController::class, 'updateRole'])
                ->name('members.update-role');

            Route::put('/members/{member}/status', [MemberController::class, 'toggleStatus'])
                ->name('members.toggle-status');

            /*
            |--------------------------------------------------------------------------
            | Contributions (Active Cycle)
            |--------------------------------------------------------------------------
            */
            Route::prefix('contributions')->name('contributions.')->group(function () {
                Route::get('/', [ContributionController::class, 'index'])->name('index');
                Route::get('/create', [ContributionController::class, 'create'])->name('create');
                Route::post('/', [ContributionController::class, 'store'])->name('store');
                Route::get('/{transaction}', [ContributionController::class, 'show'])->name('show');
            });

            /*
            |--------------------------------------------------------------------------
            | Loans (Active Cycle)
            |--------------------------------------------------------------------------
            */
            Route::prefix('loans')->name('loans.')->group(function () {
                Route::get('/', [LoanController::class, 'index'])->name('index');
                Route::get('/create', [LoanController::class, 'create'])->name('create');
                Route::post('/', [LoanController::class, 'store'])->name('store');
                Route::get('/{loan}', [LoanController::class, 'show'])->name('show');

                Route::put('/{loan}/write-off', [LoanController::class, 'writeOff'])->name('write-off');
                Route::put('/{loan}/reactivate', [LoanController::class, 'reactivate'])->name('reactivate');
            });

            /*
            |--------------------------------------------------------------------------
            | Repayments (Active Cycle)
            |--------------------------------------------------------------------------
            */
            Route::prefix('repayments')->name('repayments.')->group(function () {
                Route::get('/', [RepaymentController::class, 'index'])->name('index');
                Route::get('/create', [RepaymentController::class, 'create'])->name('create');
                Route::post('/', [RepaymentController::class, 'store'])->name('store');
                Route::get('/{transaction}', [RepaymentController::class, 'show'])
                    ->name('show');
            });

            /*
            |--------------------------------------------------------------------------
            | Reports (Cycle-aware)
            |--------------------------------------------------------------------------
            */
            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/summary', [ReportController::class, 'summary'])->name('summary');
                Route::get('/members', [ReportController::class, 'members'])->name('members');
                Route::get('/transactions', [ReportController::class, 'transactions'])->name('transactions');
                Route::get('/loans', [ReportController::class, 'loans'])->name('loans');

                Route::get('/member/{member}', [ReportController::class, 'memberStatement'])
                    ->name('member-statement');
            });

            /*
            |--------------------------------------------------------------------------
            | Year-End (Closes Cycle)
            |--------------------------------------------------------------------------
            */
            Route::prefix('year-end')->name('year-end.')->group(function () {
                // Live projection during active cycle (accessible by all members)
                Route::get('/projection', [YearEndController::class, 'projection'])
                    ->name('projection');
                
                // Preview after cycle ends (chairman only)
                Route::get('/preview', [YearEndController::class, 'preview'])
                    ->name('preview');
                
                // Process settlement (chairman only)
                Route::post('/process', [YearEndController::class, 'process'])
                    ->name('process');
                
                // View history of past settlements
                Route::get('/history', [YearEndController::class, 'history'])
                    ->name('history');
            });

        });

});