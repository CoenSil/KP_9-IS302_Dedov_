<?php

namespace App\Models;

use App\Core\Database;

class DailyHours
{
    public static function addSeconds(int $employeeId, string $workDate, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $db = Database::connect();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare(
            'INSERT INTO daily_hours (employee_id, work_date, total_seconds, updated_at)
             VALUES (:eid, :d, :sec_new, :now_new)
             ON DUPLICATE KEY UPDATE
                total_seconds = total_seconds + :sec_add,
                updated_at = :now_upd'
        );
        $stmt->execute([
            'eid'     => $employeeId,
            'd'       => $workDate,
            'sec_new' => $seconds,
            'now_new' => $now,
            'sec_add' => $seconds,
            'now_upd' => $now,
        ]);
    }

    public static function setSeconds(int $employeeId, string $workDate, int $seconds): void
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare(
            'INSERT INTO daily_hours (employee_id, work_date, total_seconds, updated_at)
             VALUES (:eid, :d, :sec_new, :now_new)
             ON DUPLICATE KEY UPDATE
                total_seconds = :sec_upd,
                updated_at = :now_upd'
        );
        $stmt->execute([
            'eid'     => $employeeId,
            'd'       => $workDate,
            'sec_new' => $seconds,
            'now_new' => $now,
            'sec_upd' => $seconds,
            'now_upd' => $now,
        ]);
    }

    public static function totalSecondsFor(int $employeeId, string $workDate): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT total_seconds FROM daily_hours WHERE employee_id = :eid AND work_date = :d LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $workDate]);
        $row = $stmt->fetch();
        return $row ? (int) $row['total_seconds'] : 0;
    }

    public static function forRange(string $dateFrom, string $dateTo): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT employee_id, work_date, total_seconds
             FROM daily_hours
             WHERE work_date BETWEEN :from AND :to'
        );
        $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['employee_id'] . '|' . $row['work_date']] = (int) $row['total_seconds'];
        }
        return $out;
    }
}
