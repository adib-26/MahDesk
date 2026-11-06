<?php

namespace App\Http\Controllers\Api;

use App\Auth\PasswordVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AccessTokenController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private AuditLogger $audit,
        private PasswordVerifier $passwords,
    ) {
    }

    /**
     * Exchange verified credentials (and a second factor, when enabled) for a
     * short-lived Sanctum personal access token.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->passwords->verify($validated['email'], $validated['password']);

        if ($user === null) {
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

        $this->audit->record(
            'token.created',
            $user,
            null,
            $user,
            ['device_name' => $validated['device_name']],
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
        $token = $request->user()->currentAccessToken();

        // Cookie-authenticated (SPA) requests carry a transient token with nothing to revoke.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(null, 204);
    }
}
