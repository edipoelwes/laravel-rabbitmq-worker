<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ClusterHostSelector
{
    private array $connectionConfig;
    private array $clusterConfig;

    public function __construct(array $connectionConfig, array $clusterConfig = [])
    {
        $this->connectionConfig = $connectionConfig;
        $this->clusterConfig = $clusterConfig;
    }

    public function orderedHosts(): array
    {
        $hosts = $this->normalizedHosts();
        $count = count($hosts);

        if ($count <= 1) {
            return $hosts;
        }

        $startIndex = 0;
        $lastHostKey = $this->cachedLastHostKey();

        if (is_string($lastHostKey)) {
            foreach ($hosts as $index => $host) {
                if ($this->hostKey($host) === $lastHostKey) {
                    $startIndex = ($index + 1) % $count;
                    break;
                }
            }
        } else {
            $lastIndex = $this->cachedLastIndex();
            if (is_int($lastIndex) && $lastIndex >= 0) {
                $startIndex = ($lastIndex + 1) % $count;
            }
        }

        return array_merge(
            array_slice($hosts, $startIndex),
            array_slice($hosts, 0, $startIndex)
        );
    }

    public function rememberSuccessfulHost(array $host): void
    {
        try {
            Cache::forever($this->lastHostCacheKey(), $this->hostKey($host));
            Cache::forever($this->lastIndexCacheKey(), $this->hostIndex($host));
        } catch (Throwable $exception) {
            // Sem cache compartilhado a conexão continua funcional; só perde a memória de distribuição.
        }
    }

    private function normalizedHosts(): array
    {
        $configuredHosts = $this->connectionConfig['hosts'] ?? [];
        $defaults = $this->baseDefaults();

        if (!is_array($configuredHosts) || $configuredHosts === []) {
            return [$defaults];
        }

        $normalized = [];

        foreach ($configuredHosts as $configuredHost) {
            if (is_string($configuredHost)) {
                $configuredHost = ['host' => trim($configuredHost)];
            }

            if (!is_array($configuredHost)) {
                continue;
            }

            $host = trim((string) ($configuredHost['host'] ?? ''));
            if ($host === '') {
                continue;
            }

            $normalized[] = array_merge($defaults, $configuredHost, ['host' => $host]);
        }

        return $normalized !== [] ? array_values($normalized) : [$defaults];
    }

    private function baseDefaults(): array
    {
        return [
            'host' => (string) ($this->connectionConfig['host'] ?? 'localhost'),
            'port' => (int) ($this->connectionConfig['port'] ?? 5672),
            'user' => (string) ($this->connectionConfig['user'] ?? 'guest'),
            'password' => (string) ($this->connectionConfig['password'] ?? 'guest'),
            'vhost' => (string) ($this->connectionConfig['vhost'] ?? '/'),
            'insist' => (bool) ($this->connectionConfig['insist'] ?? false),
            'login_method' => (string) ($this->connectionConfig['login_method'] ?? 'AMQPLAIN'),
            'login_response' => $this->connectionConfig['login_response'] ?? null,
            'locale' => (string) ($this->connectionConfig['locale'] ?? 'en_US'),
            'connection_timeout' => (float) ($this->connectionConfig['connection_timeout'] ?? 3.0),
            'read_write_timeout' => (float) ($this->connectionConfig['read_write_timeout'] ?? 3.0),
            'context' => $this->connectionConfig['context'] ?? null,
            'keepalive' => (bool) ($this->connectionConfig['keepalive'] ?? false),
            'heartbeat' => (int) ($this->connectionConfig['heartbeat'] ?? 30),
            'channel_rpc_timeout' => (float) ($this->connectionConfig['channel_rpc_timeout'] ?? 0.0),
            'ssl_protocol' => $this->connectionConfig['ssl_protocol'] ?? null,
        ];
    }

    private function hostIndex(array $host): int
    {
        foreach ($this->normalizedHosts() as $index => $candidate) {
            if ($this->hostKey($candidate) === $this->hostKey($host)) {
                return $index;
            }
        }

        return 0;
    }

    private function hostKey(array $host): string
    {
        return sprintf(
            '%s:%s/%s',
            (string) ($host['host'] ?? 'localhost'),
            (string) ($host['port'] ?? 5672),
            (string) ($host['vhost'] ?? '/')
        );
    }

    private function cachedLastHostKey(): ?string
    {
        try {
            $value = Cache::get($this->lastHostCacheKey());
            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function cachedLastIndex(): ?int
    {
        try {
            $value = Cache::get($this->lastIndexCacheKey());
            return is_numeric($value) ? (int) $value : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function lastHostCacheKey(): string
    {
        return (string) ($this->clusterConfig['last_host_cache_key'] ?? 'rabbitmq:cluster:last-success-host');
    }

    private function lastIndexCacheKey(): string
    {
        return (string) ($this->clusterConfig['last_index_cache_key'] ?? 'rabbitmq:cluster:last-success-index');
    }
}
