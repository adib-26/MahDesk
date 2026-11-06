<?php

namespace App\Jobs;

use App\Models\InboundEmail;
use App\Services\InboundEmail\EmailTicketProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessInboundEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $inboundEmailId) {}

    public function handle(EmailTicketProcessor $processor): void
    {
        $record = InboundEmail::find($this->inboundEmailId);

        if ($record) {
            $processor->process($record);
        }
    }

    public function failed(?Throwable $exception): void
    {
        InboundEmail::find($this->inboundEmailId)?->markAs(
            InboundEmail::STATUS_FAILED,
            mb_substr($exception?->getMessage() ?? 'Processing failed.', 0, 255),
        );
    }
}
