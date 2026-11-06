<?php

namespace App\Services\InboundEmail;

use InvalidArgumentException;

class InboundEmailParsers
{
    public function for(string $provider): InboundEmailParser
    {
        return match ($provider) {
            PostmarkInboundParser::PROVIDER => app(PostmarkInboundParser::class),
            TestmailInboundParser::PROVIDER => app(TestmailInboundParser::class),
            default => throw new InvalidArgumentException("Unknown inbound email provider [{$provider}]."),
        };
    }
}
