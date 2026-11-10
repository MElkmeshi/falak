<?php

namespace Falak\Billing\Infrastructure;

use Falak\Billing\Domain\Enums\SpaceStatus;
use Falak\Billing\Domain\Models\Space;
use Falak\Edge\Contracts\EdgeRoutes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SpaceRouter
{
    public function __construct(private readonly EdgeRoutes $routes) {}

    /**
     * @return array<string, string>
     */
    public function table(): array
    {
        $table = [];

        $spaces = Space::query()->whereIn('status', [SpaceStatus::Ready, SpaceStatus::Enrolling])
            ->whereNotNull('server_id')->whereNotNull('ipv4')->orderBy('created_at')->get();

        foreach ($spaces as $space) {
            try {
                $payload = $this->routes->compile((string) $space->server_id);
            } catch (Throwable $e) {
                Log::warning('billing: could not compile routes for space', ['space' => $space->id, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ((array) ($payload['sites'] ?? []) as $site) {
                foreach ([...(array) ($site['domains'] ?? []), ...(array) ($site['redirect_domains'] ?? [])] as $host) {
                    $host = strtolower(trim((string) $host));

                    if ($host !== '' && ! isset($table[$host])) {
                        $table[$host] = (string) $space->ipv4;
                    }
                }
            }
        }

        ksort($table);

        return $table;
    }

    public function push(): bool
    {
        $config = $this->config($this->table());

        try {
            return Http::timeout(10)->withHeaders(['Host' => $this->adminHost()])
                ->withBody(json_encode($config, JSON_UNESCAPED_SLASHES), 'application/json')
                ->post(rtrim((string) config('billing.router.admin_url'), '/').'/load')->successful();
        } catch (Throwable $e) {
            Log::warning('billing: space router push failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array<string, string>  $table
     */
    public function config(array $table): array
    {
        $byIp = [];
        foreach ($table as $host => $ip) {
            $byIp[$ip][] = $host;
        }

        $tls = [];
        $http = [];
        foreach ($byIp as $ip => $hosts) {
            $tls[] = ['match' => [['tls' => ['sni' => $hosts]]], 'handle' => [['handler' => 'proxy', 'upstreams' => [['dial' => ["{$ip}:443"]]]]]];
            $http[] = ['match' => [['http' => [['host' => $hosts]]]], 'handle' => [['handler' => 'proxy', 'upstreams' => [['dial' => ["{$ip}:80"]]]]]];
        }

        $server = fn (string $listen, array $routes) => [
            'listen' => [$listen],
            'routes' => [['handle' => [['handler' => 'proxy_protocol'], ['handler' => 'subroute', 'routes' => $routes]]]],
        ];

        return [
            'admin' => ['listen' => '0.0.0.0:2019', 'origins' => [$this->adminHost(), 'localhost:2019', '127.0.0.1:2019']],
            'apps' => ['layer4' => ['servers' => [
                'https' => $server((string) config('billing.router.https_listen'), $tls),
                'http' => $server((string) config('billing.router.http_listen'), $http),
            ]]],
        ];
    }

    private function adminHost(): string
    {
        return (string) parse_url((string) config('billing.router.admin_url'), PHP_URL_HOST).':'.(parse_url((string) config('billing.router.admin_url'), PHP_URL_PORT) ?: 2019);
    }
}
