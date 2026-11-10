<?php

namespace Falak\Billing\Application;

use Falak\Identity\Contracts\OrganizationAccess;
use Falak\Recovery\Contracts\OperatorOrganization;
use Illuminate\Contracts\Auth\Authenticatable;

final class Operator
{
    public const PERMISSION = 'billing.operate';

    public function __construct(
        private readonly OperatorOrganization $operator,
        private readonly OrganizationAccess $access,
    ) {}

    public function organizationId(): ?string
    {
        $configured = config('billing.operator_organization');

        if (is_string($configured) && $configured !== '') {
            return strtolower($configured);
        }

        return config('billing.operator_from_recovery') ? $this->operator->id() : null;
    }

    public function is(?string $organizationId): bool
    {
        return $organizationId !== null && $organizationId === $this->organizationId();
    }

    public function can(?Authenticatable $user, ?string $organizationId): bool
    {
        return $user !== null && $this->is($organizationId) && $this->access->can($user, (string) $organizationId, self::PERMISSION);
    }
}
