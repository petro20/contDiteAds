<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Alinha o timezone do MySQL com o do PHP (America/Sao_Paulo definido
        // em includes/config.php). Sem isso, CURDATE() e NOW() no MySQL ficam
        // em UTC enquanto date() do PHP fica em -03, podendo divergir o
        // "dia atual" em até 3h (cobranças vencendo no dia errado, etc).
        try {
            $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))
                ->format('P'); // ex: '-03:00'
            $pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (Throwable $e) {
            // Tolera servidores sem tabelas de timezone — o offset numérico funciona em qualquer MySQL
            error_log('SET time_zone failed: ' . $e->getMessage());
        }
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Falha ao conectar ao banco de dados.');
    }
    return $pdo;
}
