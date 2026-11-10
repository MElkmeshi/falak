<?php

namespace Falak\Billing\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingOtp = 'awaiting_otp';
    case AwaitingReview = 'awaiting_review';
    case Paid = 'paid';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function settled(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Rejected], true);
    }
}
