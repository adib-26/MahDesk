<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public const PENDING_SECRET_SESSION_KEY = 'two_factor.pending_secret';

    public const RECOVERY_CODES_SESSION_KEY = 'two_factor.recovery_codes';

    public function __construct(private Google2FA $google2fa) {}

    /**
     * Generate a base32 secret suitable for an authenticator application.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Build the provisioning URI an authenticator application can import.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'OmniDesk'),
            $user->email,
            $secret,
        );
    }

    /**
     * Verify a six-digit, time-based one-time password, allowing one adjacent time window.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $code, 1) !== false;
    }

    /**
     * Generate display-ready recovery codes. Plain-text values must only be shown once.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $value = strtoupper(bin2hex(random_bytes(8)));
            $code = implode('-', str_split($value, 4));

            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    /**
     * Hash each recovery code before it is stored in the encrypted user column.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_values(array_map(
            fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)),
            $codes,
        ));
    }

    /**
     * Consume a recovery code exactly once.
     *
     * The row lock prevents two concurrent requests from redeeming the same code.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalizedCode = $this->normalizeRecoveryCode($code);

        if (strlen($normalizedCode) !== 16) {
            return false;
        }

        return DB::transaction(function () use ($user, $normalizedCode): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

            if (! $lockedUser?->hasTwoFactorEnabled()) {
                return false;
            }

            $storedCodes = $lockedUser->two_factor_recovery_codes;

            if (! is_array($storedCodes)) {
                return false;
            }

            foreach ($storedCodes as $index => $hash) {
                if (! is_string($hash) || ! Hash::check($normalizedCode, $hash)) {
                    continue;
                }

                unset($storedCodes[$index]);
                $remainingCodes = array_values($storedCodes);

                $lockedUser->forceFill([
                    'two_factor_recovery_codes' => $remainingCodes,
                ])->save();

                $user->setAttribute('two_factor_recovery_codes', $remainingCodes);

                return true;
            }

            return false;
        });
    }

    /**
     * Accept recovery codes with or without visual separators.
     */
    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', $code));
    }
}
