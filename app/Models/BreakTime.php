<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

class BreakTime
{
    private static bool $tablesChecked = false;

    public const DAYS = [
        1 => 'Пн',
        2 => 'Вт',
        3 => 'Ср',
        4 => 'Чт',
        5 => 'Пт',
        6 => 'Сб',
        7 => 'Вс',
    ];

    public static function ensureTables(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'break_sessions'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS break_sessions (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    work_date DATE NOT NULL,
                    kind VARCHAR(16) NOT NULL DEFAULT \'short\',
                    schedule_id INT NULL,
                    started_at DATETIME NOT NULL,
                    ended_at DATETIME NULL,
                    max_seconds INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_employee_date (employee_id, work_date),
                    KEY idx_schedule (schedule_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        if (!$db->query("SHOW TABLES LIKE 'break_schedules'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS break_schedules (
                    id INT NOT NULL AUTO_INCREMENT,
                    company_id INT NOT NULL,
                    label VARCHAR(64) NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    max_minutes SMALLINT NOT NULL DEFAULT 10,
                    days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_company (company_id, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tablesChecked = true;
    }

    public static function rowsFor(int $employeeId, string $date): array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT id, schedule_id, kind, started_at, ended_at, max_seconds
             FROM break_sessions
             WHERE employee_id = :eid AND work_date = :d ORDER BY id'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    public static function rangesByEmployee(string $date): array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT employee_id, started_at, ended_at, max_seconds
             FROM break_sessions WHERE work_date = :d ORDER BY id'
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

    public static function schedulesForCompany(int $companyId): array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT * FROM break_schedules WHERE company_id = :cid ORDER BY start_time, id'
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function schedulesForDay(int $companyId, int $weekday): array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT * FROM break_schedules
             WHERE company_id = :cid AND is_active = 1 AND (days_mask & :bit) > 0
             ORDER BY start_time, id'
        );
        $stmt->execute(['cid' => $companyId, 'bit' => 1 << max(0, $weekday - 1)]);
        return $stmt->fetchAll();
    }

    public static function hasScheduledBreaks(int $companyId): bool
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT id FROM break_schedules WHERE company_id = :cid AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId]);
        return (bool) $stmt->fetch();
    }

    public static function createSchedule(
        int $companyId,
        string $label,
        string $start,
        string $end,
        int $minutes,
        int $daysMask
    ): void {
        self::ensureTables();
        $now = date('Y-m-d H:i:s');
        Database::connect()->prepare(
            'INSERT INTO break_schedules
                (company_id, label, start_time, end_time, max_minutes, days_mask, is_active, created_at)
             VALUES (:cid, :label, :start, :end, :minutes, :mask, 1, :now)'
        )->execute([
            'cid'     => $companyId,
            'label'   => $label,
            'start'   => $start . ':00',
            'end'     => $end . ':00',
            'minutes' => $minutes,
            'mask'    => $daysMask,
            'now'     => $now,
        ]);
    }

    public static function setScheduleActive(int $id, int $companyId, bool $active): void
    {
        self::ensureTables();
        Database::connect()->prepare(
            'UPDATE break_schedules SET is_active = :a WHERE id = :id AND company_id = :cid'
        )->execute(['a' => $active ? 1 : 0, 'id' => $id, 'cid' => $companyId]);
    }

    public static function deleteSchedule(int $id, int $companyId): void
    {
        self::ensureTables();
        Database::connect()->prepare(
            'DELETE FROM break_schedules WHERE id = :id AND company_id = :cid'
        )->execute(['id' => $id, 'cid' => $companyId]);
    }

