<?php

namespace App\Models;

use App\Core\Database;

class ForceMajeure
{
    public const REASONS = [
        'health'    => 'Плохое самочувствие',
        'family'    => 'Семейные обстоятельства',
        'transport' => 'Проблемы с транспортом',
        'tech'      => 'Техническая неисправность',
        'emergency' => 'Аварийная ситуация',
        'other'     => 'Другое',
    ];

    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'force_majeures'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS force_majeures (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    company_id INT NOT NULL,
                    work_date DATE NOT NULL,
                    reason VARCHAR(32) NOT NULL,
                    comment TEXT NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'open\',
                    created_at DATETIME NOT NULL,
                    acknowledged_by INT NULL,
                    acknowledged_at DATETIME NULL,
                    PRIMARY KEY (id),
                    KEY idx_company_status (company_id, status),
                    KEY idx_employee_date (employee_id, work_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tableChecked = true;
    }

    public static function label(string $reason): string
    {
        return self::REASONS[$reason] ?? $reason;
    }

    public static function submit(int $employeeId, int $companyId, string $reason, string $comment): array
    {
        self::ensureTable();
        if (!isset(self::REASONS[$reason])) {
            return ['ok' => false, 'error' => 'Выберите причину из списка.'];
        }
        $comment = trim($comment);
        $max = defined('FORCE_MAJEURE_COMMENT_MAX') ? (int) FORCE_MAJEURE_COMMENT_MAX : 500;
        if (mb_strlen($comment) < 3) {
            return ['ok' => false, 'error' => 'Напишите короткий комментарий (минимум 3 символа).'];
        }
        if (mb_strlen($comment) > $max) {
            return ['ok' => false, 'error' => 'Комментарий слишком длинный (максимум ' . $max . ' символов).'];
        }

        $now = date('Y-m-d H:i:s');
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO force_majeures
                (employee_id, company_id, work_date, reason, comment, status, created_at)
             VALUES (:eid, :cid, :d, :reason, :comment, \'open\', :now)'
        )->execute([
            'eid'     => $employeeId,
            'cid'     => $companyId,
            'd'       => date('Y-m-d'),
            'reason'  => $reason,
            'comment' => $comment,
            'now'     => $now,
        ]);

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    }

    public static function historyFor(int $employeeId, int $limit = 8): array
    {
        self::ensureTable();
        $limit = max(1, min(50, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT id, work_date, reason, comment, status, created_at, acknowledged_at
             FROM force_majeures
             WHERE employee_id = :eid
             ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute(['eid' => $employeeId]);
        return $stmt->fetchAll();
    }

    public static function openForCompany(int $companyId, int $limit = 20): array
    {
        self::ensureTable();
        $limit = max(1, min(100, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT f.*, e.full_name, p.name AS position_name
             FROM force_majeures f
             JOIN employees e ON e.id = f.employee_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE f.company_id = :cid AND f.status = 'open'
             ORDER BY f.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function historyForCompany(int $companyId, int $limit = 50): array
    {
        self::ensureTable();
        $limit = max(1, min(200, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT f.*, e.full_name, p.name AS position_name
             FROM force_majeures f
             JOIN employees e ON e.id = f.employee_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE f.company_id = :cid
             ORDER BY f.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function openMap(int $companyId): array
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT f.employee_id, f.reason, f.created_at
             FROM force_majeures f
             JOIN (
                 SELECT employee_id, MAX(id) AS max_id
                 FROM force_majeures
                 WHERE company_id = :cid AND status = \'open\'
                 GROUP BY employee_id
             ) last ON last.max_id = f.id'
        );
        $stmt->execute(['cid' => $companyId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['employee_id']] = $row;
        }
        return $map;
    }

    public static function acknowledge(int $id, int $adminId, int $companyId): bool
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'UPDATE force_majeures
             SET status = \'acknowledged\', acknowledged_by = :aid, acknowledged_at = :now
             WHERE id = :id AND company_id = :cid AND status = \'open\''
        );
        $stmt->execute(['aid' => $adminId, 'now' => date('Y-m-d H:i:s'), 'id' => $id, 'cid' => $companyId]);
        return $stmt->rowCount() > 0;
    }
}
