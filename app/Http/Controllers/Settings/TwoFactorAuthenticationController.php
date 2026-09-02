<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private AuditLogger $audit,
    ) {}

    /**
     * Display two-factor authentication settings without exposing persisted secrets.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $enabled = $user->hasTwoFactorEnabled();
        $pendingSecret = $enabled ? null : $request->session()->get(TwoFactorService::PENDING_SECRET_SESSION_KEY);

        return Inertia::render('settings/two-factor', [
            'twoFactorEnabled' => $enabled,
            'confirmedAt' => $user->two_factor_confirmed_at?->toIso8601String(),
            'setup' => is_string($pendingSecret) && $pendingSecret !== ''
                ? [
                    'manualKey' => $pendingSecret,
                    'otpauthUri' => $this->twoFactor->provisioningUri($user, $pendingSecret),
                ]
                : null,
            // This flash value is intentionally supplied only on this page and pulled once.
            'recoveryCodes' => $request->session()->pull(TwoFactorService::RECOVERY_CODES_SESSION_KEY, []),
        ]);
    }

    /**
     * Generate an enrollment secret and keep it only in the user's current session.
     */
    public function start(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Two-factor authentication is already enabled.');
        }

        $request->session()->put(
            TwoFactorService::PENDING_SECRET_SESSION_KEY,
            $this->twoFactor->generateSecret(),
        );

        return to_route('two-factor.edit');
    }

    /**
     * Confirm the pending authenticator setup and persist only encrypted / hashed values.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['bail', 'required', 'string', 'regex:/^\d{6}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return to_route('two-factor.edit')->with('error', 'Two-factor authentication is already enabled.');
        }

        $pendingSecret = $request->session()->get(TwoFactorService::PENDING_SECRET_SESSION_KEY);

        if (! is_string($pendingSecret) || $pendingSecret === '') {
            throw ValidationException::withMessages([
                'code' => 'Start two-factor setup again before entering a verification code.',
            ]);
        }

        if (! $this->twoFactor->verifyCode($pendingSecret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The authentication code is invalid. Check your authenticator app and try again.',
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $pendingSecret,
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(TwoFactorService::PENDING_SECRET_SESSION_KEY);

        return to_route('two-factor.edit')
            ->with(TwoFactorService::RECOVERY_CODES_SESSION_KEY, $recoveryCodes)
            ->with('success', 'Two-factor authentication is enabled. Save your recovery codes now.');
    }

    /**
     * Disable two-factor authentication after a current-password challenge.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->record('two_factor.disabled', $user, null, $user);

        $request->session()->forget([
            TwoFactorService::PENDING_SECRET_SESSION_KEY,
            TwoFactorService::RECOVERY_CODES_SESSION_KEY,
        ]);

        return to_route('two-factor.edit')->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Replace recovery codes after a current-password challenge and reveal them once.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 404);

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
        ])->save();

        return to_route('two-factor.edit')
            ->with(TwoFactorService::RECOVERY_CODES_SESSION_KEY, $recoveryCodes)
            ->with('success', 'Recovery codes have been regenerated. Save the new codes now.');
    }
}
