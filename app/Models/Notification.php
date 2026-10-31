<?php

namespace App\Models;

use App\Core\Database;

class Notification
{
    public const KIND_ABSENCE = 'absence_4h';

    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        $db = Database::connect();
        $exists = $db->query("SHOW TABLES LIKE 'notifications'")->fetch();
        if (!$exists) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS notifications (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    kind VARCHAR(32) NOT NULL DEFAULT \'absence_4h\',
                    work_date DATE NOT NULL,
                    absent_seconds INT NOT NULL DEFAULT 0,
                    title VARCHAR(255) NOT NULL,
                    employee_text TEXT NOT NULL,
                    admin_text TEXT NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'open\',
                    employee_read_at DATETIME NULL,
                    admin_read_at DATETIME NULL,
                    resolved_by INT NULL,
                    resolved_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_notification (employee_id, kind, work_date),
                    KEY idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tableChecked = true;
    }

    public static function createAbsence(
        int $employeeId,
        string $workDate,
        int $absentSeconds,
        string $title,
        string $employeeText,
        string $adminText
    ): bool {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'INSERT IGNORE INTO notifications
                (employee_id, kind, work_date, absent_seconds, title, employee_text, admin_text, status, created_at)
             VALUES (:eid, :kind, :d, :sec, :title, :etext, :atext, \'open\', :now)'
        );
        $stmt->execute([
            'eid'   => $employeeId,
            'kind'  => self::KIND_ABSENCE,
            'd'     => $workDate,
            'sec'   => $absentSeconds,
            'title' => $title,
            'etext' => $employeeText,
            'atext' => $adminText,
            'now'   => date('Y-m-d H:i:s'),
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function forEmployee(int $employeeId): array
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT id, title, employee_text AS text, work_date, created_at
             FROM notifications
             WHERE employee_id = :eid AND status = \'open\' AND employee_read_at IS NULL
             ORDER BY id DESC LIMIT 20'
        );
        $stmt->execute(['eid' => $employeeId]);
        return $stmt->fetchAll();
    }

    public static function forAdmin(int $companyId): array
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT n.id, n.title, n.admin_text AS text, n.work_date, n.created_at,
                    n.employee_id, e.full_name
             FROM notifications n
             JOIN employees e ON e.id = n.employee_id
             WHERE n.status = \'open\' AND n.admin_read_at IS NULL AND e.company_id = :cid
             ORDER BY n.id DESC LIMIT 50'
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function markReadByEmployee(int $id, int $employeeId): void
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'UPDATE notifications SET employee_read_at = :now
             WHERE id = :id AND employee_id = :eid AND employee_read_at IS NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id, 'eid' => $employeeId]);
    }

    public static function markReadByAdmin(int $id, int $companyId): void
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'UPDATE notifications SET admin_read_at = :now
             WHERE id = :id AND admin_read_at IS NULL
               AND employee_id IN (SELECT id FROM employees WHERE company_id = :cid)'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id, 'cid' => $companyId]);
    }

    public static function cancel(int $id, int $adminId, int $companyId): void
    {
        self::ensureTable();
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connect()->prepare(
            'UPDATE notifications
             SET status = \'cancelled\', resolved_by = :aid, resolved_at = :now1, admin_read_at = :now2
             WHERE id = :id AND status = \'open\'
               AND employee_id IN (SELECT id FROM employees WHERE company_id = :cid)'
        );
        $stmt->execute(['aid' => $adminId, 'now1' => $now, 'now2' => $now, 'id' => $id, 'cid' => $companyId]);
    }
}
