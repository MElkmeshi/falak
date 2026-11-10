<?php

namespace Falak\Servers\Contracts;

/**
 * Custom (bring-your-own-machine) servers created by other modules, e.g. a hosted space: the server row plus the
 * one-time install URL the machine runs to enrol its agent.
 */
interface CustomServers
{
    /**
     * @return array{server_id: string, install_url: string, install_command: string}
     */
    public function create(string $organizationId, ?string $userId, string $name, ServerType $type = ServerType::App): array;
}