    /**
     * Проверяет окно регламентированного перерыва.
     * Возвращает список ошибок (пустой массив — всё хорошо).
     */
    public static function validateSchedule(
        int $companyId,
        string $label,
        string $start,
        string $end,
        int $minutes,
        int $daysMask
    ): array {
        $errors = [];
        $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

        if ($label === '' || mb_strlen($label) > 64) {
            $errors[] = 'Название перерыва: от 1 до 64 символов.';
        }
        if (!preg_match($timePattern, $start) || !preg_match($timePattern, $end)) {
            $errors[] = 'Укажите корректное время начала и окончания (ЧЧ:ММ).';
            return $errors;
        }
        if ($end <= $start) {
            $errors[] = 'Окончание окна должно быть позже начала.';
        } elseif ($minutes > 0) {
            $startMinutes = ((int) substr($start, 0, 2)) * 60 + (int) substr($start, 3, 2);
            $endMinutes   = ((int) substr($end, 0, 2)) * 60 + (int) substr($end, 3, 2);
            if ($minutes > $endMinutes - $startMinutes) {
                $errors[] = 'Длительность перерыва не может быть больше окна.';
            }
        }
        if ($minutes < 1 || $minutes > 30) {
            $errors[] = 'Длительность перерыва: от 1 до 30 минут.';
        }
        if ($daysMask < 1 || $daysMask > 127) {
            $errors[] = 'Выберите хотя бы один день недели.';
            return $errors;
        }

        $existing = self::schedulesForCompany($companyId);
        $limits = [];
        foreach (self::DAYS as $day => $_) {
            $limits[$day] = ['minutes' => 0, 'count' => 0];
        }
        foreach ($existing as $row) {
            if ((int) $row['is_active'] !== 1) {
                continue;
            }
            $mask = (int) $row['days_mask'];
            foreach (self::DAYS as $day => $_) {
                if (($mask & (1 << ($day - 1))) === 0) {
                    continue;
                }
                $limits[$day]['minutes'] += (int) $row['max_minutes'];
                $limits[$day]['count']++;
                $remain = substr((string) $row['start_time'], 0, 5);
                $rend = substr((string) $row['end_time'], 0, 5);
                if (($daysMask & (1 << ($day - 1))) !== 0 && $start < $rend && $end > $remain) {
                    $errors[] = 'Окно пересекается с «' . $row['label'] . '» (' . $remain . '–' . $rend . ').';
                }
            }
        }

        $cap = max(1, (int) floor(Settings::breakTotal() / 60));
        foreach (self::DAYS as $day => $_) {
            if (($daysMask & (1 << ($day - 1))) === 0) {
                continue;
            }
            if ($limits[$day]['count'] >= Settings::breakMaxCount()) {
                $errors[] = 'На ' . self::DAYS[$day] . ' уже ' . $limits[$day]['count'] . ' окна — больше '
                    . Settings::breakMaxCount() . ' в день нельзя.';
            }
            if ($limits[$day]['minutes'] + $minutes > $cap) {
                $errors[] = 'На ' . self::DAYS[$day] . ' суммарно больше ' . $cap . ' минут перерывов.';
            }
        }

        return array_values(array_unique($errors));
    }

