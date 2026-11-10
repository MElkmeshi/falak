<?php

namespace Falak\Servers\Infrastructure;

use Falak\Servers\Application\Actions\CreateServer;
use Falak\Servers\Contracts\CustomServers;
use Falak\Servers\Contracts\ServerType;
use RuntimeException;

final class ActionCustomServers implements CustomServers
{
    public function __construct(private readonly CreateServer $createServer) {}

    public function create(string $organizationId, ?string $userId, string $name, ServerType $type = ServerType::App): array
    {
        $server = ($this->createServer)($organizationId, $userId, ['name' => $name, 'type' => $type->value, 'provider' => 'custom']);
        $command = (string) $server->fresh()?->install_command;

        if (preg_match('#https?://\S+/install/[A-Za-z0-9_-]+#', $command, $url) !== 1) {
            throw new RuntimeException("Server {$server->id} has no install URL.");
        }

        return ['server_id' => $server->id, 'install_url' => $url[0], 'install_command' => $command];
    }
}
