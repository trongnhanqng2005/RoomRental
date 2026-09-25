<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $identifier = $request->string('identifier')->toString();
        $identifierColumn = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([
            $identifierColumn => $identifier,
            'password' => $request->string('password')->toString(),
            'account_status' => 'ACTIVE',
        ])) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        RateLimiter::clear($key);

        /** @var User $user */
        $user = $request->user();
        $user->last_login_at = now();
        $user->save();

        if ($user->must_change_password) {
            return redirect()->route('password.change.required');
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function throttleKey(LoginRequest $request): string
    {
        return Str::lower($request->string('identifier')->toString()).'|'.$request->ip();
    }
}
