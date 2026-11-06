<?php

namespace App\Services\InboundEmail;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Pulls new mail from the testmail.app namespace and hands it to the same
 * ingestion pipeline the webhooks use. testmail.app has no push delivery,
 * so this runs on the scheduler (every minute) or in --watch mode.
 */
class TestmailPoller
{
    public const CURSOR_KEY = 'inbound-email:testmail:cursor';

    private const PAGE_SIZE = 25;

    public function __construct(
        private TestmailClient $client,
        private TestmailInboundParser $parser,
        private InboundEmailIngestor $ingestor,
    ) {}

    /**
     * @return array{fetched: int, ingested: int, skipped: int}
     */
    public function poll(bool $sync = false): array
    {
        // timestamp_from is inclusive, so the newest email from the previous
        // poll is read again; the ingestor deduplicates it by provider ID.
        $cursor = (float) Cache::get(
            self::CURSOR_KEY,
            now()->subMinutes((int) config('services.testmail.initial_lookback_minutes'))->getTimestampMs(),
        );

        $slugs = Workspace::query()->pluck('slug')->map(fn ($slug) => strtolower($slug))->flip();
        $stats = ['fetched' => 0, 'ingested' => 0, 'skipped' => 0];
        $newest = $cursor;
        $offset = 0;

        do {
            $page = $this->client->inbox($cursor, self::PAGE_SIZE, $offset);

            foreach ($page['emails'] as $email) {
                $stats['fetched']++;
                $newest = max($newest, (float) ($email['timestamp'] ?? 0));

                // The namespace also holds other test inboxes (for example a
                // test customer's). Only tags that are workspace slugs are
                // helpdesk mailboxes.
                if (! isset($slugs[strtolower((string) ($email['tag'] ?? ''))])) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $record = $this->ingestor->ingest($this->parser, $email, $sync);
                    $record->wasRecentlyCreated ? $stats['ingested']++ : $stats['skipped']++;
                } catch (InvalidArgumentException $exception) {
                    $stats['skipped']++;
                    Log::warning('Skipped unusable testmail.app email', ['id' => $email['id'] ?? null, 'error' => $exception->getMessage()]);
                }
            }

            $offset += count($page['emails']);
        } while ($page['emails'] !== [] && $offset < $page['count']);

        Cache::forever(self::CURSOR_KEY, $newest);

        return $stats;
    }
}
