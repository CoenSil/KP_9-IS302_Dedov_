<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;
use App\Core\Settings;

class Attendance
{
    public static function clockIn(int $employeeId, string $createdBy = 'auto'): array
    {
        $db = Database::connect();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $updatedAt = Schema::dateValue('attendance', 'updated_at');

        $stmt = $db->prepare(
            'SELECT * FROM attendance WHERE employee_id = :eid AND work_date = :d LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $today]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['last_logout_at'] === null) {
                return $existing;
            }

            $upd = $db->prepare(
                'UPDATE attendance
                 SET last_login_at = :login_at, last_logout_at = NULL,
                     reentry_allowed = 0, reentry_requested = 0, updated_at = :updated_at
                 WHERE id = :id'
            );
            $upd->execute(['login_at' => $now, 'updated_at' => $updatedAt, 'id' => $existing['id']]);

            $existing['last_login_at'] = $now;
            $existing['last_logout_at'] = null;
            $existing['reentry_allowed'] = 0;
            $existing['reentry_requested'] = 0;
            return $existing;
        }

        $ins = $db->prepare(
            'INSERT INTO attendance (employee_id, work_date, last_login_at, last_logout_at, created_by, updated_at)
             VALUES (:eid, :d, :login_at, NULL, :cb, :updated_at)'
        );
        $ins->execute([
            'eid'        => $employeeId,
            'd'          => $today,
            'login_at'   => $now,
            'cb'         => $createdBy,
            'updated_at' => $updatedAt,
        ]);

        $id = $db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM attendance WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function clockOut(int $employeeId, string $createdBy = 'auto', ?int $atTimestamp = null): void
    {
        $db = Database::connect();
        $today = date('Y-m-d');
        $closeTs = $atTimestamp !== null ? min(time(), $atTimestamp) : time();
        $now = date('Y-m-d H:i:s', $closeTs);

        $stmt = $db->prepare(
            'SELECT last_login_at FROM attendance
             WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $today]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        if ($closeTs < strtotime($row['last_login_at'])) {
            $closeTs = strtotime($row['last_login_at']);
            $now = date('Y-m-d H:i:s', $closeTs);
        }
        $sessionSeconds = max(0, $closeTs - strtotime($row['last_login_at']));

        $upd = $db->prepare(
            'UPDATE attendance
             SET last_logout_at = :logout_at, updated_at = :updated_at, created_by = :cb
             WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL'
        );
        $upd->execute([
            'logout_at'  => $now,
            'updated_at' => Schema::dateValue('attendance', 'updated_at', $closeTs),
            'cb'         => $createdBy,
            'eid'        => $employeeId,
            'd'          => $today,
        ]);

        DailyHours::addSeconds($employeeId, $today, $sessionSeconds);
    }

