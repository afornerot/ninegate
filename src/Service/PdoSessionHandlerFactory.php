<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class PdoSessionHandlerFactory
{
    public static function create(Connection $connection): PdoSessionHandler
    {
        $params = $connection->getParams();
        $drvParams = $params['driverOptions'] ?? [];

        $dsn = match ($params['driver']) {
            'pdo_pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $params['host'] ?? 'localhost',
                $params['port'] ?? 5432,
                $params['dbname'],
            ),
            'pdo_mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $params['host'] ?? 'localhost',
                $params['port'] ?? 3306,
                $params['dbname'],
            ),
            'pdo_sqlite' => sprintf('sqlite:%s', $params['path'] ?? $params['dbname']),
            default => throw new \RuntimeException(sprintf('Unsupported driver "%s" for session handler.', $params['driver'])),
        };

        $pdo = new \PDO($dsn, $params['user'] ?? null, $params['password'] ?? null, $drvParams);

        return new PdoSessionHandler($pdo, [
            'db_table' => 'sessions',
            'db_id_col' => 'sess_id',
            'db_data_col' => 'sess_data',
            'db_lifetime_col' => 'sess_lifetime',
            'db_time_col' => 'sess_time',
        ]);
    }
}
