<?php

namespace Tests\Unit;

use App\Models\Workspace;
use App\Services\InboundEmail\TestmailClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestmailStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_checks_the_configured_testmail_namespace_without_exposing_the_api_key(): void
    {
        config()->set('services.inbound_email.driver', 'testmail');
        config()->set('services.testmail.api_key', 'secret-api-key');
        config()->set('services.testmail.namespace', 'demo');
        Cache::put('inbound-email:testmail:cursor', 1700000000000);
        Workspace::create(['name' => 'Acme', 'slug' => 'acme']);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'inbox' => [
                        'result' => 'success',
                        'count' => 0,
                        'emails' => [],
                    ],
                ],
            ]),
        ]);

        $this->artisan('desk:testmail-status', ['--check' => true])
            ->expectsOutputToContain('de***')
            ->expectsOutputToContain('testmail.app API reachable')
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => ! str_contains((string) $request->body(), 'secret-api-key'));
    }
}
