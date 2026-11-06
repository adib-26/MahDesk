<?php

namespace App\Console\Commands;

use App\Services\InboundEmail\TestmailPoller;
use Illuminate\Console\Command;
use Throwable;

class PollTestmail extends Command
{
    protected $signature = 'desk:poll-testmail
        {--watch : Keep polling until stopped (Ctrl+C)}
        {--interval=5 : Seconds between polls in --watch mode}
        {--sync : Process emails immediately instead of queueing them}';

    protected $description = 'Import new support emails from testmail.app into the email-to-ticket pipeline';

    public function handle(TestmailPoller $poller): int
    {
        $interval = max(1, (int) $this->option('interval'));

        do {
            try {
                $stats = $poller->poll((bool) $this->option('sync'));
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                if (! $this->option('watch')) {
                    return self::FAILURE;
                }

                sleep($interval);

                continue;
            }

            if (! $this->option('watch') || $stats['ingested'] > 0) {
                $this->info(sprintf(
                    '[%s] fetched %d, imported %d, skipped %d',
                    now()->format('H:i:s'),
                    $stats['fetched'],
                    $stats['ingested'],
                    $stats['skipped'],
                ));
            }

            if ($this->option('watch')) {
                sleep($interval);
            }
        } while ($this->option('watch'));

        return self::SUCCESS;
    }
}
