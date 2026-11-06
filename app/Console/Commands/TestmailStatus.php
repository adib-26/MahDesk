<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\InboundEmail\TestmailClient;
use App\Services\InboundEmail\TestmailPoller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TestmailStatus extends Command
{
    protected $signature = 'desk:testmail-status
        {--check : Make a live request to testmail.app}';

    protected $description = 'Show testmail.app configuration and workspace routing addresses';

    public function handle(TestmailClient $client): int
    {
        $configured = $client->isConfigured();

        $this->table(['Setting', 'Value'], [
            ['Driver', (string) config('services.inbound_email.driver')],
            ['Configured', $configured ? 'yes' : 'no'],
            ['Namespace', $this->maskedNamespace((string) config('services.testmail.namespace'))],
            ['Cursor', (string) Cache::get(TestmailPoller::CURSOR_KEY, 'not set')],
        ]);

        $this->newLine();
        $this->table(
            ['Workspace', 'Inbound address'],
            Workspace::query()->orderBy('name')->get()->map(fn (Workspace $workspace) => [
                $workspace->name,
                $workspace->inboundEmailAddress(),
            ])->all(),
        );

        if (! $this->option('check')) {
            return $configured ? self::SUCCESS : self::FAILURE;
        }

        if (! $configured) {
            $this->error('Set TESTMAIL_NAMESPACE and TESTMAIL_API_KEY before checking the API.');

            return self::FAILURE;
        }

        try {
            $result = $client->inbox(null, 1);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("testmail.app API reachable; {$result['count']} message(s) available.");

        return self::SUCCESS;
    }

    private function maskedNamespace(string $namespace): string
    {
        if ($namespace === '') {
            return 'not set';
        }

        return strlen($namespace) <= 2 ? '**' : substr($namespace, 0, 2).'***';
    }
}
