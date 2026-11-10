<?php

namespace Falak\Billing\Tests\Support;

use Falak\Billing\Infrastructure\IncusClient;

final class FakeIncus extends IncusClient
{
    public array $instances = [];

    public array $calls = [];

    public static function install(): self
    {
        $fake = new self;
        app()->instance(IncusClient::class, $fake);

        return $fake;
    }

    public function create(string $name, float $cpus, int $memoryMb, int $diskGb, string $installUrl): void
    {
        $this->calls[] = ['create', $name];
        $this->instances[$name] = ['status' => 'Stopped', 'cpus' => $cpus, 'memory_mb' => $memoryMb, 'disk_gb' => $diskGb, 'install_url' => $installUrl];
    }

    public function start(string $name): void
    {
        $this->calls[] = ['start', $name];
        $this->instances[$name]['status'] = 'Running';
    }

    public function stop(string $name): void
    {
        $this->calls[] = ['stop', $name];
        $this->instances[$name]['status'] = 'Stopped';
    }

    public function delete(string $name): void
    {
        unset($this->instances[$name]);
    }

    public function resize(string $name, float $cpus, int $memoryMb, int $diskGb): void
    {
        $this->calls[] = ['resize', $name];
        $this->instances[$name] = array_merge($this->instances[$name] ?? [], ['cpus' => $cpus, 'memory_mb' => $memoryMb, 'disk_gb' => $diskGb]);
    }

    public function exists(string $name): bool
    {
        return isset($this->instances[$name]);
    }

    public function state(string $name): array
    {
        return [
            'status' => $this->instances[$name]['status'] ?? 'Stopped',
            'ipv4' => isset($this->instances[$name]) ? '10.50.0.10' : null,
            'memory_bytes' => 300 * 1048576,
            'disk_bytes' => 2 * 1073741824,
            'cpu_ns' => 0,
        ];
    }
}
