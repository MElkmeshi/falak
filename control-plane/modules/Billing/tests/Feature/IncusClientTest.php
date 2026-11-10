<?php

use Falak\Billing\Infrastructure\IncusClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['billing.incus.url' => 'https://incus.test:8443', 'billing.incus.project' => 'spaces']);
});

it('scopes every request, including reads and operation waits, to the spaces project', function () {
    Http::fake([
        'incus.test:8443/1.0/instances/space-a/state*' => Http::response(['type' => 'sync', 'metadata' => [
            'status' => 'Running',
            'network' => ['eth0' => ['addresses' => [['family' => 'inet', 'scope' => 'global', 'address' => '10.50.0.7']]]],
            'memory' => ['usage' => 1024],
        ]]),
        'incus.test:8443/1.0/operations/*' => Http::response(['type' => 'sync', 'metadata' => ['status' => 'Success']]),
        'incus.test:8443/1.0/instances*' => Http::response(['type' => 'async', 'operation' => '/1.0/operations/op-1']),
    ]);

    $client = new IncusClient;
    $client->create('space-a', 0.5, 1024, 8, 'https://panel.test/install');
    $state = $client->state('space-a');

    expect($state['ipv4'])->toBe('10.50.0.7')->and($state['status'])->toBe('Running');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/operations/op-1/wait') && str_contains($request->url(), 'timeout=600'));
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['config']['limits.cpu'] === '1'
        && $request['config']['limits.cpu.allowance'] === '50ms/100ms'
        && $request['config']['limits.memory'] === '1024MiB'
        && $request['devices']['root']['size'] === '8GiB');
    Http::assertNotSent(fn (Request $request) => ! str_contains($request->url(), 'project=spaces'));
});

it('caps multi-core plans with a hard CPU quota rather than a soft share', function () {
    Http::fake(['*' => Http::response(['type' => 'sync', 'metadata' => []])]);

    (new IncusClient)->resize('space-b', 2, 4096, 30);

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request['config']['limits.cpu'] === '2'
        && $request['config']['limits.cpu.allowance'] === '200ms/100ms');
});
