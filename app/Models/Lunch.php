<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

class Lunch
{
    private static bool $tableChecked = false;

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'lunch_breaks'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS lunch_breaks (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    work_date DATE NOT NULL,
                    started_at DATETIME NOT NULL,
                    ended_at DATETIME NULL,
                    max_seconds INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_employee_date (employee_id, work_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tableChecked = true;
    }

    public static function rowsFor(int $employeeId, string $date): array
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT id, started_at, ended_at, max_seconds FROM lunch_breaks
             WHERE employee_id = :eid AND work_date = :d ORDER BY id'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    public static function rangesByEmployee(string $date): array
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'SELECT employee_id, started_at, ended_at, max_seconds FROM lunch_breaks WHERE work_date = :d ORDER BY id'
        );
        $stmt->execute(['d' => $date]);
        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[(int) $r['employee_id']][] = self::coverage($r);
        }
        return $map;
    }

    public static function coverage(array $row): array
    {
        $start = strtotime($row['started_at']);
        $end = $row['ended_at'] !== null ? strtotime($row['ended_at']) : $start + (int) $row['max_seconds'];
        return [$start, $end];
    }

    public static function extendEnd(array $ranges, int $ts, int $slack = 120): int
    {
        foreach ($ranges as [$start, $end]) {
            if ($start <= $ts + $slack && $end > $ts) {
                $ts = $end;
            }
        }
        return $ts;
    }

    public static function state(int $employeeId, ?int $now = null): array
    {
        $now = $now ?? time();
        $max = Settings::lunchMax();
        $used = 0;
        $active = false;
        $until = null;
        $lastEnd = null;
        $segments = [];
        $ranges = [];

        foreach (self::rowsFor($employeeId, date('Y-m-d', $now)) as $r) {
            $start = strtotime($r['started_at']);
            if ($r['ended_at'] === null) {
                $limitEnd = $start + (int) $r['max_seconds'];
                if ($now < $limitEnd) {
                    $active = true;
                    $until = $limitEnd;
                    $used += max(0, $now - $start);
                    $segments[] = [$start, $now];
                    $ranges[] = [$start, $limitEnd];
                    continue;
                }
                $upd = Database::connect()->prepare('UPDATE lunch_breaks SET ended_at = :e WHERE id = :id');
                $upd->execute(['e' => date('Y-m-d H:i:s', $limitEnd), 'id' => $r['id']]);
                $end = $limitEnd;
            } else {
                $end = strtotime($r['ended_at']);
            }
            $used += max(0, $end - $start);
            $segments[] = [$start, $end];
            $ranges[] = [$start, $end];
            $lastEnd = $end;
        }

        return [
            'active'    => $active,
            'until'     => $until,
            'used'      => $used,
            'remaining' => max(0, $max - $used),
            'max'       => $max,
            'last_end'  => $lastEnd,
            'segments'  => $segments,
            'ranges'    => $ranges,
        ];
    }

    public static function start(int $employeeId): array
    {
        $now = time();
        $state = self::state($employeeId, $now);

        if ($state['active']) {
            return ['ok' => false, 'error' => 'Обед уже идёт.'];
        }
        if (BreakTime::state($employeeId, $now)['active']) {
            return ['ok' => false, 'error' => 'Сначала завершите перерыв.'];
        }

        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT 1 FROM attendance WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d', $now)]);
        if (!$stmt->fetch()) {
            return ['ok' => false, 'error' => 'Обед доступен только во время рабочего дня.'];
        }

        if ($state['remaining'] < 60) {
            return ['ok' => false, 'error' => 'Лимит обеда на сегодня исчерпан.'];
        }

        $ins = $db->prepare(
            'INSERT INTO lunch_breaks (employee_id, work_date, started_at, ended_at, max_seconds, created_at)
             VALUES (:eid, :d, :s, NULL, :max, :c)'
        );
        $stamp = date('Y-m-d H:i:s', $now);
        $ins->execute([
            'eid' => $employeeId,
            'd'   => date('Y-m-d', $now),
            's'   => $stamp,
            'max' => $state['remaining'],
            'c'   => $stamp,
        ]);
        return ['ok' => true];
    }

    public static function end(int $employeeId): bool
    {
        self::ensureTable();
        $stmt = Database::connect()->prepare(
            'UPDATE lunch_breaks SET ended_at = :now
             WHERE employee_id = :eid AND work_date = :d AND ended_at IS NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'eid' => $employeeId, 'd' => date('Y-m-d')]);
        return $stmt->rowCount() > 0;
    }
}
