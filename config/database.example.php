<?php
/**
 * Copy this file to config/database.php and fill in the real credentials.
 * Never commit config/database.php with production passwords.
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'cpaneluser_balancete');
define('DB_USER',    'cpaneluser_balancete_user');
define('DB_PASS',    'change-me');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(503);
            die('Database connection error. Check config/database.php.');
        }
    }

    return $pdo;
}
