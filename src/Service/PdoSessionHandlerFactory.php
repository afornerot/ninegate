<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class PdoSessionHandlerFactory
{
    public static function create(Connection $connection): PdoSessionHandler
    {
        $pdo = $connection->getNativeConnection();

        return new PdoSessionHandler($pdo, [
            'db_table' => 'sessions',
            'db_id_col' => 'sess_id',
            'db_data_col' => 'sess_data',
            'db_lifetime_col' => 'sess_lifetime',
            'db_time_col' => 'sess_time',
        ]);
    }
}
