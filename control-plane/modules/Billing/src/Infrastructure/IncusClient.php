<?php

namespace Falak\Billing\Infrastructure;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IncusClient
{
    public function create(string $name, float $cpus, int $memoryMb, int $diskGb, string $installUrl): void
    {
        $this->wait($this->request('post', '/1.0/instances', [
            'name' => $name,
            'type' => 'container',
            'source' => ['type' => 'image', 'alias' => config('billing.incus.image')],
            'profiles' => ['default'],
            'config' => $this->limits($cpus, $memoryMb) + [
                'security.nesting' => 'true',
                'security.syscalls.intercept.mknod' => 'true',
                'security.syscalls.intercept.setxattr' => 'true',
                'user.falak-install-url' => $installUrl,
            ],
            'devices' => [
                'root' => ['type' => 'disk', 'path' => '/', 'pool' => config('billing.incus.pool'), 'size' => "{$diskGb}GiB"],
            ],
        ]), 600);
    }

    public function start(string $name): void
    {
        $this->changeState($name, 'start');
    }

    public function stop(string $name): void
    {
        $this->changeState($name, 'stop');
    }

    public function delete(string $name): void
    {
        if ($this->exists($name)) {
            if (($this->state($name)['status'] ?? '') === 'Running') {
                $this->changeState($name, 'stop');
            }
            $this->wait($this->request('delete', "/1.0/instances/{$name}"));
        }
    }

    public function resize(string $name, float $cpus, int $memoryMb, int $diskGb): void
    {
        $this->wait($this->request('patch', "/1.0/instances/{$name}", [
            'config' => $this->limits($cpus, $memoryMb),
            'devices' => [
                'root' => ['type' => 'disk', 'path' => '/', 'pool' => config('billing.incus.pool'), 'size' => "{$diskGb}GiB"],
            ],
        ]));
    }

    public function exists(string $name): bool
    {
        return $this->http()->get($this->url("/1.0/instances/{$name}"))->status() === 200;
    }

    /**
     * @return array{status: string, ipv4: ?string, memory_bytes: int, disk_bytes: int, cpu_ns: int}
     */
    public function state(string $name): array
    {
        $state = (array) ($this->request('get', "/1.0/instances/{$name}/state")['metadata'] ?? []);
        $ipv4 = null;

        foreach ((array) ($state['network'] ?? []) as $interface => $net) {
            if ($interface === 'lo') {
                continue;
            }
            foreach ((array) ($net['addresses'] ?? []) as $address) {
                if (($address['family'] ?? '') === 'inet' && ($address['scope'] ?? '') === 'global' && str_starts_with((string) $interface, 'eth')) {
                    $ipv4 = (string) $address['address'];
                    break 2;
                }
            }
        }

        return [
            'status' => (string) ($state['status'] ?? 'Unknown'),
            'ipv4' => $ipv4,
            'memory_bytes' => (int) ($state['memory']['usage'] ?? 0),
            'disk_bytes' => (int) ($state['disk']['root']['usage'] ?? 0),
            'cpu_ns' => (int) ($state['cpu']['usage'] ?? 0),
        ];
    }

    private function limits(float $cpus, int $memoryMb): array
    {
        $cores = max(1, (int) ceil($cpus));

        return [
            'limits.cpu' => (string) $cores,
            'limits.cpu.allowance' => ((int) round($cpus * 100)).'ms/100ms',
            'limits.memory' => "{$memoryMb}MiB",
            'limits.memory.enforce' => 'hard',
            'limits.processes' => '4000',
        ];
    }

    private function changeState(string $name, string $action): void
    {
        $this->wait($this->request('put', "/1.0/instances/{$name}/state", ['action' => $action, 'timeout' => 60, 'force' => $action === 'stop']));
    }

    private function wait(array $response, int $timeout = 120): void
    {
        $operation = (string) ($response['operation'] ?? '');

        if ($operation === '') {
            return;
        }

        $result = $this->request('get', $operation.'/wait', ['timeout' => $timeout], $timeout + 10);
        $metadata = (array) ($result['metadata'] ?? []);

        if (($metadata['status'] ?? '') !== 'Success') {
            throw new RuntimeException('Incus: '.($metadata['err'] ?? 'operation did not finish'));
        }
    }

    private function request(string $method, string $path, array $body = [], int $timeout = 60): array
    {
        $http = $this->http()->timeout($timeout);
        $url = $this->url($method === 'get' && $body !== [] ? $path.'?'.http_build_query($body) : $path);

        /** @var Response $response */
        $response = match ($method) {
            'get' => $http->get($url),
            'delete' => $http->delete($url),
            default => $http->{$method}($url, $body),
        };

        $json = (array) $response->json();

        if ($response->failed() || ($json['type'] ?? '') === 'error') {
            throw new RuntimeException('Incus: '.($json['error'] ?? "HTTP {$response->status()}"));
        }

        return $json;
    }

    private function url(string $path): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';

        return rtrim((string) config('billing.incus.url'), '/').$path.$separator.'project='.urlencode((string) config('billing.incus.project'));
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->withOptions([
            'cert' => (string) config('billing.incus.cert'),
            'ssl_key' => (string) config('billing.incus.key'),
            'verify' => false,
        ]);
    }
}
