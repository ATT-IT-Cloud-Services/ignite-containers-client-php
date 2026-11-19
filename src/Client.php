<?php

declare(strict_types=1);

namespace IgniteContainers;

/**
 * Minimal, dependency-free client for the Ignite Containers control plane.
 *
 *   $client = new \IgniteContainers\Client('https://api.example.com', getenv('IGNITE_CONTAINERS_API_KEY'));
 *   $pods = $client->listPods(['limit' => 10]);
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
     * @param list<string> $podIds
     * @return array<string, mixed> The created key plus the one-time plaintext token.
     */
    public function createApiKey(
        string $name,
        array $scopes,
        array $podIds = [],
        ?string $expiresAt = null,
        array $limits = [],
    ): array {
        $body = [
            'name' => $name,
            'scopes' => array_values($scopes),
            'pod_ids' => array_values($podIds),
        ];
        if ($expiresAt !== null) {
            $body['expires_at'] = $expiresAt;
        }
        if ($limits !== []) {
            $body['limits'] = $limits;
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
    public function listPodDatabases(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/databases',
        )['databases'];
    }

    /**
     * @param array{host_id?: string, remote?: string} $request
     * @return array<string, mixed> Includes the one-time plaintext password.
     */
    public function createPodDatabase(string $podId, array $request = []): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/databases',
            $request,
        );
    }

    public function deletePodDatabase(string $podId, string $databaseId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/databases/' . rawurlencode($databaseId),
        );
    }

    // ------------------------------------------------------------------ sftp

    /** @return array<string, mixed> Includes host, port and the one-time password. */
    public function createSftpCredential(string $podId, ?int $ttlSeconds = null): array
    {
        $body = [];
        if ($ttlSeconds !== null) {
            $body['ttl_seconds'] = $ttlSeconds;
        }

        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/sftp',
            $body,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listSftpCredentials(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/sftp',
        )['credentials'];
    }

    public function revokeSftpCredential(string $podId, string $credentialId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId) . '/sftp/' . rawurlencode($credentialId),
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
     * @param list<string> $podIds
     * @return array<int, array<string, mixed>>
     */
    public function bulkPower(array $podIds, string $signal): array
    {
        return $this->request('POST', '/api/v1/pods/bulk/power', [
            'pod_ids' => array_values($podIds),
            'signal' => $signal,
        ])['results'];
    }

    /**
     * @param list<string> $podIds
     * @return array<int, array<string, mixed>>
     */
    public function bulkCommand(array $podIds, string $command): array
    {
        return $this->request('POST', '/api/v1/pods/bulk/command', [
            'pod_ids' => array_values($podIds),
            'command' => $command,
        ])['results'];
    }

    // --------------------------------------------------------------- backups

    /** @return array<int, array<string, mixed>> */
    public function listBackups(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/backups',
        )['backups'];
    }

    /** @return array<string, mixed> */
    public function createBackup(string $podId, ?string $name = null): array
    {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }

        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/backups',
            $body,
        );
    }

    public function deleteBackup(string $podId, string $backupId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/backups/' . rawurlencode($backupId),
        );
    }

    /** @return array<string, mixed> */
    public function restoreBackup(string $podId, string $backupId): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/backups/' . rawurlencode($backupId) . '/restore',
            [],
        );
    }

    /** Returns raw gzip bytes. */
    public function downloadBackup(string $podId, string $backupId): string
    {
        $url = '/api/v1/pods/' . rawurlencode($podId)
            . '/backups/' . rawurlencode($backupId) . '/download';

        return $this->requestRaw('GET', $url);
    }

    /** Returns raw gzip bytes of the data volume (or one subdirectory). */
    public function downloadVolume(string $podId, ?string $path = null): string
    {
        return $this->requestRaw(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/volume/export'
                . $this->query(['path' => $path]),
        );
    }

    /**
     * Uploads a gzip archive into the data volume (or one subdirectory).
     *
     * @return array<string, mixed>
     */
    public function importVolume(
        string $podId,
        string $contents,
        ?string $path = null,
        bool $wipe = true,
    ): array {
        return $this->requestBody(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/volume/import'
                . $this->query(['path' => $path, 'wipe' => $wipe ? 'true' : 'false']),
            $contents,
            'application/gzip',
        );
    }

    // ------------------------------------------------------------- schedules

    /** @return array<int, array<string, mixed>> */
    public function listSchedules(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/schedules',
        )['schedules'];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    public function createSchedule(
        string $podId,
        string $name,
        string $cron,
        array $actions,
        bool $enabled = true,
    ): array {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/schedules',
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
    public function listFiles(string $podId, string $path = '/'): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/files'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function readFile(string $podId, string $path): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/content'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function writeFile(
        string $podId,
        string $path,
        string $content,
        string $encoding = 'utf8',
        ?string $etag = null,
    ): array {
        $body = ['path' => $path, 'content' => $content, 'encoding' => $encoding];
        if ($etag !== null) {
            $body['etag'] = $etag;
        }

        return $this->request(
            'PUT',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/content',
            $body,
        );
    }

    /** @return array<string, mixed> */
    public function makeDirectory(string $podId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/mkdir',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function renameFile(string $podId, string $from, string $to): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/rename',
            ['from' => $from, 'to' => $to],
        );
    }

    /** @return array<string, mixed> */
    public function chmodFile(string $podId, string $path, string $mode): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/chmod',
            ['path' => $path, 'mode' => $mode],
        );
    }

    /** @return array<string, mixed> */
    public function compressFile(string $podId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/compress',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function decompressFile(string $podId, string $path): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/decompress',
            ['path' => $path],
        );
    }

    /** @return array<string, mixed> */
    public function deleteFile(string $podId, string $path): array
    {
        return $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId) . '/files'
                . $this->query(['path' => $path]),
        );
    }

    /** @return array<string, mixed> */
    public function suspendPod(string $podId): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/suspend',
            [],
        );
    }

    /** @return array<string, mixed> */
    public function resumePod(string $podId): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/resume',
            [],
        );
    }

    // ----------------------------------------------------------- allocations

    /** @return array<int, array<string, mixed>> */
    public function listAllocations(?string $nodeId = null, ?string $podId = null): array
    {
        $query = array_filter([
            'node_id' => $nodeId,
            'pod_id' => $podId,
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

    // ---------------------------------------------------------------- pods

    /**
     * @param array{node_id?: string, state?: string, external_ref?: string, name?: string, limit?: int, offset?: int} $query
     * @return array<int, array<string, mixed>>
     */
    public function listPods(array $query = []): array
    {
        return $this->request('GET', '/api/v1/pods' . $this->query($query))['pods'];
    }

    /** @param array<string, mixed> $pod */
    public function createPod(array $pod): array
    {
        return $this->request('POST', '/api/v1/pods', $pod);
    }

    public function getPod(string $id): array
    {
        return $this->request('GET', '/api/v1/pods/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $patch */
    public function updatePod(string $id, array $patch): array
    {
        return $this->request('PATCH', '/api/v1/pods/' . rawurlencode($id), $patch);
    }

    public function deletePod(string $id): void
    {
        $this->request('DELETE', '/api/v1/pods/' . rawurlencode($id));
    }

    /** @param "start"|"stop"|"restart"|"kill" $signal */
    public function power(string $id, string $signal): array
    {
        return $this->request('POST', '/api/v1/pods/' . rawurlencode($id) . '/power', [
            'signal' => $signal,
        ]);
    }

    public function command(string $id, string $command): array
    {
        return $this->request('POST', '/api/v1/pods/' . rawurlencode($id) . '/command', [
            'command' => $command,
        ]);
    }

    /**
     * @return array{pod_id: string, cursor: string, lines: list<string>, truncated: bool}
     */
    public function logs(string $id, int $lines = 200): array
    {
        $lines = max(1, min(2000, $lines));

        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($id) . '/logs' . $this->query(['lines' => $lines]),
        );
    }

    /** @return array<string, mixed> */
    public function stats(string $id): array
    {
        return $this->request('GET', '/api/v1/pods/' . rawurlencode($id) . '/stats');
    }

    /** @param array<string, mixed> $limits */
    public function setLimits(string $id, array $limits): array
    {
        return $this->updatePod($id, ['limits' => $limits]);
    }

    public function attachAllocation(string $podId, string $allocationId): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/allocations',
            ['allocation_id' => $allocationId],
        );
    }

    public function detachAllocation(string $podId, string $allocationId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId) . '/allocations/' . rawurlencode($allocationId),
        );
    }

    public function reinstall(string $id): array
    {
        return $this->request('POST', '/api/v1/pods/' . rawurlencode($id) . '/reinstall', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function listJobs(string $id): array
    {
        return $this->request('GET', '/api/v1/pods/' . rawurlencode($id) . '/jobs')['jobs'];
    }

    // ------------------------------------------------- environment / audit

    /** @return array<string, string> */
    public function getPodEnvironment(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/environment',
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

    /** @return array<int, array<string, mixed>> */
    public function listProxyCertificates(): array
    {
        return $this->request('GET', '/api/v1/proxy/certificates')['certificates'];
    }

    /** @param array<string, mixed> $certificate */
    public function uploadProxyCertificate(array $certificate): array
    {
        return $this->request('POST', '/api/v1/proxy/certificates', $certificate);
    }

    /** @return array<string, mixed> */
    public function getProxyCertificate(string $id): array
    {
        return $this->request('GET', '/api/v1/proxy/certificates/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function deleteProxyCertificate(string $id): array
    {
        return $this->request('DELETE', '/api/v1/proxy/certificates/' . rawurlencode($id));
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

    // -------------------------------------------------------------- fabric

    /** @return array<int, array<string, mixed>> */
    public function listVnets(): array
    {
        return $this->request('GET', '/api/v1/vnets')['vnets'];
    }

    /** @param array<string, mixed> $vnet */
    public function createVnet(array $vnet): array
    {
        return $this->request('POST', '/api/v1/vnets', $vnet);
    }

    public function deleteVnet(string $id): void
    {
        $this->request('DELETE', '/api/v1/vnets/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listVnetAttachments(string $id): array
    {
        return $this->request(
            'GET',
            '/api/v1/vnets/' . rawurlencode($id) . '/attachments',
        )['attachments'];
    }

    /** @param array<string, mixed> $request */
    public function attachVnet(string $id, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/vnets/' . rawurlencode($id) . '/attachments',
            $request,
        );
    }

    public function detachVnet(string $id, string $podId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/vnets/' . rawurlencode($id) . '/attachments/' . rawurlencode($podId),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listInterconnects(): array
    {
        return $this->request('GET', '/api/v1/interconnects')['interconnects'];
    }

    /** @param array<string, mixed> $request */
    public function createInterconnect(array $request): array
    {
        return $this->request('POST', '/api/v1/interconnects', $request);
    }

    public function deleteInterconnect(string $id): void
    {
        $this->request('DELETE', '/api/v1/interconnects/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listRelays(): array
    {
        return $this->request('GET', '/api/v1/relays')['relays'];
    }

    /** @param array<string, mixed> $request */
    public function createRelay(array $request): array
    {
        return $this->request('POST', '/api/v1/relays', $request);
    }

    public function getRelay(string $id): array
    {
        return $this->request('GET', '/api/v1/relays/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateRelay(string $id, array $request): array
    {
        return $this->request('PATCH', '/api/v1/relays/' . rawurlencode($id), $request);
    }

    public function deleteRelay(string $id): void
    {
        $this->request('DELETE', '/api/v1/relays/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listListeners(): array
    {
        return $this->request('GET', '/api/v1/proxy/listeners')['listeners'];
    }

    /** @param array<string, mixed> $request */
    public function createListener(array $request): array
    {
        return $this->request('POST', '/api/v1/proxy/listeners', $request);
    }

    public function deleteListener(string $id): void
    {
        $this->request('DELETE', '/api/v1/proxy/listeners/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listListenerRoutes(string $id): array
    {
        return $this->request(
            'GET',
            '/api/v1/proxy/listeners/' . rawurlencode($id) . '/routes',
        )['routes'];
    }

    /** @param array<string, mixed> $request */
    public function createListenerRoute(string $id, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/proxy/listeners/' . rawurlencode($id) . '/routes',
            $request,
        );
    }

    public function deleteListenerRoute(string $id, string $routeId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/proxy/listeners/' . rawurlencode($id) . '/routes/' . rawurlencode($routeId),
        );
    }

    /** @return array<string, mixed> builtin and custom adapters */
    public function listProxyAdapters(): array
    {
        return $this->request('GET', '/api/v1/proxy/adapters');
    }

    /** @param array<string, mixed> $request */
    public function createProxyAdapter(array $request): array
    {
        return $this->request('POST', '/api/v1/proxy/adapters', $request);
    }

    public function deleteProxyAdapter(string $id): void
    {
        $this->request('DELETE', '/api/v1/proxy/adapters/' . rawurlencode($id));
    }

    // ------------------------------------------------------- template export

    /** @param array<string, mixed> $request */
    public function exportTemplate(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/export-template',
            $request,
        );
    }

    /** @param array<string, mixed> $request */
    public function cloneTemplate(string $templateId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/templates/' . rawurlencode($templateId) . '/clone',
            $request,
        );
    }

    // ------------------------------------------------- deployment classes

    /** @return array<int, array<string, mixed>> */
    public function listDeploymentClasses(): array
    {
        return $this->request('GET', '/api/v1/deployment-classes')['groups'];
    }

    /** @param array<string, mixed> $group */
    public function createDeploymentClass(array $group): array
    {
        return $this->request('POST', '/api/v1/deployment-classes', $group);
    }

    /** @param array<string, mixed> $patch */
    public function updateDeploymentClass(string $id, array $patch): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/deployment-classes/' . rawurlencode($id),
            $patch,
        );
    }

    public function deleteDeploymentClass(string $id): void
    {
        $this->request('DELETE', '/api/v1/deployment-classes/' . rawurlencode($id));
    }

    // ------------------------------------------------------- runtime groups

    /** @return array<int, array<string, mixed>> */
    public function listRuntimeGroups(): array
    {
        return $this->request('GET', '/api/v1/runtime-groups')['groups'];
    }

    /** @param array<string, mixed> $group */
    public function createRuntimeGroup(array $group): array
    {
        return $this->request('POST', '/api/v1/runtime-groups', $group);
    }

    /** @param array<string, mixed> $patch */
    public function updateRuntimeGroup(string $id, array $patch): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/runtime-groups/' . rawurlencode($id),
            $patch,
        );
    }

    public function deleteRuntimeGroup(string $id): void
    {
        $this->request('DELETE', '/api/v1/runtime-groups/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function runtimeGroupUsage(string $id, string $from, string $to): array
    {
        return $this->request(
            'GET',
            '/api/v1/runtime-groups/' . rawurlencode($id) . '/usage'
                . $this->query(['from' => $from, 'to' => $to]),
        );
    }

    /** @return array<string, mixed> */
    public function podConfirmation(string $podId, ?int $waitSeconds = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/confirmation'
                . $this->query(['wait_seconds' => $waitSeconds]),
        );
    }

    // ------------------------------------------------------------- addresses

    /** @param array<string, scalar|null> $query node_id, pod_id
     *  @return array<int, array<string, mixed>> */
    public function listIps(array $query = []): array
    {
        return $this->request('GET', '/api/v1/ips' . $this->query($query))['addresses'];
    }

    /** @param array<string, mixed> $request */
    public function createIp(array $request): array
    {
        return $this->request('POST', '/api/v1/ips', $request);
    }

    /** @return array<string, mixed> */
    public function getIp(string $id): array
    {
        return $this->request('GET', '/api/v1/ips/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateIp(string $id, array $request): array
    {
        return $this->request('PATCH', '/api/v1/ips/' . rawurlencode($id), $request);
    }

    public function deleteIp(string $id): void
    {
        $this->request('DELETE', '/api/v1/ips/' . rawurlencode($id));
    }

    /** @param array<string, scalar|null> $query node_id, pod_id
     *  @return array<int, array<string, mixed>> */
    public function listForwards(array $query = []): array
    {
        return $this->request('GET', '/api/v1/forwards' . $this->query($query))['forwards'];
    }

    /** @return array<int, array<string, mixed>> */
    public function listPodIps(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/ips',
        )['addresses'];
    }

    /** @param array<string, mixed> $request */
    public function bindPodIp(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/ips',
            $request,
        );
    }

    public function unbindPodIp(string $podId, string $ipId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId) . '/ips/' . rawurlencode($ipId),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listPodForwards(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/forwards',
        )['forwards'];
    }

    /** @param array<string, mixed> $request */
    public function createPodForward(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/forwards',
            $request,
        );
    }

    /** @param array<string, mixed> $request */
    public function updatePodForward(string $podId, string $forwardId, array $request): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/forwards/' . rawurlencode($forwardId),
            $request,
        );
    }

    public function deletePodForward(string $podId, string $forwardId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/forwards/' . rawurlencode($forwardId),
        );
    }

    // -------------------------------------------------------------- platform

    /** @return array<string, mixed> */
    public function getPlatform(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/platform',
        );
    }

    /** @param array<string, mixed> $spec */
    public function putPlatform(string $podId, array $spec): array
    {
        return $this->request(
            'PUT',
            '/api/v1/pods/' . rawurlencode($podId) . '/platform',
            $spec,
        );
    }

    // ---------------------------------------------------- autonomous updates

    /** @return array<string, mixed> */
    public function getUpdatePolicy(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/update-policy',
        );
    }

    /** @param array<string, mixed> $policy */
    public function putUpdatePolicy(string $podId, array $policy): array
    {
        return $this->request(
            'PUT',
            '/api/v1/pods/' . rawurlencode($podId) . '/update-policy',
            $policy,
        );
    }

    /** @return array<string, mixed> */
    public function checkPodUpdates(string $podId): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/updates/check',
            [],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listRevisions(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/revisions',
        )['revisions'];
    }

    public function deleteRevision(string $podId, string $revisionId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/revisions/' . rawurlencode($revisionId),
        );
    }

    /** @return array<string, mixed> */
    public function pinRevision(string $podId, string $revisionId, bool $pinned = true): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/revisions/' . rawurlencode($revisionId) . '/pin',
            ['pinned' => $pinned],
        );
    }

    /**
     * Activate one existing revision with a cutover strategy.
     *
     * `hard` starts the revision in the background and switches at once after
     * `wait_seconds` (0 = immediate); `gradual` moves traffic through `steps`
     * (increasing, last 100), each after `step_seconds`. The same call rolls back
     * to an older revision.
     *
     * @param  list<int>|null  $steps
     * @return array<string, mixed>
     */
    public function activateRevision(
        string $podId,
        string $revisionId,
        string $mode = 'gradual',
        ?array $steps = null,
        ?int $stepSeconds = null,
        ?int $waitSeconds = null,
    ): array {
        $body = ['mode' => $mode];
        if ($steps !== null) {
            $body['steps'] = $steps;
        }
        if ($stepSeconds !== null) {
            $body['step_seconds'] = $stepSeconds;
        }
        if ($waitSeconds !== null) {
            $body['wait_seconds'] = $waitSeconds;
        }
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/revisions/' . rawurlencode($revisionId) . '/activate',
            $body,
        );
    }

    /** @return array<string, mixed>|null */
    public function getRollout(string $podId): ?array
    {
        $status = $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/rollout',
        );
        return $status['rollout'] ?? null;
    }

    // -------------------------------------------------------------- metering

    /** @return array<string, mixed> */
    public function getMeterView(string $scope, ?string $scopeId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/metering' . $this->query(['scope' => $scope, 'scope_id' => $scopeId]),
        );
    }

    /** @param array<string, mixed> $request scope, scope_id, unit */
    public function setMeterView(array $request): array
    {
        return $this->request('PUT', '/api/v1/metering', $request);
    }

    /** @return array<string, mixed> */
    public function podUsage(string $podId, string $from, string $to): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/usage'
                . $this->query(['from' => $from, 'to' => $to]),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function podUsageSamples(string $podId, string $from, string $to): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/usage/samples'
                . $this->query(['from' => $from, 'to' => $to]),
        )['samples'];
    }

    // -------------------------------------------------------- jobs and transfer

    /** @param array<string, scalar|null> $query status, limit
     *  @return array<int, array<string, mixed>> */
    public function listAllJobs(array $query = []): array
    {
        return $this->request('GET', '/api/v1/jobs' . $this->query($query))['jobs'];
    }

    /** @return array<string, mixed> */
    public function getJob(string $id): array
    {
        return $this->request('GET', '/api/v1/jobs/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function retryJob(string $id): array
    {
        return $this->request('POST', '/api/v1/jobs/' . rawurlencode($id) . '/retry', []);
    }

    /** @param array<string, mixed> $request node_id, mode, drain_seconds */
    public function transferPod(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/transfer',
            $request,
        );
    }

    // -------------------------------------------------------------- webhooks

    /** @return array<int, array<string, mixed>> */
    public function listWebhooks(): array
    {
        return $this->request('GET', '/api/v1/webhooks')['webhooks'];
    }

    /** @param array<string, mixed> $request url, events, description, enabled */
    public function createWebhook(array $request): array
    {
        return $this->request('POST', '/api/v1/webhooks', $request);
    }

    /** @param array<string, mixed> $request */
    public function updateWebhook(string $id, array $request): array
    {
        return $this->request('PATCH', '/api/v1/webhooks/' . rawurlencode($id), $request);
    }

    public function deleteWebhook(string $id): void
    {
        $this->request('DELETE', '/api/v1/webhooks/' . rawurlencode($id));
    }

    public function rotateWebhookSecret(string $id): string
    {
        return $this->request(
            'POST',
            '/api/v1/webhooks/' . rawurlencode($id) . '/rotate-secret',
            [],
        )['secret'];
    }

    /** @return array<int, array<string, mixed>> */
    public function listWebhookDeliveries(string $id): array
    {
        return $this->request(
            'GET',
            '/api/v1/webhooks/' . rawurlencode($id) . '/deliveries',
        )['deliveries'];
    }

    // ---------------------------------------------------------- template nests

    /** @param array<string, mixed> $request nest, version, channel, save */
    public function importNest(array $request): array
    {
        return $this->request('POST', '/api/v1/templates/nests', $request);
    }

    // --------------------------------------------------------- files and limits

    public function downloadFile(string $podId, string $path): string
    {
        return $this->requestRaw(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/download'
                . $this->query(['path' => $path]),
            'application/octet-stream',
        );
    }

    /** @return array<string, mixed> */
    public function uploadFile(string $podId, string $path, string $contents): array
    {
        return $this->requestBody(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/files/upload'
                . $this->query(['path' => $path]),
            $contents,
            'application/octet-stream',
        );
    }

    /** @param array<string, mixed> $limits */
    public function updateLimits(string $podId, array $limits): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/pods/' . rawurlencode($podId) . '/limits',
            ['limits' => $limits],
        );
    }

    /** @param array<string, mixed> $request */
    public function deployCompose(array $request): array
    {
        return $this->request('POST', '/api/v1/compose/deploy', $request);
    }

    // ----------------------------------------------------------- storage

    /** @return array<int, array<string, mixed>> */
    public function listStorageBackends(?string $tenantId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/storage/backends'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
        )['backends'];
    }

    /** @param array<string, mixed> $request */
    public function createStorageBackend(array $request): array
    {
        return $this->request('POST', '/api/v1/storage/backends', $request);
    }

    /** @return array<string, mixed> */
    public function getStorageBackend(string $id): array
    {
        return $this->request('GET', '/api/v1/storage/backends/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateStorageBackend(string $id, array $request): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/storage/backends/' . rawurlencode($id),
            $request,
        );
    }

    public function deleteStorageBackend(string $id): void
    {
        $this->request('DELETE', '/api/v1/storage/backends/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listVolumes(?string $resourceGroupId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/storage/volumes'
                . $this->query(
                    $resourceGroupId === null ? [] : ['resource_group_id' => $resourceGroupId],
                ),
        )['volumes'];
    }

    /** @param array<string, mixed> $request */
    public function createVolume(array $request): array
    {
        return $this->request('POST', '/api/v1/storage/volumes', $request);
    }

    /** @return array<string, mixed> */
    public function getVolume(string $id): array
    {
        return $this->request('GET', '/api/v1/storage/volumes/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateVolume(string $id, array $request): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/storage/volumes/' . rawurlencode($id),
            $request,
        );
    }

    public function deleteVolume(string $id): void
    {
        $this->request('DELETE', '/api/v1/storage/volumes/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listStorageGatewayTokens(string $tenantId): array
    {
        return $this->request(
            'GET',
            '/api/v1/storage/gateway-tokens' . $this->query(['tenant_id' => $tenantId]),
        )['tokens'];
    }

    /** @param array<string, mixed> $request */
    public function createStorageGatewayToken(array $request): array
    {
        return $this->request('POST', '/api/v1/storage/gateway-tokens', $request);
    }

    public function revokeStorageGatewayToken(string $id, string $tenantId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/storage/gateway-tokens/' . rawurlencode($id)
                . $this->query(['tenant_id' => $tenantId]),
        );
    }

    /** @return array<string, mixed> */
    public function reportVolumeUsage(string $id, int $usedBytes): array
    {
        return $this->request(
            'POST',
            '/api/v1/storage/volumes/' . rawurlencode($id) . '/usage',
            ['used_bytes' => $usedBytes],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listPodVolumes(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/volumes',
        )['mounts'];
    }

    /** @param array<string, mixed> $request */
    public function mountVolume(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/volumes',
            $request,
        );
    }

    public function unmountVolume(string $podId, string $mountId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/volumes/' . rawurlencode($mountId),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listExternalDatabases(?string $tenantId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/external-databases'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
        )['databases'];
    }

    /** @param array<string, mixed> $request */
    public function createExternalDatabase(array $request): array
    {
        return $this->request('POST', '/api/v1/external-databases', $request);
    }

    /** @return array<string, mixed> */
    public function getExternalDatabase(string $id): array
    {
        return $this->request('GET', '/api/v1/external-databases/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateExternalDatabase(string $id, array $request): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/external-databases/' . rawurlencode($id),
            $request,
        );
    }

    public function deleteExternalDatabase(string $id): void
    {
        $this->request('DELETE', '/api/v1/external-databases/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function testExternalDatabase(string $id): array
    {
        return $this->request(
            'POST',
            '/api/v1/external-databases/' . rawurlencode($id) . '/test',
            [],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listPodExternalDatabases(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/external-databases',
        )['bindings'];
    }

    /** @param array<string, mixed> $request */
    public function bindPodExternalDatabase(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/external-databases',
            $request,
        );
    }

    public function unbindPodExternalDatabase(string $podId, string $bindingId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId)
                . '/external-databases/' . rawurlencode($bindingId),
        );
    }

    // ----------------------------------------------------- metrics storage

    /** @return array<string, mixed> */
    public function getMetricsStoragePolicy(?string $tenantId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/metrics/storage-policy'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
        );
    }

    /** @param array<string, mixed> $policy */
    public function putMetricsStoragePolicy(array $policy, ?string $tenantId = null): array
    {
        return $this->request(
            'PUT',
            '/api/v1/metrics/storage-policy'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
            $policy,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listMetricsArchives(?string $tenantId = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/metrics/archive'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
        )['archives'];
    }

    /** @return array<string, mixed> */
    public function archiveMetrics(?string $tenantId = null): array
    {
        return $this->request(
            'POST',
            '/api/v1/metrics/archive-now'
                . $this->query($tenantId === null ? [] : ['tenant_id' => $tenantId]),
            [],
        );
    }

    // --------------------------------------------------------------- git

    /** @return array<int, array<string, mixed>> */
    public function listGitCredentials(): array
    {
        return $this->request('GET', '/api/v1/git/credentials');
    }

    /** @param array<string, mixed> $request */
    public function createGitCredential(array $request): array
    {
        return $this->request('POST', '/api/v1/git/credentials', $request);
    }

    /** @return array<string, mixed> */
    public function getGitCredential(string $id): array
    {
        return $this->request('GET', '/api/v1/git/credentials/' . rawurlencode($id));
    }

    public function deleteGitCredential(string $id): void
    {
        $this->request('DELETE', '/api/v1/git/credentials/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function listGitSources(): array
    {
        return $this->request('GET', '/api/v1/git/sources');
    }

    /** @param array<string, mixed> $request */
    public function createGitSource(array $request): array
    {
        return $this->request('POST', '/api/v1/git/sources', $request);
    }

    /** @return array<string, mixed> */
    public function getGitSource(string $id): array
    {
        return $this->request('GET', '/api/v1/git/sources/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $request */
    public function updateGitSource(string $id, array $request): array
    {
        return $this->request('PATCH', '/api/v1/git/sources/' . rawurlencode($id), $request);
    }

    public function deleteGitSource(string $id): void
    {
        $this->request('DELETE', '/api/v1/git/sources/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function triggerGitBuild(string $id, ?string $reference = null): array
    {
        return $this->request(
            'POST',
            '/api/v1/git/sources/' . rawurlencode($id) . '/build',
            $reference === null ? [] : ['reference' => $reference],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listGitBuilds(): array
    {
        return $this->request('GET', '/api/v1/git/builds')['builds'];
    }

    /** @return array<string, mixed> */
    public function getGitBuild(string $id): array
    {
        return $this->request('GET', '/api/v1/git/builds/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function buildUsage(string $id, ?int $fromMs = null, ?int $toMs = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/git/builds/' . rawurlencode($id) . '/usage'
                . $this->query(['from_ms' => $fromMs, 'to_ms' => $toMs]),
        );
    }

    /** @return array<string, mixed> */
    public function buildUsageSamples(string $id, ?int $fromMs = null, ?int $toMs = null): array
    {
        return $this->request(
            'GET',
            '/api/v1/git/builds/' . rawurlencode($id) . '/usage/samples'
                . $this->query(['from_ms' => $fromMs, 'to_ms' => $toMs]),
        );
    }

    // ------------------------------------------------------------- images

    /** @return array<int, array<string, mixed>> */
    public function listImages(): array
    {
        return $this->request('GET', '/api/v1/images')['images'];
    }

    /** @return array<string, mixed> */
    public function getImage(string $id): array
    {
        return $this->request('GET', '/api/v1/images/' . rawurlencode($id));
    }

    /** @return array<int, array<string, mixed>> */
    public function imageCopies(string $id): array
    {
        return $this->request('GET', '/api/v1/images/' . rawurlencode($id) . '/copies');
    }

    /** @return array<int, array<string, mixed>> */
    public function imagePulls(string $id): array
    {
        return $this->request('GET', '/api/v1/images/' . rawurlencode($id) . '/pulls');
    }

    /** @return array<int, array<string, mixed>> */
    public function imageUsage(): array
    {
        return $this->request('GET', '/api/v1/images/usage');
    }

    /** @return array<int, array<string, mixed>> */
    public function allImagePulls(): array
    {
        return $this->request('GET', '/api/v1/images/pulls');
    }

    /** @param array<string, mixed> $request */
    public function pullImage(array $request): array
    {
        return $this->request('POST', '/api/v1/images/pull', $request);
    }

    /** @param array<string, mixed> $request */
    public function uploadImage(array $request): array
    {
        return $this->request('POST', '/api/v1/images/upload', $request);
    }

    public function deleteImage(string $id): void
    {
        $this->request('DELETE', '/api/v1/images/' . rawurlencode($id));
    }

    // ------------------------------------------------- pod port bindings

    /** @return array<int, array<string, mixed>> */
    public function listPodPorts(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/ports',
        )['ports'];
    }

    /** @param array<string, mixed> $request */
    public function createPodPort(string $podId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/pods/' . rawurlencode($podId) . '/ports',
            $request,
        );
    }

    /** @param array<string, mixed> $request */
    public function updatePodPort(string $podId, string $portId, array $request): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/pods/' . rawurlencode($podId) . '/ports/' . rawurlencode($portId),
            $request,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function podPortHistory(string $podId): array
    {
        return $this->request(
            'GET',
            '/api/v1/pods/' . rawurlencode($podId) . '/ports/history',
        )['events'];
    }

    public function deletePodPort(string $podId, string $portId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/pods/' . rawurlencode($podId) . '/ports/' . rawurlencode($portId),
        );
    }

    // ------------------------------------------------------ compose

    /** @return array<string, mixed> */
    public function convertCompose(string $yaml): array
    {
        return $this->requestBody(
            'POST',
            '/api/v1/compose/convert',
            $yaml,
            'application/yaml',
        );
    }

    // --------------------------------------------------- TOML templates

    /** @return array<string, mixed> */
    public function importTemplateToml(string $toml): array
    {
        return $this->requestBody(
            'POST',
            '/api/v1/templates/import?save=true',
            $toml,
            'application/toml',
        );
    }

    public function exportTemplateToml(string $id): string
    {
        return $this->requestRaw(
            'GET',
            '/api/v1/templates/' . rawurlencode($id) . '/export?format=toml',
            'application/toml',
        );
    }

    // ---------------------------------------------------- tenants and RGs

    /** @return array<int, array<string, mixed>> */
    public function listTenants(): array
    {
        return $this->request('GET', '/api/v1/tenants');
    }

    /** @param array<string, mixed> $tenant */
    public function createTenant(array $tenant): array
    {
        return $this->request('POST', '/api/v1/tenants', $tenant);
    }

    /** @return array<string, mixed> */
    public function getTenant(string $id): array
    {
        return $this->request('GET', '/api/v1/tenants/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $patch */
    public function updateTenant(string $id, array $patch): array
    {
        return $this->request('PATCH', '/api/v1/tenants/' . rawurlencode($id), $patch);
    }

    /** @return array<int, string> */
    public function listTenantAccess(string $tenantId): array
    {
        return $this->request(
            'GET',
            '/api/v1/tenants/' . rawurlencode($tenantId) . '/access',
        )['api_key_ids'];
    }

    /** @return array<int, string> */
    public function grantTenantAccess(string $tenantId, string $apiKeyId): array
    {
        return $this->request(
            'POST',
            '/api/v1/tenants/' . rawurlencode($tenantId) . '/access',
            ['api_key_id' => $apiKeyId],
        )['api_key_ids'];
    }

    public function revokeTenantAccess(string $tenantId, string $keyId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/tenants/' . rawurlencode($tenantId)
                . '/access/' . rawurlencode($keyId),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listTenantResourceGroups(string $tenantId): array
    {
        return $this->request(
            'GET',
            '/api/v1/tenants/' . rawurlencode($tenantId) . '/resource-groups',
        );
    }

    /** @param array<string, mixed> $group */
    public function createResourceGroup(string $tenantId, array $group): array
    {
        return $this->request(
            'POST',
            '/api/v1/tenants/' . rawurlencode($tenantId) . '/resource-groups',
            $group,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listResourceGroups(): array
    {
        return $this->request('GET', '/api/v1/resource-groups');
    }

    /** @return array<string, mixed> */
    public function getResourceGroup(string $id): array
    {
        return $this->request('GET', '/api/v1/resource-groups/' . rawurlencode($id));
    }

    /** @param array<string, mixed> $patch */
    public function updateResourceGroup(string $id, array $patch): array
    {
        return $this->request(
            'PATCH',
            '/api/v1/resource-groups/' . rawurlencode($id),
            $patch,
        );
    }

    public function deleteResourceGroup(string $id): void
    {
        $this->request('DELETE', '/api/v1/resource-groups/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function getReference(string $referenceId): array
    {
        return $this->request(
            'GET',
            '/api/v1/references/' . rawurlencode($referenceId),
        );
    }

    // ---------------------------------------------------------- transfers

    /** @param array<string, mixed> $request tenant_id, label */
    public function createTransferKey(array $request): array
    {
        return $this->request('POST', '/api/v1/transfer-keys', $request);
    }

    /** @return array<int, array<string, mixed>> */
    public function listTransferKeys(string $tenantId): array
    {
        return $this->request(
            'GET',
            '/api/v1/transfer-keys' . $this->query(['tenant_id' => $tenantId]),
        );
    }

    public function revokeTransferKey(string $id, string $tenantId): void
    {
        $this->request(
            'DELETE',
            '/api/v1/transfer-keys/' . rawurlencode($id)
                . $this->query(['tenant_id' => $tenantId]),
        );
    }

    /** @param array<string, mixed> $request transfer_key, idempotency_key */
    public function startTransfer(string $resourceGroupId, array $request): array
    {
        return $this->request(
            'POST',
            '/api/v1/resource-groups/' . rawurlencode($resourceGroupId) . '/transfer',
            $request,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listTransfers(string $resourceGroupId): array
    {
        return $this->request(
            'GET',
            '/api/v1/resource-groups/' . rawurlencode($resourceGroupId) . '/transfers',
        );
    }

    /** @return array<string, mixed> */
    public function getTransfer(string $id): array
    {
        return $this->request('GET', '/api/v1/transfers/' . rawurlencode($id));
    }

    /** @return array<string, mixed> */
    public function cancelTransfer(string $id): array
    {
        return $this->request('POST', '/api/v1/transfers/' . rawurlencode($id) . '/cancel', []);
    }

    /** @return array<string, mixed> */
    public function dismissTransfer(string $id): array
    {
        return $this->request('POST', '/api/v1/transfers/' . rawurlencode($id) . '/dismiss', []);
    }

    // ------------------------------------------------- usage and profiles

    /** @return array<string, mixed> */
    public function usage(
        string $scope,
        string $scopeId,
        string $window,
        string $unit = 'times',
        bool $includeExact = false,
    ): array {
        return $this->request(
            'GET',
            '/api/v1/usage' . $this->query([
                'scope' => $scope,
                'scope_id' => $scopeId,
                'window' => $window,
                'unit' => $unit,
                'include_exact' => $includeExact ? 'true' : 'false',
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function billingReport(string $scope, string $scopeId, int $fromMs, int $toMs): array
    {
        return $this->request(
            'GET',
            '/api/v1/billing' . $this->query([
                'scope' => $scope,
                'scope_id' => $scopeId,
                'from_ms' => $fromMs,
                'to_ms' => $toMs,
            ]),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listMeteringProfiles(): array
    {
        return $this->request('GET', '/api/v1/metering/profiles');
    }

    /** @param array<string, mixed> $profile */
    public function createMeteringProfile(array $profile): array
    {
        return $this->request('POST', '/api/v1/metering/profiles', $profile);
    }

    /**
     * Stream `GET /pods/{id}/logs/stream` (SSE) and call `$onLine` for each
     * `data:` line. `$maxSeconds = 0` disables the timeout.
     *
     * @param callable(string):void $onLine
     */
    public function streamLogs(string $podId, callable $onLine, int $maxSeconds = 300): void
    {
        $handle = curl_init(
            $this->baseUrl . '/api/v1/pods/' . rawurlencode($podId) . '/logs/stream'
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
     * Requests a binary response (backup or file download).
     */
    public function requestRaw(
        string $method,
        string $path,
        string $accept = 'application/gzip',
    ): string
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
                'Accept: ' . $accept,
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

    /**
     * Sends a raw request body and returns the decoded JSON response.
     *
     * @return array<string, mixed>
     */
    private function requestBody(
        string $method,
        string $path,
        string $body,
        string $contentType,
    ): array {
        $handle = curl_init($this->baseUrl . $path);
        if ($handle === false) {
            throw new \RuntimeException('could not initialise cURL');
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: ' . $contentType,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => max($this->timeout, 600),
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
