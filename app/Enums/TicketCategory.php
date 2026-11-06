<?php

namespace App\Enums;

enum TicketCategory: string
{
    case General = 'general';
    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case Shipping = 'shipping';
    case FeatureRequest = 'feature_request';
}
