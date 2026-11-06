<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\InboundEmail\InboundEmailIngestor;
use App\Services\InboundEmail\PostmarkInboundParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Feed an email through the real ingestion pipeline without an email
 * provider. Builds the same payload shape Postmark posts to the webhook.
 */
class ReceiveEmail extends Command
{
    protected $signature = 'desk:receive-email
        {to : Workspace slug or recipient address (e.g. northwind-support or support@company.com)}
        {from : Sender address}
        {subject}
        {body}
        {--name= : Sender display name}
        {--in-reply-to= : Message-ID this email replies to}
        {--queue : Queue for a worker instead of processing immediately}';

    protected $description = 'Simulate an inbound support email (creates or threads a ticket)';

    public function handle(InboundEmailIngestor $ingestor, PostmarkInboundParser $parser): int
    {
        $to = (string) $this->argument('to');
        $recipient = str_contains($to, '@') ? $to : Workspace::inboundAddressFor($to);
        $messageId = Str::uuid()->toString().'@cli.mahbubdesk.test';

        $headers = [['Name' => 'Message-ID', 'Value' => "<{$messageId}>"]];
        if ($inReplyTo = $this->option('in-reply-to')) {
            $headers[] = ['Name' => 'In-Reply-To', 'Value' => '<'.trim($inReplyTo, '<>').'>'];
        }

        $record = $ingestor->ingest($parser, [
            'MessageID' => Str::uuid()->toString(),
            'From' => $this->argument('from'),
            'FromName' => $this->option('name') ?? '',
            'FromFull' => ['Email' => $this->argument('from'), 'Name' => $this->option('name') ?? ''],
            'ToFull' => [['Email' => $recipient, 'Name' => '']],
            'OriginalRecipient' => $recipient,
            'Subject' => $this->argument('subject'),
            'TextBody' => $this->argument('body'),
            'Headers' => $headers,
        ], sync: ! $this->option('queue'));

        $this->table(['Inbound email', 'Status', 'Detail', 'Ticket ID', 'Message-ID'], [[
            $record->id,
            $record->status,
            $record->status_reason ?? '—',
            $record->ticket_id ?? '—',
            $messageId,
        ]]);

        return self::SUCCESS;
    }
}