    public static function touch(int $employeeId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE attendance SET updated_at = :now
             WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL'
        );
        $stmt->execute([
            'now' => Schema::dateValue('attendance', 'updated_at'),
            'eid' => $employeeId,
            'd'   => date('Y-m-d'),
        ]);
    }

    public static function ensureOpen(int $employeeId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT last_logout_at FROM attendance WHERE employee_id = :eid AND work_date = :d LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d')]);
        $row = $stmt->fetch();

        if (!$row || $row['last_logout_at'] !== null) {
            self::clockIn($employeeId, 'auto');
            return;
        }
        self::touch($employeeId);
    }

    public static function existsFor(int $employeeId, string $workDate): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT id FROM attendance WHERE employee_id = :eid AND work_date = :d LIMIT 1');
        $stmt->execute(['eid' => $employeeId, 'd' => $workDate]);
        return (bool) $stmt->fetch();
    }

    public static function isBlockedToday(int $employeeId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT last_logout_at, reentry_allowed FROM attendance WHERE employee_id = :eid AND work_date = :d LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d')]);
        $row = $stmt->fetch();
        return (bool) ($row && $row['last_logout_at'] !== null && !$row['reentry_allowed']);
    }

    public static function markReentryRequested(int $employeeId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE attendance SET reentry_requested = 1, reentry_requested_at = :now
             WHERE employee_id = :eid AND work_date = :d'
        );
        $stmt->execute([
            'now' => date('Y-m-d H:i:s'),
            'eid' => $employeeId,
            'd'   => date('Y-m-d'),
        ]);
    }

    public static function allowReentry(int $employeeId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE attendance SET reentry_allowed = 1, reentry_requested = 0, updated_at = :now
             WHERE employee_id = :eid AND work_date = :d'
        );
        $stmt->execute([
            'now' => Schema::dateValue('attendance', 'updated_at'),
            'eid' => $employeeId,
            'd'   => date('Y-m-d'),
        ]);
    }

    public static function closeStale(int $timeoutSeconds): int
    {
        $db = Database::connect();
        $thresholdTs = time() - $timeoutSeconds;

        $stmt = $db->prepare(
            'SELECT a.employee_id, a.work_date, a.last_login_at, a.updated_at, p.role
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             JOIN positions p ON p.id = e.position_id
             WHERE a.last_logout_at IS NULL'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $today = date('Y-m-d');
        $rows = array_values(array_filter($rows, function (array $r) use ($thresholdTs, $today) {
            if ($r['role'] === 'admin' && $r['work_date'] >= $today) {
                return false;
            }
            $updated = Schema::timestamp($r['updated_at'] ?? null, $r['work_date'])
                ?? strtotime($r['last_login_at']);
            return $updated !== false && $updated < $thresholdTs;
        }));

        $upd = $db->prepare(
            "UPDATE attendance SET last_logout_at = :closed_at, created_by = 'auto'
             WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL"
        );

        $closed = 0;
        foreach ($rows as $row) {
            $closeTs = Schema::timestamp($row['updated_at'] ?? null, $row['work_date'])
                ?? strtotime($row['last_login_at']);

            $ranges = [];
            $onLunch = false;
            foreach (Lunch::rowsFor((int) $row['employee_id'], $row['work_date']) as $l) {
                $ranges[] = Lunch::coverage($l);
                if ($l['ended_at'] === null && Lunch::coverage($l)[1] > time()) {
                    $onLunch = true;
                }
            }
            foreach (BreakTime::rowsFor((int) $row['employee_id'], $row['work_date']) as $b) {
                $ranges[] = BreakTime::coverage($b);
                if ($b['ended_at'] === null && BreakTime::coverage($b)[1] > time()) {
                    $onLunch = true;
                }
            }
            if ($onLunch) {
                continue;
            }
            $closeTs = min(time(), Lunch::extendEnd($ranges, $closeTs));
            $closeTs = max($closeTs, strtotime($row['last_login_at']));

            DailyHours::addSeconds((int) $row['employee_id'], $row['work_date'], $closeTs - strtotime($row['last_login_at']));

            $upd->execute([
                'closed_at' => date('Y-m-d H:i:s', $closeTs),
                'eid'       => $row['employee_id'],
                'd'         => $row['work_date'],
            ]);
            $closed++;
        }

        return $closed;
    }

    public static function todayStatusFor(int $employeeId, ?string $plannedLogin, ?string $plannedLogout = null): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT * FROM attendance WHERE employee_id = :eid AND work_date = :d LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d')]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'status' => 'not_working', 'last_login_at' => null, 'last_logout_at' => null,
                'late_minutes' => 0, 'login_class' => '', 'logout_class' => '',
                'reentry_requested' => false, 'reentry_allowed' => false,
                'worked_seconds' => 0,
                'on_lunch' => false, 'lunch_available' => false, 'lunch_until' => null,
                'lunch_allowance_seconds' => 0, 'lunch_max_seconds' => Settings::lunchMax(),
                'lunch_segments' => [],
                'on_break' => false, 'break_available' => false, 'break_until' => null,
                'break_remaining_seconds' => 0, 'break_max_seconds' => Settings::breakTotal(),
                'break_count' => 0, 'break_count_max' => Settings::breakMaxCount(),
                'break_mode' => 'none', 'break_windows' => [], 'break_segments' => [],
            ];
        }

        $today = date('Y-m-d');
        $lateMinutes = 0;
        $loginClass = '';
        $logoutClass = '';

        if ($plannedLogin && $row['last_login_at']) {
            $plannedTs = strtotime($today . ' ' . $plannedLogin);
            $actualTs  = strtotime($row['last_login_at']);
            if ($actualTs > $plannedTs) {
                $lateMinutes = (int) round(($actualTs - $plannedTs) / 60);
                $loginClass = 'time-late';
            } elseif ($actualTs < $plannedTs) {
                $loginClass = 'time-early-arrival';
            }
        }

        if ($plannedLogout && $row['last_logout_at']) {
            $plannedTs = strtotime($today . ' ' . $plannedLogout);
            $actualTs  = strtotime($row['last_logout_at']);
            if ($actualTs < $plannedTs) {
                $logoutClass = 'time-early-leave';
            } elseif ($actualTs > $plannedTs + 3600) {
                $logoutClass = 'time-overtime';
            }
        }

        $status = $row['last_logout_at'] ? 'finished' : ($lateMinutes > 0 ? 'late' : 'working');

        $worked = DailyHours::totalSecondsFor($employeeId, $today);

        $lunch = ['active' => false, 'until' => null, 'remaining' => 0, 'max' => Settings::lunchMax(), 'segments' => [], 'ranges' => []];
        try {
            $lunch = Lunch::state($employeeId);
        } catch (\Throwable $e) {
            error_log('Lunch state: ' . $e->getMessage());
        }

        $break = [
            'active' => false, 'until' => null, 'remaining' => 0, 'max' => Settings::breakTotal(),
            'count' => 0, 'count_max' => Settings::breakMaxCount(), 'mode' => 'none',
            'windows' => [], 'segments' => [], 'ranges' => [],
        ];
        try {
            $break = BreakTime::state($employeeId);
        } catch (\Throwable $e) {
            error_log('Break state: ' . $e->getMessage());
        }

        if (!$row['last_logout_at'] && $row['last_login_at']) {
            $idle = Settings::backgroundInactivity();
            $lastSeen = Schema::timestamp($row['updated_at'] ?? null, $today)
                ?? strtotime($row['last_login_at']);
            $openEnd = min(time(), $lastSeen + $idle);
            $openEnd = min(time(), Lunch::extendEnd(array_merge($lunch['ranges'], $break['ranges']), $openEnd));
            $worked += max(0, $openEnd - strtotime($row['last_login_at']));
        }

        $segments = [];
        foreach ($lunch['segments'] as [$ls, $le]) {
            $segments[] = ['from' => date('H:i', $ls), 'to' => date('H:i', $le)];
        }
        $breakSegments = [];
        foreach ($break['segments'] as [$bs, $be]) {
            $breakSegments[] = ['from' => date('H:i', $bs), 'to' => date('H:i', $be)];
        }
        $sessionOpen = !$row['last_logout_at'];

        return [
            'status'            => $status,
            'last_login_at'     => $row['last_login_at'],
            'last_logout_at'    => $row['last_logout_at'],
            'late_minutes'      => $lateMinutes,
            'login_class'       => $loginClass,
            'logout_class'      => $logoutClass,
            'reentry_requested' => (bool) $row['reentry_requested'],
            'reentry_allowed'   => (bool) $row['reentry_allowed'],
            'worked_seconds'    => $worked,
            'on_lunch'          => $lunch['active'],
            'lunch_available'   => $sessionOpen && !$break['active'] && ($lunch['active'] || $lunch['remaining'] >= 60),
            'lunch_until'       => $lunch['until'] ? date('H:i', $lunch['until']) : null,
            'lunch_allowance_seconds' => $lunch['remaining'],
            'lunch_max_seconds' => $lunch['max'],
            'lunch_segments'    => $segments,
            'on_break'          => $break['active'],
            'break_available'   => $sessionOpen && !$lunch['active'] && (
                $break['active']
                || ($break['mode'] === 'free'
                    ? ($break['remaining'] >= 30 && $break['count'] < $break['count_max'])
                    : ($break['mode'] === 'scheduled'
                        && (bool) ($break['next'] && $break['next']['status'] === 'available' && $break['next']['remaining'] >= 30)))
            ),
            'break_until'       => $break['until'] ? date('H:i', $break['until']) : null,
            'break_remaining_seconds' => $break['remaining'],
            'break_max_seconds' => $break['max'],
            'break_count'       => $break['count'],
            'break_count_max'   => $break['count_max'],
            'break_mode'        => $break['mode'],
            'break_windows'     => $break['windows'],
            'break_segments'    => $breakSegments,
        ];
    }

    public static function forRange(string $dateFrom, string $dateTo, int $companyId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT a.*, e.full_name,
                    COALESCE(e.custom_planned_login, p.planned_login) AS planned_login,
                    COALESCE(e.custom_planned_logout, p.planned_logout) AS planned_logout,
                    d.name AS department_name
             FROM attendance a
             JOIN employees e ON e.id = a.employee_id
             JOIN positions p ON p.id = e.position_id
             JOIN departments d ON d.id = p.department_id
             WHERE a.work_date BETWEEN :from AND :to AND e.company_id = :cid
             ORDER BY d.name, e.full_name, a.work_date'
        );
        $stmt->execute(['from' => $dateFrom, 'to' => $dateTo, 'cid' => $companyId]);
        return $stmt->fetchAll();
    }
}
