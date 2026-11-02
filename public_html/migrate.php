<?php

/**
 * Разовая миграция базы для подписок, заявок и восстановления пароля.
 *
 * Запуск: /migrate.php?key=КЛЮЧ
 * Ключ задаётся в config/config.php: define('MIGRATE_KEY', 'длинная-случайная-строка');
 *
 * Пока ключ не задан, скрипт отвечает 403. После миграции файл лучше удалить.
 */

require __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Core\Schema;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$expected = defined('MIGRATE_KEY') ? (string) MIGRATE_KEY : '';
$given    = (string) ($_GET['key'] ?? '');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$db = Database::connect();
$applied = [];
$errors  = [];

$addColumn = function (string $table, string $column, string $definition) use ($db, &$applied, &$errors): void {
    if (Schema::columnType($table, $column) !== null) {
        return;
    }
    try {
        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        $applied[] = $table . '.' . $column;
    } catch (\Throwable $e) {
        $errors[] = $table . '.' . $column . ': ' . $e->getMessage();
    }
};

$addColumn('companies', 'plan', "VARCHAR(32) NOT NULL DEFAULT 'legacy'");
$addColumn('companies', 'trial_ends_at', 'DATETIME NULL');
$addColumn('companies', 'paid_until', 'DATETIME NULL');
$addColumn('companies', 'max_employees', 'INT NOT NULL DEFAULT 0');
$addColumn('companies', 'max_students', 'INT NOT NULL DEFAULT 0');

$tables = [
    'leads' => "CREATE TABLE IF NOT EXISTS leads (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(190) NOT NULL,
        school VARCHAR(190) NULL,
        contact VARCHAR(190) NOT NULL,
        message TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_leads_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'password_resets' => "CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        requested_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_resets_token (token_hash),
        KEY idx_resets_employee (employee_id),
        KEY idx_resets_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        $applied[] = 'table ' . $name;
    } catch (\Throwable $e) {
        $errors[] = $name . ': ' . $e->getMessage();
    }
}

$db->exec("UPDATE companies SET plan = 'legacy' WHERE plan IS NULL OR plan = ''");

echo json_encode([
    'ok'      => empty($errors),
    'applied' => $applied,
    'errors'  => $errors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
