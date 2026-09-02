<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccessTokenController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor)
    {
    }

    /**
     * Exchange verified credentials (and a second factor, when enabled) for a
     * short-lived Sanctum personal access token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'two_factor_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Verify your email address before creating an API token.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $secondFactorValid = ! empty($validated['two_factor_code'])
                ? $this->twoFactor->verifyCode($user->two_factor_secret, $validated['two_factor_code'])
                : (! empty($validated['recovery_code'])
                    && $this->twoFactor->consumeRecoveryCode($user, $validated['recovery_code']));

            if (! $secondFactorValid) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['A valid authentication or recovery code is required.'],
                ]);
            }
        }

        $expiresAt = now()->addDays(30);
        $token = $user->createToken(
            $validated['device_name'],
            ['api:access'],
            $expiresAt,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }

    /**
     * Revoke the bearer token used for this request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }
}
