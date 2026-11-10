<?php

namespace Falak\Billing\Domain\Enums;

enum SpaceStatus: string
{
    case Provisioning = 'provisioning';
    case Enrolling = 'enrolling';
    case Ready = 'ready';
    case Suspended = 'suspended';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Creating your space',
            self::Enrolling => 'Installing Falak in your space',
            self::Ready => 'Ready',
            self::Suspended => 'Suspended',
            self::Error => 'Needs attention',
        };
    }
}
