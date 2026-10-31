<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Schema;
use App\Core\Settings;
use App\Models\Lunch;
use App\Models\BreakTime;
use App\Models\Notification;

class AbsenceMonitor
{
    private const DEFAULT_THRESHOLD = 4 * 3600;

    public static function threshold(): int
    {
        return defined('ABSENCE_THRESHOLD') ? (int) ABSENCE_THRESHOLD : self::DEFAULT_THRESHOLD;
    }

    public static function absentSeconds(array $row, int $now, string $today, int $idleTimeout): ?int
    {
        if (empty($row['planned_login']) || empty($row['planned_logout'])) {
            return null;
        }
        $start = strtotime($today . ' ' . $row['planned_login']);
        $end   = strtotime($today . ' ' . $row['planned_logout']);
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $windowEnd = min($now, $end);
        if ($windowEnd <= $start) {
            return null;
        }
        $windowLen = $windowEnd - $start;

        $worked = max(0, (int) ($row['closed_seconds'] ?? 0));

        if (!empty($row['last_login_at']) && empty($row['last_logout_at'])) {
            $from = max(strtotime($row['last_login_at']), $start);
            $to   = $windowEnd;
            if (!empty($row['updated_at'])) {
                $lastSeen = Schema::timestamp($row['updated_at'], $today);
                if ($lastSeen !== null) {
                    $to = min($to, $lastSeen + $idleTimeout);
                }
            }
            if (!empty($row['lunch_ranges'])) {
                $to = min($windowEnd, Lunch::extendEnd($row['lunch_ranges'], $to));
            }
            if ($to > $from) {
                $worked += $to - $from;
            }
        }

        $worked = min($worked, $windowLen);
        return $windowLen - $worked;
    }

    public static function check(?int $now = null): int
    {
        $now = $now ?? time();
        if ((int) date('N', $now) > 5) {
            return 0;
        }
        $today = date('Y-m-d', $now);
        $threshold = self::threshold();
        $idle = Settings::backgroundInactivity();
        $lunches = Lunch::rangesByEmployee($today);
        $breaks = BreakTime::rangesByEmployee($today);

        $stmt = Database::connect()->prepare(
            "SELECT e.id, e.full_name,
                    COALESCE(e.custom_planned_login, p.planned_login)   AS planned_login,
                    COALESCE(e.custom_planned_logout, p.planned_logout) AS planned_logout,
                    a.last_login_at, a.last_logout_at, a.updated_at,
                    COALESCE(h.total_seconds, 0) AS closed_seconds
             FROM employees e
             JOIN companies c ON c.id = e.company_id AND c.is_active = 1
             JOIN positions p ON p.id = e.position_id
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = :d1
             LEFT JOIN daily_hours h ON h.employee_id = e.id AND h.work_date = :d2
             WHERE p.role = 'employee'"
        );
        $stmt->execute(['d1' => $today, 'd2' => $today]);

        $created = 0;
        foreach ($stmt->fetchAll() as $row) {
            $row['lunch_ranges'] = array_merge(
                $lunches[(int) $row['id']] ?? [],
                $breaks[(int) $row['id']] ?? []
            );
            $absent = self::absentSeconds($row, $now, $today, $idle);
            if ($absent === null || $absent < $threshold) {
                continue;
            }
            [$title, $employeeText, $adminText] = self::texts($row, $today, $absent, $threshold);
            if (Notification::createAbsence((int) $row['id'], $today, $absent, $title, $employeeText, $adminText)) {
                $created++;
            }
        }
        return $created;
    }

    public static function checkThrottled(int $minInterval = 60): void
    {
        try {
            $stamp = dirname(__DIR__, 2) . '/storage/.absence_check';
            $last = @filemtime($stamp);
            if ($last !== false && time() - $last < $minInterval) {
                return;
            }
            @touch($stamp);
            self::check();
        } catch (\Throwable $e) {
            error_log('AbsenceMonitor: ' . $e->getMessage());
        }
    }

    private static function texts(array $row, string $today, int $absent, int $threshold): array
    {
        $date  = date('d.m.Y', strtotime($today));
        $plan  = substr($row['planned_login'], 0, 5) . '–' . substr($row['planned_logout'], 0, 5);
        $hours = self::fmt($absent);
        $limit = rtrim(rtrim(number_format($threshold / 3600, 1, '.', ''), '0'), '.');

        $title = 'Прогул: отсутствие более ' . $limit . ' ч';

        $employeeText = "Зафиксировано ваше отсутствие на рабочем месте {$date}: {$hours} при графике {$plan} "
            . "(порог — {$limit} ч). Отсутствие без уважительной причины более 4 часов в течение рабочего дня "
            . "является прогулом и может повлечь увольнение (пп. «а» п. 6 ч. 1 ст. 81 ТК РФ). "
            . "Если у вас была уважительная причина — сообщите администратору и дайте письменное объяснение.";

        $adminText = "Сотрудник {$row['full_name']} отсутствовал на рабочем месте {$date}: {$hours} при графике {$plan} "
            . "(порог — {$limit} ч). Система оформила уведомление о возможном увольнении за прогул. "
            . "Перед решением выясните причину и запросите письменное объяснение (ст. 193 ТК РФ); "
            . "если причина уважительная — отмените уведомление.";

        return [$title, $employeeText, $adminText];
    }

    private static function fmt(int $seconds): string
    {
        return intdiv($seconds, 3600) . ' ч ' . str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT) . ' мин';
    }
}
