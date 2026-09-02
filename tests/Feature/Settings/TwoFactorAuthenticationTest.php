<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_begin_setup_and_only_the_pending_setup_exposes_a_secret(): void
    {
        $user = User::factory()->create();
        $pendingSecret = null;

        $response = $this
            ->actingAs($user)
            ->post(route('two-factor.start'));

        $response
            ->assertRedirect(route('two-factor.edit'))
            ->assertSessionHas(TwoFactorService::PENDING_SECRET_SESSION_KEY, function ($secret) use (&$pendingSecret) {
                $pendingSecret = $secret;

                return is_string($secret) && $secret !== '';
            });

        $this->assertIsString($pendingSecret);

        $this
            ->actingAs($user)
            ->get(route('two-factor.edit'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('component', 'settings/two-factor')
            ->assertJsonPath('props.twoFactorEnabled', false)
            ->assertJsonPath('props.setup.manualKey', $pendingSecret)
            ->assertJsonMissingPath('props.auth.user.two_factor_secret')
            ->assertJsonMissingPath('props.auth.user.two_factor_recovery_codes');
    }

    public function test_user_can_confirm_a_valid_totp_and_recovery_codes_are_shown_once(): void
    {
        $user = User::factory()->create();
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $recoveryCodes = [];

        $response = $this
            ->actingAs($user)
            ->withSession([TwoFactorService::PENDING_SECRET_SESSION_KEY => $secret])
            ->post(route('two-factor.confirm'), ['code' => $code]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('two-factor.edit'))
            ->assertSessionMissing(TwoFactorService::PENDING_SECRET_SESSION_KEY)
            ->assertSessionHas(TwoFactorService::RECOVERY_CODES_SESSION_KEY, function ($codes) use (&$recoveryCodes) {
                $recoveryCodes = $codes;

                return is_array($codes) && count($codes) === 8;
            });

        $user->refresh();

        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertIsArray($user->two_factor_recovery_codes);
        $this->assertTrue(Hash::check(str_replace('-', '', $recoveryCodes[0]), $user->two_factor_recovery_codes[0]));

        $this
            ->actingAs($user)
            ->get(route('two-factor.edit'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('props.recoveryCodes', $recoveryCodes);

        $this
            ->actingAs($user)
            ->get(route('two-factor.edit'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('props.recoveryCodes', []);
    }

    public function test_invalid_totp_does_not_enable_two_factor_authentication(): void
    {
        $user = User::factory()->create();
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $invalidCode = '000000';

        while ($twoFactor->verifyCode($secret, $invalidCode)) {
            $invalidCode = str_pad((string) ((int) $invalidCode + 1), 6, '0', STR_PAD_LEFT);
        }

        $response = $this
            ->actingAs($user)
            ->withSession([TwoFactorService::PENDING_SECRET_SESSION_KEY => $secret])
            ->from(route('two-factor.edit'))
            ->post(route('two-factor.confirm'), ['code' => $invalidCode]);

        $response
            ->assertSessionHasErrors('code')
            ->assertRedirect(route('two-factor.edit'));

        $user->refresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_disabling_two_factor_requires_the_current_password_and_clears_all_values(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this
            ->actingAs($user)
            ->from(route('two-factor.edit'))
            ->delete(route('two-factor.disable'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('two-factor.edit'));

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this
            ->actingAs($user)
            ->delete(route('two-factor.disable'), ['current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('two-factor.edit'));

        $user->refresh();

        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_recovery_codes_require_the_current_password_and_are_replaced_once_confirmed(): void
    {
        $user = User::factory()->create();
        $oldCodes = $this->enableTwoFactor($user);

        $this
            ->actingAs($user)
            ->from(route('two-factor.edit'))
            ->post(route('two-factor.recovery-codes.regenerate'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('two-factor.edit'));

        $this->assertTrue(Hash::check(str_replace('-', '', $oldCodes[0]), $user->fresh()->two_factor_recovery_codes[0]));

        $newCodes = [];

        $this
            ->actingAs($user)
            ->post(route('two-factor.recovery-codes.regenerate'), ['current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('two-factor.edit'))
            ->assertSessionHas(TwoFactorService::RECOVERY_CODES_SESSION_KEY, function ($codes) use (&$newCodes) {
                $newCodes = $codes;

                return is_array($codes) && count($codes) === 8;
            });

        $this->assertTrue(Hash::check(str_replace('-', '', $newCodes[0]), $user->fresh()->two_factor_recovery_codes[0]));
        $this->assertFalse(Hash::check(str_replace('-', '', $oldCodes[0]), $user->fresh()->two_factor_recovery_codes[0]));

        $this
            ->actingAs($user)
            ->get(route('two-factor.edit'), $this->inertiaHeaders())
            ->assertJsonPath('props.recoveryCodes', $newCodes);

        $this
            ->actingAs($user)
            ->get(route('two-factor.edit'), $this->inertiaHeaders())
            ->assertJsonPath('props.recoveryCodes', []);
    }

    /**
     * @return list<string>
     */
    private function enableTwoFactor(User $user): array
    {
        $twoFactor = app(TwoFactorService::class);
        $codes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $twoFactor->generateSecret(),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
        ];
    }
}
