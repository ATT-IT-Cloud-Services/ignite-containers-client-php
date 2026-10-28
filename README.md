# Ignite Containers PHP client

Dependency-free PHP client for the Ignite Containers control plane API.

```php
$client = new \IgniteContainers\Client('https://api.example.com', getenv('IGNITE_CONTAINERS_API_KEY'));
$servers = $client->listServers(['limit' => 10]);
```

Covers operators, nodes, templates, servers, files, backups, schedules, SFTP,
databases, notifications, Caddy proxy routes, audit, metrics, health/readiness,
OpenAPI and SSE log streaming.

License: AGPL-3.0-or-later. See `LICENSE`.
