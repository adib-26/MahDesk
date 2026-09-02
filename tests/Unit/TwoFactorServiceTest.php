<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_valid_totp_secret_and_provisioning_uri(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $user = User::factory()->create();

        $this->assertTrue($service->verifyCode($secret, $code));
        $this->assertFalse($service->verifyCode($secret, 'not-a-code'));

        $uri = $service->provisioningUri($user, $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString('issuer=', $uri);
    }

    public function test_it_hashes_and_consumes_recovery_codes_once(): void
    {
        $service = app(TwoFactorService::class);
        $codes = $service->generateRecoveryCodes();
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => $service->generateSecret(),
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $storedCodes = $user->two_factor_recovery_codes;

        $this->assertIsArray($storedCodes);
        $this->assertCount(8, $storedCodes);
        $this->assertTrue(Hash::check(str_replace('-', '', $codes[0]), $storedCodes[0]));
        $this->assertNotSame($codes[0], $user->getRawOriginal('two_factor_recovery_codes'));

        $this->assertFalse($service->consumeRecoveryCode($user, 'not-a-recovery-code'));
        $this->assertTrue($service->consumeRecoveryCode($user, str_replace('-', '', $codes[0])));
        $this->assertFalse($service->consumeRecoveryCode($user, $codes[0]));

        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
    }
}
