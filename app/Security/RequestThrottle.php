<?php

namespace App\Security;

final class RequestThrottle
{
    public static function currentIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? $ip : 'unknown';
    }

    public static function isLocked(string $scope, string $key, int $windowSeconds, int $lockSeconds): bool
    {
        $state = self::loadState($scope, $key, $windowSeconds, $lockSeconds);
        return ($state['locked_until'] ?? 0) > time();
    }

    public static function retryAfter(string $scope, string $key, int $windowSeconds, int $lockSeconds): int
    {
        $state = self::loadState($scope, $key, $windowSeconds, $lockSeconds);
        return max(0, (int) (($state['locked_until'] ?? 0) - time()));
    }

    public static function hit(string $scope, string $key, int $maxAttempts, int $windowSeconds, int $lockSeconds): array
    {
        $state = self::loadState($scope, $key, $windowSeconds, $lockSeconds);
        $now = time();
        $hits = $state['hits'] ?? [];
        $hits[] = $now;
        $state['hits'] = $hits;

        if (count($hits) >= $maxAttempts) {
            $state['locked_until'] = $now + $lockSeconds;
        }

        self::writeState($scope, $key, $state);

        return [
            'attempts' => count($hits),
            'locked' => ($state['locked_until'] ?? 0) > $now,
            'retry_after' => max(0, (int) (($state['locked_until'] ?? 0) - $now)),
        ];
    }

    public static function clear(string $scope, string $key): void
    {
        $path = self::pathFor($scope, $key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Deletes state files of one scope whose last write is older than $maxAgeSeconds,
     * at most $limit files per call. A state file is rewritten on every hit, so its
     * mtime is the last activity; callers pass an age above window + lock. Only the
     * "<scope>-<sha1>.json" files of that scope are touched; other scopes are left alone.
     */
    public static function purgeStale(string $scope, int $maxAgeSeconds, int $limit, ?string $directory = null, ?int $now = null): int
    {
        if (preg_match('~^[a-z0-9_-]+$~i', $scope) !== 1 || $limit <= 0) {
            return 0;
        }
        $directory ??= self::directory();
        $handle = @opendir($directory);
        if ($handle === false) {
            return 0;
        }

        $threshold = ($now ?? time()) - max(0, $maxAgeSeconds);
        $pattern = '~^' . preg_quote($scope, '~') . '-[a-f0-9]{40}\.json$~';
        $deleted = 0;
        try {
            while ($deleted < $limit && ($entry = readdir($handle)) !== false) {
                if (preg_match($pattern, $entry) !== 1) {
                    continue;
                }
                $path = $directory . '/' . $entry;
                $mtime = @filemtime($path);
                if ($mtime !== false && $mtime < $threshold && is_file($path) && @unlink($path)) {
                    $deleted++;
                }
            }
        } finally {
            closedir($handle);
        }

        return $deleted;
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/throttle';
    }

    private static function loadState(string $scope, string $key, int $windowSeconds, int $lockSeconds): array
    {
        $path = self::pathFor($scope, $key);
        $state = [
            'hits' => [],
            'locked_until' => 0,
        ];

        if (is_file($path)) {
            $json = @file_get_contents($path);
            $data = json_decode((string) $json, true);
            if (is_array($data)) {
                $state = array_merge($state, $data);
            }
        }

        $now = time();
        $state['hits'] = array_values(array_filter(
            array_map('intval', is_array($state['hits']) ? $state['hits'] : []),
            static fn (int $hit): bool => $hit >= ($now - $windowSeconds)
        ));

        if ((int) ($state['locked_until'] ?? 0) <= $now) {
            $state['locked_until'] = 0;
            if ($state['hits'] === []) {
                self::clear($scope, $key);
                return $state;
            }
        }

        self::writeState($scope, $key, $state);
        return $state;
    }

    private static function writeState(string $scope, string $key, array $state): void
    {
        $directory = dirname(self::pathFor($scope, $key));
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents(
            self::pathFor($scope, $key),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private static function pathFor(string $scope, string $key): string
    {
        $hash = sha1($scope . '|' . $key);
        return self::directory() . '/' . $scope . '-' . $hash . '.json';
    }
}