    public static function state(int $employeeId, ?int $now = null): array
    {
        $now = $now ?? time();
        $date = date('Y-m-d', $now);
        $weekday = (int) date('N', $now);
        $context = self::contextFor($employeeId);
        $companyId = (int) $context['company_id'];
        $schedules = (!$context['student'] && $companyId)
            ? self::schedulesForDay($companyId, $weekday)
            : [];

        $rows = self::rowsFor($employeeId, $date);
        foreach ($rows as &$row) {
            if ($row['ended_at'] === null) {
                $limitEnd = strtotime($row['started_at']) + (int) $row['max_seconds'];
                if ($now >= $limitEnd) {
                    Database::connect()->prepare('UPDATE break_sessions SET ended_at = :e WHERE id = :id')
                        ->execute(['e' => date('Y-m-d H:i:s', $limitEnd), 'id' => $row['id']]);
                    $row['ended_at'] = date('Y-m-d H:i:s', $limitEnd);
                }
            }
        }
        unset($row);

        $active = null;
        $used = 0;
        $segments = [];
        $ranges = [];
        $lastEnd = null;
        $bySchedule = [];

        foreach ($rows as $row) {
            $start = strtotime($row['started_at']);
            $end = $row['ended_at'] !== null
                ? strtotime($row['ended_at'])
                : min($now, $start + (int) $row['max_seconds']);
            $used += max(0, $end - $start);
            $segments[] = [$start, $end];
            $ranges[] = [$start, $start + (int) $row['max_seconds']];
            $lastEnd = $end;
            if ($row['ended_at'] === null) {
                $active = [
                    'started_at' => $row['started_at'],
                    'until'      => $start + (int) $row['max_seconds'],
                ];
            }
            if (!empty($row['schedule_id'])) {
                $bySchedule[(int) $row['schedule_id']] = $row;
            }
        }

        $windows = [];
        foreach ($schedules as $s) {
            $startTime = substr((string) $s['start_time'], 0, 5);
            $endTime = substr((string) $s['end_time'], 0, 5);
            $startTs = strtotime($date . ' ' . $startTime);
            $endTs = strtotime($date . ' ' . $endTime);
            $maxSeconds = max(60, (int) $s['max_minutes'] * 60);
            $session = $bySchedule[(int) $s['id']] ?? null;
            $status = 'upcoming';
            $remaining = max(0, min($maxSeconds, $endTs - $now));

            if ($session) {
                $sStart = strtotime($session['started_at']);
                $sEnd = $session['ended_at'] !== null ? strtotime($session['ended_at']) : min($now, $sStart + (int) $session['max_seconds']);
                if ($session['ended_at'] === null) {
                    $status = 'active';
                    $remaining = max(0, $sStart + (int) $session['max_seconds'] - $now);
                } else {
                    $status = 'used';
                    $remaining = 0;
                }
            } elseif ($now >= $endTs) {
                $status = 'expired';
                $remaining = 0;
            } elseif ($now >= $startTs) {
                $status = 'available';
            }

            $windows[] = [
                'id'        => (int) $s['id'],
                'label'     => $s['label'],
                'start'     => $startTime,
                'end'       => $endTime,
                'minutes'   => (int) $s['max_minutes'],
                'status'    => $status,
                'remaining' => $remaining,
                'is_active' => (int) $s['is_active'] === 1,
            ];
        }

        // Свободные перерывы положены только студентам учебных заведений.
        // Сотрудникам компаний — только окна, заданные администратором.
        // В учебном заведении сотрудникам (не студентам) перерывы не выдаются.
        if ($context['student']) {
            $mode = 'free';
        } elseif ($context['company_type'] === 'school') {
            $mode = 'none';
        } else {
            $mode = $windows ? 'scheduled' : 'none';
        }
        $count = count($rows);
        $countMax = Settings::breakMaxCount();
        $total = Settings::breakTotal();

        if ($mode === 'scheduled') {
            $remaining = 0;
            $activeRemaining = null;
            foreach ($windows as $w) {
                if ($w['status'] === 'active') {
                    $activeRemaining = $w['remaining'];
                }
                if (!in_array($w['status'], ['used', 'expired'], true)) {
                    $remaining += $w['remaining'];
                }
            }
            // Пока идёт перерыв, таймер должен показывать остаток именно
            // текущего окна, а не сумму всех будущих окон.
            if ($activeRemaining !== null) {
                $remaining = $activeRemaining;
            }
            $total = 0;
            foreach ($windows as $w) {
                $total += $w['minutes'] * 60;
            }
        } elseif ($mode === 'free') {
            $remaining = max(0, $total - $used);
            $countMax = Settings::breakMaxCount();
        } else {
            $remaining = 0;
            $total = 0;
        }

        $next = null;
        foreach ($windows as $w) {
            if (in_array($w['status'], ['available', 'upcoming'], true)) {
                $next = $w;
                break;
            }
        }

        return [
            'active'    => $active !== null,
            'until'     => $active['until'] ?? null,
            'used'      => $used,
            'remaining' => $remaining,
            'max'       => $total,
            'count'     => $count,
            'count_max' => $countMax,
            'mode'      => $mode,
            'student'   => $context['student'],
            'company_type' => $context['company_type'],
            'windows'   => $windows,
            'next'      => $next,
            'last_end'  => $lastEnd,
            'segments'  => $segments,
            'ranges'    => $ranges,
        ];
    }

