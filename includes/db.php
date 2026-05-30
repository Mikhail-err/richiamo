<?php
// ============================================================
//  Richiamo Coffee — Database Connection (PDO)
// ============================================================

require_once __DIR__ . '/../config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('<pre style="color:red">Database connection failed: ' . $e->getMessage() . '</pre>');
            } else {
                die('Service temporarily unavailable. Please try again later.');
            }
        }
    }

    return $pdo;
}
