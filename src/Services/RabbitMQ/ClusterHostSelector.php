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

        $orderedHosts = $this->rotatedHosts($hosts);
        $healthyHosts = [];
        $recoveringHosts = [];
        $coolingHosts = [];

        foreach ($orderedHosts as $host) {
            $failureState = $this->failedHostState($host);

            if ($failureState === null) {
                $healthyHosts[] = $host;
                continue;
            }

            if ($this->isCoolingDown($failureState)) {
                $coolingHosts[] = $host;
                continue;
            }

            $recoveringHosts[] = $host;
        }

        if ($healthyHosts === [] && $recoveringHosts === []) {
            return $coolingHosts !== [] ? $coolingHosts : $orderedHosts;
        }

        if ($healthyHosts === []) {
            return array_merge($recoveringHosts, $coolingHosts);
        }

        if ($recoveringHosts !== [] && $this->shouldProbeRecoveredHostFirst()) {
            $probeHost = array_shift($recoveringHosts);

            return array_merge([$probeHost], $healthyHosts, $recoveringHosts, $coolingHosts);
        }

        return array_merge($healthyHosts, $recoveringHosts, $coolingHosts);
    }

    public function rememberFailedHost(array $host, ?string $error = null): void
    {
        try {
            $state = $this->failedHostState($host) ?? [];
            $consecutiveFailures = ((int) ($state['consecutive_failures'] ?? 0)) + 1;
            $cooldownSeconds = $this->cooldownSecondsForFailureCount($consecutiveFailures);
            $now = time();

            Cache::forever($this->failedHostCacheKey($host), [
                'consecutive_failures' => $consecutiveFailures,
                'retry_after_epoch' => $now + $cooldownSeconds,
                'last_failed_at_epoch' => $now,
                'last_error' => $error,
            ]);
        } catch (Throwable $exception) {
            // Sem cache compartilhado a conexão continua funcional; só perde a memória de falha.
        }
    }

    public function clearFailedHost(array $host): void
    {
        try {
            Cache::forget($this->failedHostCacheKey($host));
        } catch (Throwable $exception) {
            // Sem cache compartilhado a conexão continua funcional; só perde a limpeza de memória de falha.
        }
    }

    public function rememberSuccessfulHost(array $host): void
    {
        try {
            Cache::forever($this->lastHostCacheKey(), $this->hostKey($host));
            Cache::forever($this->lastIndexCacheKey(), $this->hostIndex($host));
            $this->clearFailedHost($host);
        } catch (Throwable $exception) {
            // Sem cache compartilhado a conexão continua funcional; só perde a memória de distribuição.
        }
    }

    private function rotatedHosts(array $hosts): array
    {
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

    private function normalizedHosts(): array
    {
        $configuredHosts = $this->connectionConfig['hosts'] ?? [];
        $defaults = $this->baseDefaults();

        if (!is_array($configuredHosts) || $configuredHosts === []) {
            return [$this->sanitizeTimeouts($defaults)];
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

            $normalized[] = $this->sanitizeTimeouts(array_merge($defaults, $configuredHost, ['host' => $host]));
        }

        return $normalized !== [] ? array_values($normalized) : [$this->sanitizeTimeouts($defaults)];
    }

    private function sanitizeTimeouts(array $host): array
    {
        // O php-amqplib exige read_write_timeout >= 2x heartbeat; abaixo disso a
        // detecção de heartbeat quebra e um nó caído deixa o socket pendurado.
        $heartbeat = (int) ($host['heartbeat'] ?? 0);
        $readWriteTimeout = (float) ($host['read_write_timeout'] ?? 3.0);

        if ($heartbeat > 0 && $readWriteTimeout < 2 * $heartbeat) {
            $host['read_write_timeout'] = (float) (2 * $heartbeat);
        }

        return $host;
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

    private function failedHostState(array $host): ?array
    {
        try {
            $value = Cache::get($this->failedHostCacheKey($host));

            return is_array($value) ? $value : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function failedHostCacheKey(array $host): string
    {
        return $this->failedHostCachePrefix() . $this->hostKey($host);
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

    private function failedHostCachePrefix(): string
    {
        return (string) ($this->clusterConfig['failed_host_cache_prefix'] ?? 'rabbitmq:cluster:failed-host:');
    }

    private function failedHostBaseCooldownSeconds(): int
    {
        return max(1, (int) ($this->clusterConfig['failed_host_base_cooldown_seconds'] ?? 30));
    }

    private function failedHostMaxCooldownSeconds(): int
    {
        return max($this->failedHostBaseCooldownSeconds(), (int) ($this->clusterConfig['failed_host_max_cooldown_seconds'] ?? 300));
    }

    private function failedHostProbeEvery(): int
    {
        return max(1, (int) ($this->clusterConfig['failed_host_probe_every'] ?? 10));
    }

    private function attemptCounterCacheKey(): string
    {
        return (string) ($this->clusterConfig['attempt_counter_cache_key'] ?? 'rabbitmq:cluster:connection-attempt-counter');
    }

    private function isCoolingDown(array $failureState): bool
    {
        return ((int) ($failureState['retry_after_epoch'] ?? 0)) > time();
    }

    private function cooldownSecondsForFailureCount(int $consecutiveFailures): int
    {
        $base = $this->failedHostBaseCooldownSeconds();
        $max = $this->failedHostMaxCooldownSeconds();
        $multiplier = max(0, $consecutiveFailures - 1);
        $seconds = $base * (2 ** $multiplier);

        return min($seconds, $max);
    }

    private function shouldProbeRecoveredHostFirst(): bool
    {
        try {
            $attempt = Cache::increment($this->attemptCounterCacheKey());
            return ((int) $attempt % $this->failedHostProbeEvery()) === 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}