    public static function start(int $employeeId, ?int $now = null): array
    {
        $now = $now ?? time();
        $context = self::contextFor($employeeId);
        $state = self::state($employeeId, $now);

        if ($state['active']) {
            return ['ok' => false, 'error' => 'Перерыв уже идёт.'];
        }
        if ($state['mode'] === 'none') {
            if ($context['company_type'] === 'school') {
                return ['ok' => false, 'error' => 'Перерывы доступны только студентам учебного заведения.'];
            }
            return ['ok' => false, 'error' => 'Администратор не задал окна перерывов. Обратитесь к администратору.'];
        }
        if (!$context['student'] && Lunch::state($employeeId, $now)['active']) {
            return ['ok' => false, 'error' => 'Сначала завершите обед.'];
        }

        $db = Database::connect();

        if ($context['student']) {
            // Перерыв студента имеет смысл только во время занятия:
            // именно там ведётся учёт присутствия.
            $group = Group::ofStudent($employeeId);
            if (!$group || !Lesson::openForGroup((int) $group['id'])) {
                return ['ok' => false, 'error' => 'Перерыв доступен во время занятия.'];
            }
        } else {
            $stmt = $db->prepare(
                'SELECT 1 FROM attendance WHERE employee_id = :eid AND work_date = :d AND last_logout_at IS NULL LIMIT 1'
            );
            $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d', $now)]);
            if (!$stmt->fetch()) {
                return ['ok' => false, 'error' => 'Перерыв доступен только во время рабочего дня.'];
            }
        }

        $scheduleId = null;
        if ($state['mode'] === 'scheduled') {
            $window = null;
            foreach ($state['windows'] as $w) {
                if ($w['status'] === 'available') {
                    $window = $w;
                    break;
                }
            }
            if ($window === null) {
                $upcoming = $state['next'];
                if ($upcoming && $upcoming['status'] === 'upcoming') {
                    return ['ok' => false, 'error' => 'Перерыв по регламенту будет доступен в ' . $upcoming['start'] . '.'];
                }
                return ['ok' => false, 'error' => 'Время регламентированного перерыва уже сгорело. Следующее окно — по графику.'];
            }
            if ($window['remaining'] < 30) {
                return ['ok' => false, 'error' => 'До конца окна перерыва осталось меньше минуты.'];
            }
            $max = $window['remaining'];
            $scheduleId = $window['id'];
        } else {
            if ($state['count'] >= $state['count_max']) {
                return ['ok' => false, 'error' => 'Лимит перерывов на сегодня исчерпан (' . $state['count_max'] . ' раза).'];
            }
            if ($state['remaining'] < 30) {
                return ['ok' => false, 'error' => 'Лимит времени перерывов на сегодня исчерпан.'];
            }
            $max = $state['remaining'];
        }

        $stamp = date('Y-m-d H:i:s', $now);
        $db->prepare(
            'INSERT INTO break_sessions
                (employee_id, work_date, kind, schedule_id, started_at, ended_at, max_seconds, created_at)
             VALUES (:eid, :d, :kind, :sid, :started, NULL, :max, :created)'
        )->execute([
            'eid'     => $employeeId,
            'd'       => date('Y-m-d', $now),
            'kind'    => $scheduleId ? 'scheduled' : 'short',
            'sid'     => $scheduleId,
            'started' => $stamp,
            'max'     => (int) $max,
            'created' => $stamp,
        ]);

        return ['ok' => true];
    }

    public static function end(int $employeeId, ?int $now = null): bool
    {
        self::ensureTables();
        $now = $now ?? time();
        $stmt = Database::connect()->prepare(
            'UPDATE break_sessions SET ended_at = :now
             WHERE employee_id = :eid AND work_date = :d AND ended_at IS NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s', $now), 'eid' => $employeeId, 'd' => date('Y-m-d', $now)]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Активные перерывы всех сотрудников на дату: employee_id => до какого времени.
     * Нужно для журнала занятия, чтобы показывать состояние «перерыв».
     */
    public static function activeMap(?int $now = null): array
    {
        $now = $now ?? time();
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT employee_id, started_at, max_seconds FROM break_sessions
             WHERE work_date = :d AND ended_at IS NULL'
        );
        $stmt->execute(['d' => date('Y-m-d', $now)]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $until = strtotime($row['started_at']) + (int) $row['max_seconds'];
            if ($until > $now) {
                $map[(int) $row['employee_id']] = $until;
            }
        }
        return $map;
    }

    public static function activeUntilFor(int $employeeId, ?int $now = null): ?int
    {
        $now = $now ?? time();
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT started_at, max_seconds FROM break_sessions
             WHERE employee_id = :eid AND work_date = :d AND ended_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => date('Y-m-d', $now)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $until = strtotime($row['started_at']) + (int) $row['max_seconds'];
        return $until > $now ? $until : null;
    }

    private static function contextFor(int $employeeId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT p.role, e.company_id, c.type AS company_type
             FROM employees e
             JOIN positions p ON p.id = e.position_id
             LEFT JOIN companies c ON c.id = e.company_id
             WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $employeeId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['company_id' => 0, 'company_type' => 'company', 'role' => '', 'student' => false];
        }
        $type = ($row['company_type'] ?? 'company') === 'school' ? 'school' : 'company';
        $role = (string) ($row['role'] ?? '');
        return [
            'company_id'   => (int) $row['company_id'],
            'company_type' => $type,
            'role'         => $role,
            'student'      => $type === 'school' && $role === 'student',
        ];
    }
}
