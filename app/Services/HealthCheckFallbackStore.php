<?php

namespace App\Services;

final class HealthCheckFallbackStore
{
    public function __construct(private string $path) {}

    public function append(array $record): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Health fallback directory cannot be created.');
        }
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($line) || file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Health fallback record cannot be written.');
        }
    }

    /** Replays the whole locked file; UUID uniqueness makes retries safe. */
    public function replay(callable $insert): int
    {
        if (!is_file($this->path)) {
            return 0;
        }
        $handle = fopen($this->path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            throw new \RuntimeException('Health fallback file cannot be locked.');
        }
        try {
            rewind($handle);
            $content = stream_get_contents($handle);
            $lines = preg_split('~\R~', is_string($content) ? $content : '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $count = 0;
            foreach ($lines as $line) {
                $record = json_decode($line, true);
                if (!is_array($record) || empty($record['check_uuid'])) {
                    throw new \RuntimeException('Health fallback contains an invalid record.');
                }
                $record['source'] = 'recovered';
                $insert($record);
                $count++;
            }
            ftruncate($handle, 0);
            fflush($handle);
            return $count;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
