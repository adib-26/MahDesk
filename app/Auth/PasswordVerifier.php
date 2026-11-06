<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The one place that checks an email + password pair. Used by the web login
 * and by API token issuing.
 *
 *  - Constant work: when no account has the email, a dummy hash is still
 *    checked, so response time doesn't reveal which emails are registered.
 *    (Laravel's Auth::validate() returns immediately for unknown emails.)
 *  - Hash upgrades: after a correct password, an outdated hash (bcrypt, or
 *    Argon2id with lower costs) is replaced with a current Argon2id hash.
 */
class PasswordVerifier
{
    /** Hash of a random string, computed with the current settings so it costs the same as a real check. */
    private static ?string $dummyHash = null;

    public function verify(string $email, #[\SensitiveParameter] string $password): ?User
    {
        $user = User::query()->where('email', Str::lower($email))->first();

        if ($user === null) {
            Hash::check($password, self::dummyHash());

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        if (Hash::needsRehash($user->password)) {
            // The "hashed" cast hashes the plain value with the current driver.
            $user->forceFill(['password' => $password])->saveQuietly();
        }

        return $user;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }
}
