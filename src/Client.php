<?php

declare(strict_types=1);

namespace IgniteContainers;

/**
 * Minimal, dependency-free client for the Ignite Containers control plane.
 *
 *   $client = new \IgniteContainers\Client('https://games.example.com', getenv('IGNITE_CONTAINERS_API_KEY'));
 *   $servers = $client->listServers(['limit' => 10]);
 *
 * All methods either return the decoded JSON (array) or throw ClientException.
 */
final class Client
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
        if (!preg_match('#^https?://#i', $baseUrl)) {
            throw new \InvalidArgumentException('baseUrl must start with http:// or https://');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    // ------------------------------------------------------------------ auth

    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->request('GET', '/api/v1/me');
    }

    // -------------------------------------------------------------- api keys

    /** @return array<int, array<string, mixed>> */
    public function listApiKeys(): array
    {
        return $this->request('GET', '/api/v1/api-keys')['keys'];
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $serverIds
     * @return array<string, mixed> The created key plus the one-time plaintext token.
     */
    public function createApiKey(
        string $name,
        array $scopes,
        array $serverIds = [],
        ?string $expiresAt = null,
    ): array {
        $body = [
            'name' => $name,
            'scopes' => array_values($scopes),
            'server_ids' => array_values($serverIds),
        ];
        if ($expiresAt !== null) {
            $body['expires_at'] = $expiresAt;
        }

        return $this->request('POST', '/api/v1/api-keys', $body);
    }

    public function revokeApiKey(string $id): void
    {
        $this->request('DELETE', '/api/v1/api-keys/' . rawurlencode($id));
    }

    // ----------------------------------------------------------------- nodes

    /** @return array<int, array<string, mixed>> */
    public function listNodes(): array
    {
        return $this->request('GET', '/api/v1/nodes')['nodes'];
    }

    /** @param array<string, mixed> $node */
    public function createNode(array $node): array
    {
        return $this->request('POST', '/api/v1/nodes', $node);
    }

    public function getNode(string $id): array
    {
        return $this->request('GET', '/api/v1/nodes/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $patch */
    public function updateNode(string $id, array $patch): array
    {
        return $this->request('PATCH', '/api/v1/nodes/' . rawurlencode($id), $patch);
    }

    public function deleteNode(string $id): void
    {
        $this->request('DELETE', '/api/v1/nodes/' . rawurlencode($id));
    }

    public function rotateNodeToken(string $id): array
    {
        return $this->request('POST', '/api/v1/nodes/' . rawurlencode($id) . '/rotate-token', []);
    }

    // ------------------------------------------------------------ host updates

    /** @return array<string, mixed> */
    public function nodeUpdates(string $id): array
    {
        return $this->request('GET', '/api/v1/nodes/' . rawurlencode($id) . '/updates');
    }

    /** @return array<string, mixed> */
    public function checkNodeUpdates(string $id): array
    {
        return $this->request('POST', '/api/v1/nodes/' . rawurlencode($id) . '/updates/check', []);
    }

    /** @return array<string, mixed> */
    public function applyNodeUpdates(string $id, bool $reboot = false): array
    {
        return $this->request('POST', '/api/v1/nodes/' . rawurlencode($id) . '/updates/apply', [
            'reboot' => $reboot,
        ]);
    }

    /** @return array<string, mixed> */
    public function rebootNode(string $id): array
    {
        return $this->request('POST', '/api/v1/nodes/' . rawurlencode($id) . '/reboot', []);
    }

    // -------------------------------------------------------------- databases

    /** @return array<int, array<string, mixed>> */
    public function listDatabaseHosts(): array
    {
        return $this->request('GET', '/api/v1/database-hosts')['hosts'];
    }

    /** @param array<string, mixed> $host */
    public function createDatabaseHost(array $host): array
    {
        return $this->request('POST', '/api/v1/database-hosts', $host);
    }

    public function deleteDatabaseHost(string $id): void
    {
        $this->request('DELETE', '/api/v1/database-hosts/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function testDatabaseHost(string $id): array
    {
        return $this->request('POST', '/api/v1/database-hosts/' . rawurlencode($id) . '/test', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function listServerDatabases(string $serverId): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/databases',
        )['databases'];
    }

    /**
     * @param array{host_id?: string, remote?: string} $request
     * @return array<string, mixed> Includes the one-time plaintext password.
     */
    public function createServerDatabase(string $serverId, array $request = []): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/databases',
            $request,
        );
    }

    public function deleteServerDatabase(string $serverId, string $databaseId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/servers/' . rawurlencode($serverId)
                . '/databases/' . rawurlencode($databaseId),
        );
    }

    // ------------------------------------------------------------------ sftp

    /** @return array<string, mixed> Includes host, port and the one-time password. */
    public function createSftpCredential(string $serverId, ?int $ttlSeconds = null): array
    {
        $body = [];
        if ($ttlSeconds !== null) {
            $body['ttl_seconds'] = $ttlSeconds;
        }

        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/sftp',
            $body,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listSftpCredentials(string $serverId): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/sftp',
        )['credentials'];
    }

    public function revokeSftpCredential(string $serverId, string $credentialId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/servers/' . rawurlencode($serverId) . '/sftp/' . rawurlencode($credentialId),
        );
    }

    // --------------------------------------------------------- notifications

    /**
     * @param list<string> $to
     * @return array<string, mixed>
     */
    public function sendEmail(array $to, string $subject, string $body): array
    {
        return $this->request('POST', '/api/v1/notifications/email', [
            'to' => array_values($to),
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listEmails(int $limit = 100): array
    {
        return $this->request(
            'GET',
            '/api/v1/notifications/emails' . $this->query(['limit' => $limit]),
        )['emails'];
    }

    /** @return array<string, mixed> */
    public function retryEmail(string $id): array
    {
        return $this->request(
            'POST',
            '/api/v1/notifications/emails/' . rawurlencode($id) . '/retry',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function notificationConfig(): array
    {
        return $this->request('GET', '/api/v1/notifications/config');
    }

    // ------------------------------------------------------------------ bulk

    /**
     * @param list<string> $serverIds
     * @return array<int, array<string, mixed>>
     */
    public function bulkPower(array $serverIds, string $signal): array
    {
        return $this->request('POST', '/api/v1/servers/bulk/power', [
            'server_ids' => array_values($serverIds),
            'signal' => $signal,
        ])['results'];
    }

    /**
     * @param list<string> $serverIds
     * @return array<int, array<string, mixed>>
     */
    public function bulkCommand(array $serverIds, string $command): array
    {
        return $this->request('POST', '/api/v1/servers/bulk/command', [
            'server_ids' => array_values($serverIds),
            'command' => $command,
        ])['results'];
    }

    // --------------------------------------------------------------- backups

    /** @return array<int, array<string, mixed>> */
    public function listBackups(string $serverId): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/backups',
        )['backups'];
    }

    /** @return array<string, mixed> */
    public function createBackup(string $serverId, ?string $name = null): array
    {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }

        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/backups',
            $body,
        );
    }

    public function deleteBackup(string $serverId, string $backupId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/servers/' . rawurlencode($serverId)
                . '/backups/' . rawurlencode($backupId),
        );
    }

    /** @return array<string, mixed> */
    public function restoreBackup(string $serverId, string $backupId): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId)
                . '/backups/' . rawurlencode($backupId) . '/restore',
            [],
        );
    }

    /** Returns raw gzip bytes. */
    public function downloadBackup(string $serverId, string $backupId): string
    {
        $url = '/api/v1/servers/' . rawurlencode($serverId)
            . '/backups/' . rawurlencode($backupId) . '/download';

        return $this->requestRaw('GET', $url);
    }

    // ------------------------------------------------------------- schedules

    /** @return array<int, array<string, mixed>> */
    public function listSchedules(string $serverId): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/schedules',
        )['schedules'];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    public function createSchedule(
        string $serverId,
        string $name,
        string $cron,
        array $actions,
        bool $enabled = true,
    ): array {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/schedules',
            [
                'name' => $name,
                'cron' => $cron,
                'actions' => $actions,
                'enabled' => $enabled,
            ],
        );
    }

    /** @param array<string, mixed> $patch */
    public function updateSchedule(string $scheduleId, array $patch): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/schedules/' . rawurlencode($scheduleId),
            $patch,
        );
    }

    public function deleteSchedule(string $scheduleId): void
    {
        $this->request('DELETE', '/api/v1/schedules/' . rawurlencode($scheduleId));
    }

    /** @return array<string, mixed> */
    public function runSchedule(string $scheduleId): array
    {
        return $this->request(
            'POST',
            '/api/v1/schedules/' . rawurlencode($scheduleId) . '/run',
            [],
        );
    }

    // ----------------------------------------------------------------- files

    /** @return array<string, mixed> */
    public function listFiles(string $serverId, string $path = '/'): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function readFile(string $serverId, string $path): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/content'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function writeFile(
        string $serverId,
        string $path,
        string $content,
        string $encoding = 'utf8',
    ): array {
        return $this->request(
            'PUT',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/content',
            ['path' => $path, 'content' => $content, 'encoding' => $encoding],
        );
    }

    /** @return array<string, mixed> */
    public function makeDirectory(string $serverId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/mkdir',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function renameFile(string $serverId, string $from, string $to): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/rename',
            ['from' => $from, 'to' => $to],
        );
    }

    /** @return array<string, mixed> */
    public function chmodFile(string $serverId, string $path, string $mode): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/chmod',
            ['path' => $path, 'mode' => $mode],
        );
    }

    /** @return array<string, mixed> */
    public function compressFile(string $serverId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/compress',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function decompressFile(string $serverId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files/decompress',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function deleteFile(string $serverId, string $path): array
    {
        return $this->request(
            'DELETE',
            '/api/v1/servers/' . rawurlencode($serverId) . '/files'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function suspendServer(string $serverId): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/suspend',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function resumeServer(string $serverId): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/resume',
            [],
        );
    }

    // ----------------------------------------------------------- allocations

    /** @return array<int, array<string, mixed>> */
    public function listAllocations(?string $nodeId = null, ?string $serverId = null): array
    {
        $query = array_filter([
            'node_id' => $nodeId,
            'server_id' => $serverId,
        ], static fn ($value) => $value !== null);

        return $this->request('GET', '/api/v1/allocations' . $this->query($query))['allocations'];
    }

    /**
     * @param array{ip?: string, ports?: string, start?: int, end?: int, protocol?: string, label?: string} $request
     * @return array{created: int, requested: int, skipped: int}
     */
    public function createAllocations(string $nodeId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/nodes/' . rawurlencode($nodeId) . '/allocations',
            $request,
        );
    }

    public function deleteAllocation(string $id): void
    {
        $this->request('DELETE', '/api/v1/allocations/' . rawurlencode($id));
    }

    // -------------------------------------------------------------- templates

    /** @return array<int, array<string, mixed>> */
    public function listTemplates(): array
    {
        return $this->request('GET', '/api/v1/templates')['templates'];
    }

    public function getTemplate(string $id): array
    {
        return $this->request('GET', '/api/v1/templates/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $egg Raw Pterodactyl egg document.
     * @param array{version?: string, channel?: string, slug?: string, save?: bool, signature?: string, public_key?: string} $options
     */
    public function importEgg(array $egg, array $options = []): array
    {
        return $this->request('POST', '/api/v1/templates', array_merge(
            ['egg' => $egg, 'save' => true],
            $options,
        ));
    }

    public function setAutoUpdate(string $id, bool $enabled, string $channel = 'stable'): array
    {
        return $this->request('PATCH', '/api/v1/templates/' . rawurlencode($id), [
            'auto_update' => ['enabled' => $enabled, 'channel' => $channel],
        ]);
    }

    public function deleteTemplate(string $id): void
    {
        $this->request('DELETE', '/api/v1/templates/' . rawurlencode($id));
    }

    /**
     * Submit a signed template feed. Fetching the feed is the caller's job:
     * Ignite Containers never performs outbound requests on its own.
     *
     * @param array<string, mixed> $feed
     */
    public function syncTemplateFeed(
        array $feed,
        string $signature,
        string $publicKey,
        bool $createMissing = false,
    ): array {
        return $this->request('POST', '/api/v1/template-feeds', [
            'feed' => $feed,
            'signature' => $signature,
            'public_key' => $publicKey,
            'create_missing' => $createMissing,
        ]);
    }

    // ---------------------------------------------------------------- servers

    /**
     * @param array{node_id?: string, state?: string, external_ref?: string, name?: string, limit?: int, offset?: int} $query
     * @return array<int, array<string, mixed>>
     */
    public function listServers(array $query = []): array
    {
        return $this->request('GET', '/api/v1/servers' . $this->query($query))['servers'];
    }

    /** @param array<string, mixed> $server */
    public function createServer(array $server): array
    {
        return $this->request('POST', '/api/v1/servers', $server);
    }

    public function getServer(string $id): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $patch */
    public function updateServer(string $id, array $patch): array
    {
        return $this->request('PATCH', '/api/v1/servers/' . rawurlencode($id), $patch);
    }

    public function deleteServer(string $id): void
    {
        $this->request('DELETE', '/api/v1/servers/' . rawurlencode($id));
    }

    /** @param "start"|"stop"|"restart"|"kill" $signal */
    public function power(string $id, string $signal): array
    {
        return $this->request('POST', '/api/v1/servers/' . rawurlencode($id) . '/power', [
            'signal' => $signal,
        ]);
    }

    public function command(string $id, string $command): array
    {
        return $this->request('POST', '/api/v1/servers/' . rawurlencode($id) . '/command', [
            'command' => $command,
        ]);
    }

    /**
     * @return array{server_id: string, cursor: string, lines: list<string>, truncated: bool}
     */
    public function logs(string $id, int $lines = 200): array
    {
        $lines = max(1, min(2000, $lines));

        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($id) . '/logs' . $this->query(['lines' => $lines]),
        );
    }

    /** @return array<string, mixed> */
    public function stats(string $id): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($id) . '/stats');
    }

    /** @param array<string, mixed> $limits */
    public function setLimits(string $id, array $limits): array
    {
        return $this->updateServer($id, ['limits' => $limits]);
    }

    public function attachAllocation(string $serverId, string $allocationId): array
    {
        return $this->request(
            'POST',
            '/api/v1/servers/' . rawurlencode($serverId) . '/allocations',
            ['allocation_id' => $allocationId],
        );
    }

    public function detachAllocation(string $serverId, string $allocationId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/servers/' . rawurlencode($serverId) . '/allocations/' . rawurlencode($allocationId),
        );
    }

    public function reinstall(string $id): array
    {
        return $this->request('POST', '/api/v1/servers/' . rawurlencode($id) . '/reinstall', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function listJobs(string $id): array
    {
        return $this->request('GET', '/api/v1/servers/' . rawurlencode($id) . '/jobs')['jobs'];
    }

    // ------------------------------------------------- environment / audit

    /** @return array<string, string> */
    public function getServerEnvironment(string $serverId): array
    {
        return $this->request(
            'GET',
            '/api/v1/servers/' . rawurlencode($serverId) . '/environment',
        )['environment'];
    }

    /** @return array<int, array<string, mixed>> */
    public function listAuditEvents(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        return $this->request('GET', '/api/v1/audit-events' . $this->query(['limit' => $limit]))['events'];
    }

    // ---------------------------------------------------------------- proxy

    /** @return array<int, array<string, mixed>> */
    public function listProxyRoutes(): array
    {
        return $this->request('GET', '/api/v1/proxy/routes')['routes'];
    }

    /** @param array<string, mixed> $route */
    public function createProxyRoute(array $route): array
    {
        return $this->request('POST', '/api/v1/proxy/routes', $route);
    }

    /** @param array<string, mixed> $route */
    public function updateProxyRoute(string $id, array $route): array
    {
        return $this->request('PATCH', '/api/v1/proxy/routes/' . rawurlencode($id), $route);
    }

    /** @return array<string, mixed> */
    public function deleteProxyRoute(string $id): array
    {
        return $this->request('DELETE', '/api/v1/proxy/routes/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function proxyStatus(): array
    {
        return $this->request('GET', '/api/v1/proxy/status');
    }

    /** @return array<string, mixed> */
    public function reconcileProxy(): array
    {
        return $this->request('POST', '/api/v1/proxy/reconcile', []);
    }

    // -------------------------------------------------------------- node state

    /** @return array<string, mixed> */
    public function getNodeState(string $id): array
    {
        return $this->request('GET', '/api/v1/nodes/' . rawurlencode($id) . '/state');
    }

    /** @return array<string, mixed> */
    public function getDatabaseHost(string $id): array
    {
        return $this->request('GET', '/api/v1/database-hosts/' . rawurlencode($id));
    }

    // --------------------------------------------------------- template versions

    /** @return array<int, array<string, mixed>> */
    public function listTemplateVersions(string $id): array
    {
        return $this->request('GET', '/api/v1/templates/' . rawurlencode($id) . '/versions')['versions'];
    }

    /**
     * @param array<string, mixed> $request template, channel, notes, signature, public_key
     * @return array<string, mixed>
     */
    public function publishTemplateVersion(string $id, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/templates/' . rawurlencode($id) . '/versions',
            $request,
        );
    }

    /**
     * Stream `GET /servers/{id}/logs/stream` (SSE) and call `$onLine` for each
     * `data:` line. `$maxSeconds = 0` disables the timeout.
     *
     * @param callable(string):void $onLine
     */
    public function streamLogs(string $serverId, callable $onLine, int $maxSeconds = 300): void
    {
        $handle = curl_init(
            $this->baseUrl . '/api/v1/servers/' . rawurlencode($serverId) . '/logs/stream'
        );
        if ($handle === false) {
            throw new \RuntimeException('could not initialise cURL');
        }
        $buffer = '';
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $maxSeconds > 0 ? $maxSeconds : 0,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer, $onLine): int {
                $buffer .= $chunk;
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $event = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);
                    $data = [];
                    foreach (explode("\n", $event) as $line) {
                        if (str_starts_with($line, 'data:')) {
                            $data[] = ltrim(substr($line, 5), ' ');
                        }
                    }
                    if ($data !== []) {
                        $onLine(implode("\n", $data));
                    }
                }
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($ok === false) {
            throw new \RuntimeException('log stream transport error: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('log stream failed with HTTP ' . $status);
        }
    }

    /** @return array{status: string, service: string, version: string, uptime_seconds: int} */
    public function health(): array
    {
        return $this->request('GET', '/healthz');
    }

    /** @return array{status: string, database: string} */
    public function ready(): array
    {
        return $this->request('GET', '/readyz');
    }

    /** @return array<string, mixed> */
    public function openapi(): array
    {
        return $this->request('GET', '/openapi.json');
    }

    public function docs(): string
    {
        return $this->requestRaw('GET', '/docs');
    }

    public function metrics(): string
    {
        return $this->requestRaw('GET', '/metrics');
    }

    /**
     * Escape hatch for endpoints added before this client is updated.
     *
     * @param array<string, mixed>|null $body
     */
    public function request(string $method, string $path, ?array $body = null): mixed
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('could not initialise cURL');
        }
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException('transport error: ' . $error);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($status >= 200 && $status < 300 && ($status === 204 || $raw === '')) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }

        if ($status < 200 || $status >= 300) {
            $error = $data['error'] ?? [];
            throw new ClientException(
                $status,
                is_string($error['code'] ?? null) ? $error['code'] : 'unknown',
                is_string($error['message'] ?? null)
                    ? $error['message']
                    : sprintf('HTTP %d', $status),
                is_array($error['details'] ?? null) ? $error['details'] : null,
            );
        }

        return $data;
    }

    /**
     * Requests a binary response (backup download).
     */
    public function requestRaw(string $method, string $path): string
    {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new \RuntimeException('could not initialise cURL');
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/gzip',
            ],
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new \RuntimeException('transport error: ' . $error);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($raw, true);
            $error = is_array($decoded) ? ($decoded['error'] ?? []) : [];
            throw new ClientException(
                $status,
                is_string($error['code'] ?? null) ? $error['code'] : 'download_failed',
                is_string($error['message'] ?? null) ? $error['message'] : 'download failed',
                is_array($error['details'] ?? null) ? $error['details'] : null,
            );
        }

        return $raw;
    }

    /** @param array<string, scalar|null> $query */
    private function query(array $query): string
    {
        $filtered = array_filter($query, static fn ($value) => $value !== null && $value !== '');
        if ($filtered === []) {
            return '';
        }

        return '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }
}
