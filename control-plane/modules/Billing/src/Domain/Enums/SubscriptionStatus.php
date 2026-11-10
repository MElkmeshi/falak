<?php

namespace Falak\Billing\Domain\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
}
