<?php

namespace Tests\Unit;

use App\Services\InboundEmail\TestmailClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TestmailClientTest extends TestCase
{
    public function test_it_fetches_inbox_messages_with_graphql_variables_and_bearer_authentication(): void
    {
        config()->set('services.testmail.api_key', 'test-api-key');
        config()->set('services.testmail.namespace', 'test-namespace');
        config()->set('services.testmail.endpoint', 'https://api.testmail.app/api/graphql');

        Http::fake([
            'https://api.testmail.app/api/graphql' => Http::response([
                'data' => [
                    'inbox' => [
                        'result' => 'success',
                        'count' => 1,
                        'emails' => [['id' => 'email-1']],
                    ],
                ],
            ]),
        ]);

        $result = app(TestmailClient::class)->inbox(1700000000000.0, 25, 10);

        $this->assertSame(['count' => 1, 'emails' => [['id' => 'email-1']]], $result);
        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request['variables'] === [
                    'namespace' => 'test-namespace',
                    'from' => 1700000000000.0,
                    'limit' => 25,
                    'offset' => 10,
                ]
                && str_contains((string) $request['query'], 'inbox(');
        });
    }

    public function test_it_raises_an_error_for_graphql_failures(): void
    {
        config()->set('services.testmail.api_key', 'test-api-key');
        config()->set('services.testmail.namespace', 'test-namespace');

        Http::fake([
            '*' => Http::response([
                'errors' => [['message' => 'Invalid namespace']],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid namespace');

        app(TestmailClient::class)->inbox(null, 25);
    }

    public function test_it_raises_an_error_when_the_response_has_no_inbox(): void
    {
        config()->set('services.testmail.api_key', 'test-api-key');
        config()->set('services.testmail.namespace', 'test-namespace');

        Http::fake(['*' => Http::response(['data' => []])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('response did not contain inbox emails');

        app(TestmailClient::class)->inbox(null, 25);
    }
}
