<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor)
    {
    }

    /**
     * Prompt a user who has completed password authentication for their second factor.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return $this->restartLogin($request);
        }

        return Inertia::render('auth/two-factor-challenge', [
            'canUseRecoveryCode' => is_array($user->two_factor_recovery_codes)
                && count($user->two_factor_recovery_codes) > 0,
        ]);
    }

    /**
     * Complete sign-in after a valid authenticator or one-time recovery code.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:64', 'required_without:code'],
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return $this->restartLogin($request);
        }

        $valid = ! empty($validated['code'])
            ? $this->twoFactor->verifyCode($user->two_factor_secret, $validated['code'])
            : $this->twoFactor->consumeRecoveryCode($user, (string) $validated['recovery_code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                ! empty($validated['code']) ? 'code' : 'recovery_code' => [
                    ! empty($validated['code'])
                        ? 'The authentication code is invalid. Check your authenticator app and try again.'
                        : 'That recovery code is invalid or has already been used.',
                ],
            ]);
        }

        $remember = (bool) $request->session()->pull('auth.two_factor_remember', false);
        $request->session()->forget('auth.two_factor_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if (! is_numeric($userId)) {
            return null;
        }

        $user = User::find($userId);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function restartLogin(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'auth.two_factor_user_id',
            'auth.two_factor_remember',
        ]);

        return to_route('login')->with('error', 'Your sign-in session expired. Please enter your email and password again.');
    }
}
