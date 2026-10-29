# Ignite Containers PHP client

Dependency-free PHP client for the Ignite Containers control plane API.

```php
$client = new \IgniteContainers\Client('https://api.example.com', getenv('IGNITE_CONTAINERS_API_KEY'));
$servers = $client->listServers(['limit' => 10]);
```

## Coverage

First-class methods cover API keys, audit events, nodes and allocations,
templates and signed feeds, servers and power/commands, files, backups,
schedules, SFTP, databases, notifications, Caddy proxy routes, metrics,
health/readiness, OpenAPI and SSE log streaming.

```php
$env = $client->getServerEnvironment($serverId);
$audit = $client->listAuditEvents(100);
$state = $client->getNodeState($nodeId);
$routes = $client->listProxyRoutes();
$client->reconcileProxy();
$versions = $client->listTemplateVersions($templateId);
$client->streamLogs($serverId, static function (string $line): void {
    echo $line, "\n";
}, 300);
$metrics = $client->metrics();
$health = $client->health();
$ready = $client->ready();
$openapi = $client->openapi();
$docs = $client->docs();
```

The raw `request()` and `requestRaw()` methods remain available for future or
agent-only endpoints.

Repository: <https://github.com/ATT-IT-Cloud-Services/ignite-containers-client-php>

License: ATT IT AS-IS Software License Agreement. See `LICENSE`.
