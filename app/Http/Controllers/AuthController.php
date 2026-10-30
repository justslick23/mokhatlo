<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)->mixedCase()->numbers(),
            ],
        ]);

        $user = User::create($data);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->forget('active_society_id');

        return redirect()->route('verification.notice');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('active_society_id');

        return redirect()->intended(
            route('societies.index')
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        setPermissionsTeamId(null);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotForm(): View
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink([
            'email' => Str::lower(trim($data['email'])),
        ]);

        return back()->with(
            'status',
            'If this email is registered, a reset link has been sent.'
        );
    }

    public function resetForm(
        Request $request,
        string $token
    ): View {
        return view('auth.reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(10)->mixedCase()->numbers(),
            ],
        ]);

        $data['email'] = Str::lower(trim($data['email']));

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Your password has been updated.');
        }

        return back()->withErrors([
            'email' => __($status),
        ]);
    }
}
