<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_authentication_requires_a_second_factor_for_an_enrolled_user(): void
    {
        [$user] = $this->userWithTwoFactor();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('auth.two_factor_user_id', $user->id)
            ->assertSessionHas('auth.two_factor_remember', true);
    }

    public function test_valid_authenticator_code_completes_sign_in(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $response = $this->post(route('two-factor.challenge.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);

        $this->assertAuthenticatedAs($user);
        $response
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('auth.two_factor_user_id')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_valid_recovery_code_completes_sign_in_and_is_consumed(): void
    {
        [$user, , $recoveryCodes] = $this->userWithTwoFactor();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $response = $this->post(route('two-factor.challenge.store'), [
            'recovery_code' => $recoveryCodes[0],
        ]);

        $this->assertAuthenticatedAs($user);
        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $storedCodes = $user->fresh()->two_factor_recovery_codes;

        $this->assertCount(7, $storedCodes);
        $this->assertFalse(Hash::check(str_replace('-', '', $recoveryCodes[0]), $storedCodes[0]));
    }

    public function test_invalid_second_factor_does_not_complete_sign_in(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $twoFactor = app(TwoFactorService::class);
        $invalidCode = '000000';

        while ($twoFactor->verifyCode($secret, $invalidCode)) {
            $invalidCode = str_pad((string) ((int) $invalidCode + 1), 6, '0', STR_PAD_LEFT);
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge'));

        $response = $this
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.challenge.store'), ['code' => $invalidCode]);

        $this->assertGuest();
        $response
            ->assertSessionHasErrors('code')
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionHas('auth.two_factor_user_id', $user->id);
    }

    /**
     * @return array{0: User, 1: string, 2: list<string>}
     */
    private function userWithTwoFactor(): array
    {
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret, $recoveryCodes];
    }
}
