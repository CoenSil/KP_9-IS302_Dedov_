<?php

namespace App\Models;

use App\Core\Database;

/**
 * Небольшие настройки организации, которые задаёт администратор.
 * Отдельная таблица, чтобы не менять схему companies на рабочей базе.
 */
class CompanySetting
{
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'company_settings'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS company_settings (
                    company_id INT NOT NULL,
                    workplace_move_seconds INT NOT NULL DEFAULT 300,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (company_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tableChecked = true;
    }

    public static function workplaceMoveSeconds(int $companyId): ?int
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT workplace_move_seconds FROM company_settings WHERE company_id = :cid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return max(60, (int) $row['workplace_move_seconds']);
    }

    public static function setWorkplaceMoveSeconds(int $companyId, int $seconds): void
    {
        self::ensureTable();
        Database::connect()->prepare(
            'INSERT INTO company_settings (company_id, workplace_move_seconds, updated_at)
             VALUES (:cid, :sec, :now)
             ON DUPLICATE KEY UPDATE
                workplace_move_seconds = VALUES(workplace_move_seconds),
                updated_at = VALUES(updated_at)'
        )->execute([
            'cid' => $companyId,
            'sec' => max(60, min(3600, $seconds)),
            'now' => date('Y-m-d H:i:s'),
        ]);
    }
}
