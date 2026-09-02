<?php

namespace App\Enums;

enum TicketChannel: string
{
    case Email = 'email';
    case Web = 'web';
    case Chat = 'chat';
    case Phone = 'phone';
}
