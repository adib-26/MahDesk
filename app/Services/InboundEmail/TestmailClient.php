<?php

namespace App\Services\InboundEmail;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal testmail.app GraphQL client. Emails are returned oldest first so a
 * timestamp cursor can advance safely page by page.
 */
class TestmailClient
{
    private const QUERY = <<<'GRAPHQL'
        query Inbox($namespace: String!, $from: Float, $limit: Int, $offset: Int) {
          inbox(
            namespace: $namespace
            timestamp_from: $from
            limit: $limit
            offset: $offset
            advanced_sorts: [{ field: timestamp, order: asc }]
          ) {
            result
            message
            count
            emails {
              id tag timestamp envelope_to
              from_parsed { address name }
              to_parsed { address }
              cc_parsed { address }
              subject messageId references
              headers { key line }
              text html
              attachments { filename contentType size }
            }
          }
        }
        GRAPHQL;

    public function isConfigured(): bool
    {
        return filled(config('services.testmail.api_key')) && filled(config('services.testmail.namespace'));
    }

    /**
     * @return array{count: int, emails: list<array<string, mixed>>}
     */
    public function inbox(?float $timestampFrom, int $limit, int $offset = 0): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Set TESTMAIL_API_KEY and TESTMAIL_NAMESPACE to poll testmail.app.');
        }

        $response = Http::withToken((string) config('services.testmail.api_key'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 1000, throw: false)
            ->post((string) config('services.testmail.endpoint'), [
                'query' => self::QUERY,
                'variables' => [
                    'namespace' => config('services.testmail.namespace'),
                    'from' => $timestampFrom,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);

        $body = $response->json();
        $error = $body['errors'][0]['message'] ?? (($body['data']['inbox']['result'] ?? null) === 'fail' ? ($body['data']['inbox']['message'] ?? null) : null);

        if ($response->failed() || $error !== null) {
            throw new RuntimeException('testmail.app request failed: '.($error ?? "HTTP {$response->status()}"));
        }

        $inbox = $body['data']['inbox'] ?? null;
        if (! is_array($inbox) || ! is_array($inbox['emails'] ?? null)) {
            throw new RuntimeException('testmail.app request failed: response did not contain inbox emails.');
        }

        return [
            'count' => (int) ($inbox['count'] ?? 0),
            'emails' => $inbox['emails'],
        ];
    }
}
