<?php

header('Content-Type: text/plain');
header('Cache-Control: no-store');

$root = dirname(__DIR__);

$autoload = $root.'/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv($root.'/.env');
}

$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

$fail = function (string $message) {
    http_response_code(503);
    echo "unready: $message\n";
    exit(1);
};

if (!$databaseUrl) {
    $fail('DATABASE_URL not set');
}

$parts = parse_url($databaseUrl);
if (false === $parts || !str_starts_with($parts['scheme'] ?? '', 'postgres')) {
    $fail('DATABASE_URL is not a valid postgres DSN');
}

$path = parse_url($databaseUrl, PHP_URL_QUERY) ?? '';
parse_str($path, $params);

$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s',
    $parts['host'] ?? 'localhost',
    $parts['port'] ?? 5432,
    ltrim($parts['path'] ?? '', '/'),
);

if (isset($params['sslmode'])) {
    $dsn .= ';sslmode='.$params['sslmode'];
}

try {
    $pdo = new PDO($dsn, $parts['user'] ?? null, urldecode($parts['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    $pdo->exec('SELECT 1');
    $pdo = null;
} catch (Throwable $e) {
    $fail('database unreachable ('.$e->getMessage().')');
}

http_response_code(200);
echo "ready\n";
