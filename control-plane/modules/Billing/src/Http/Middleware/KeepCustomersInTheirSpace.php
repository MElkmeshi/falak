<?php

namespace Falak\Billing\Http\Middleware;

use Closure;
use Falak\Billing\Application\Operator;
use Falak\Identity\Contracts\CurrentOrganization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class KeepCustomersInTheirSpace
{
    private const CUSTOMER_AREA = [
        'home', 'apps', 'store', 'billing', 'account', 'start', 'logout', 'login', 'register',
        'forgot-password', 'reset-password', 'verify-email', 'email', 'confirm-password',
        'two-factor-challenge', 'invitations', 'broadcasting', 'up',
    ];

    private const ACCOUNT_WRITES = ['settings/profile', 'settings/password'];

    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly Operator $operator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isCustomer($request) || $this->allowed($request)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect('/home');
        }

        abort(403, 'This is not part of your plan.');
    }

    public static function isCustomerOrganization(?string $organizationId, Operator $operator): bool
    {
        return config('billing.landing')
            && $organizationId !== null
            && $operator->organizationId() !== null
            && ! $operator->is($organizationId);
    }

    private function isCustomer(Request $request): bool
    {
        return $request->user() !== null && self::isCustomerOrganization($this->organization->id(), $this->operator);
    }

    private function allowed(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === '') {
            return true;
        }

        if (in_array(strtok($path, '/'), self::CUSTOMER_AREA, true)) {
            return ! str_starts_with($path, 'billing/admin');
        }

        return ! $request->isMethod('GET') && in_array($path, self::ACCOUNT_WRITES, true);
    }
}
