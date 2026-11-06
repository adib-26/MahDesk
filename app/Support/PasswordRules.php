<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Password policy, following NIST SP 800-63B and OWASP:
 *
 *  - at least 12 characters; length matters far more than character classes
 *  - at most 128 characters (keeps hashing cost bounded)
 *  - no forced mix of upper case, digits or symbols, which mostly produces
 *    predictable patterns like "Password1!"
 *  - in production, reject passwords found in known data breaches, using the
 *    free Have I Been Pwned range API. Only the first 5 characters of the
 *    password's SHA-1 hash leave the server (k-anonymity), never the password.
 *    Set PASSWORD_CHECK_BREACHED=false to turn this off, e.g. without internet.
 */
final class PasswordRules
{
    public const MIN = 12;

    public const MAX = 128;

    public static function make(): Password
    {
        $rule = Password::min(self::MIN)->max(self::MAX);

        if (config('auth.password_check_breached')) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
