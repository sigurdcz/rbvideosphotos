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
            $cfg = Config::get('database');
            $driver = $cfg['driver'] ?? 'mysql';

            try {
                if ($driver === 'sqlite') {
                    $path = $cfg['path'] ?? dirname(__DIR__, 2) . '/storage/database.sqlite';
                    self::$instance = new PDO('sqlite:' . $path, null, null, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    self::$instance->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
                } else {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                        $cfg['host'],
                        $cfg['port'],
                        $cfg['dbname'],
                        $cfg['charset']
                    );
                    self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                }
            } catch (PDOException $e) {
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
}
