<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = self::createConnection();
            } catch (PDOException $e) {
                $cfg = Config::get('database');
                $driver = strtolower((string)($cfg['driver'] ?? 'mysql'));
                Logger::critical('database', 'Database connection failed', [
                    'driver' => $driver,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                if (Config::get('app', 'debug')) {
                    throw $e;
                }
                http_response_code(500);
                echo 'Database connection failed.';
                exit;
            }
        }
        return self::$instance;
    }

    /**
     * Creates a standalone connection and lets the caller handle failure.
     * Health checks use this path so a database outage becomes a structured
     * HTTP 503 instead of terminating with the regular frontend error page.
     */
    public static function createConnection(): PDO
    {
        $cfg = Config::get('database');
        $driver = strtolower((string)($cfg['driver'] ?? 'mysql'));
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            throw new PDOException('Unsupported database driver: ' . $driver . '. This project is configured for MySQL/MariaDB only.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['charset']
        );

        return new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
