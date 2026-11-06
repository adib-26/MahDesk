<?php

namespace App\Hashing;

use Illuminate\Hashing\Argon2IdHasher;

/**
 * Argon2id password hasher that still accepts older bcrypt hashes.
 *
 * New passwords are always hashed with Argon2id, which OWASP recommends first
 * and which has no input length cap (bcrypt silently ignores everything after
 * 72 bytes). Existing bcrypt hashes keep working, and needsRehash() reports
 * them as outdated, so App\Auth\PasswordVerifier swaps each one for an
 * Argon2id hash the next time that user signs in.
 *
 * Laravel's stock Argon2id hasher refuses bcrypt hashes when "verify" is on,
 * which would lock out every existing user after switching algorithms.
 */
class UpgradingArgon2IdHasher extends Argon2IdHasher
{
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        if (is_string($hashedValue) && $this->isBcrypt($hashedValue)) {
            return password_verify((string) $value, $hashedValue);
        }

        return parent::check($value, $hashedValue, $options);
    }

    /** True for bcrypt hashes and for Argon2id hashes made with older cost settings. */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        return $this->isBcrypt((string) $hashedValue) || parent::needsRehash($hashedValue, $options);
    }

    /**
     * Used by the "hashed" model cast. Bcrypt hashes are accepted so existing
     * rows stay readable until they are upgraded.
     */
    public function verifyConfiguration($value): bool
    {
        return $this->isBcrypt((string) $value) || parent::verifyConfiguration($value);
    }

    private function isBcrypt(string $hash): bool
    {
        return (password_get_info($hash)['algoName'] ?? null) === 'bcrypt';
    }
}
