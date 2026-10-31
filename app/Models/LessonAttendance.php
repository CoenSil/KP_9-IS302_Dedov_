<?php

namespace App\Models;

use App\Core\Database;

class LessonAttendance
{
    public const HEARTBEAT_GAP = 45;
    public const IDLE_AFTER = 180;

    public static function find(int $lessonId, int $studentId): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM lesson_attendance WHERE lesson_id = :lid AND student_id = :sid LIMIT 1'
        );
        $stmt->execute(['lid' => $lessonId, 'sid' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function checkIn(array $lesson, int $studentId, int $groupId, string $deviceId, string $userAgent): array
    {
        $now = time();
        $late = $now > Lesson::lateThreshold($lesson);
        $stamp = date('Y-m-d H:i:s', $now);

        Database::connect()->prepare(
            'INSERT INTO lesson_attendance
                (lesson_id, student_id, group_id, status, checked_in_at, last_seen_at, last_visible, last_interaction_at, device_id, user_agent)
             VALUES (:lid, :sid, :gid, :status, :now, :now2, 1, :now3, :device, :ua)
             ON DUPLICATE KEY UPDATE
                status = IF(status = \'manual\', \'present\', status),
                group_id = VALUES(group_id),
                last_seen_at = VALUES(last_seen_at), last_visible = 1,
                device_id = VALUES(device_id), user_agent = VALUES(user_agent)'
        )->execute([
            'lid'    => $lesson['id'],
            'sid'    => $studentId,
            'gid'    => $groupId,
            'status' => $late ? 'late' : 'present',
            'now'    => $stamp,
            'now2'   => $stamp,
            'now3'   => $stamp,
            'device' => $deviceId !== '' ? substr($deviceId, 0, 64) : null,
            'ua'     => $userAgent !== '' ? substr($userAgent, 0, 255) : null,
        ]);

        return self::find((int) $lesson['id'], $studentId);
    }

    public static function heartbeat(array $lesson, int $studentId, bool $visible, bool $interacted, bool $onBreak = false): ?array
    {
        $row = self::find((int) $lesson['id'], $studentId);
        if (!$row) {
            return null;
        }

        $now = time();
        $lessonNow = Lesson::effectiveEnd($lesson, $now);
        $prev = $row['last_seen_at'] ? strtotime($row['last_seen_at']) : strtotime($row['checked_in_at']);
        $prev = min($prev, $lessonNow);
        $gap = max(0, $lessonNow - $prev);

        $active = (int) $row['active_seconds'];
        $hidden = (int) $row['hidden_seconds'];
        $away   = (int) $row['away_count'];
        $wasVisible = (int) $row['last_visible'] === 1;

        if ($onBreak) {
            // Перерыв студента засчитывается как присутствие: время идёт
            // в активные секунды, выходом из занятия не считается.
            $active += min($gap, self::HEARTBEAT_GAP);
        } elseif (!$wasVisible) {
            $hidden += $gap;
        } elseif ($gap <= self::HEARTBEAT_GAP) {
            $active += $gap;
            if (!$visible) {
                $away++;
            }
        } else {
            $away++;
        }

        $stamp = date('Y-m-d H:i:s', $now);
        Database::connect()->prepare(
            'UPDATE lesson_attendance
             SET last_seen_at = :seen, last_visible = :vis,
                 last_interaction_at = IF(:inter = 1, :seen2, last_interaction_at),
                 active_seconds = :active, hidden_seconds = :hidden, away_count = :away
             WHERE id = :id'
        )->execute([
            'seen'   => $stamp,
            'vis'    => $visible ? 1 : 0,
            'inter'  => $interacted ? 1 : 0,
            'seen2'  => $stamp,
            'active' => $active,
            'hidden' => $hidden,
            'away'   => $away,
            'id'     => $row['id'],
        ]);

        return self::find((int) $lesson['id'], $studentId);
    }

    public static function markManual(int $lessonId, int $studentId, ?int $groupId): void
    {
        Database::connect()->prepare(
            'INSERT INTO lesson_attendance (lesson_id, student_id, group_id, status, checked_in_at)
             VALUES (:lid, :sid, :gid, \'manual\', :now)
             ON DUPLICATE KEY UPDATE status = status'
        )->execute(['lid' => $lessonId, 'sid' => $studentId, 'gid' => $groupId, 'now' => date('Y-m-d H:i:s')]);
    }

    public static function remove(int $lessonId, int $studentId): void
    {
        Database::connect()->prepare(
            'DELETE FROM lesson_attendance WHERE lesson_id = :lid AND student_id = :sid'
        )->execute(['lid' => $lessonId, 'sid' => $studentId]);
    }

    public static function liveState(array $row, array $lesson, int $now, bool $onBreak = false): string
    {
        if ($row['status'] === 'manual' && empty($row['last_seen_at'])) {
            return 'manual';
        }
        if (!Lesson::isOpen($lesson, $now)) {
            return 'finished';
        }
        if ($onBreak) {
            return 'break';
        }
        if ((int) $row['last_visible'] !== 1) {
            return 'hidden';
        }
        $seen = strtotime($row['last_seen_at'] ?? $row['checked_in_at']);
        if ($now - $seen > self::HEARTBEAT_GAP) {
            return 'offline';
        }
        $touch = $row['last_interaction_at'] ? strtotime($row['last_interaction_at']) : strtotime($row['checked_in_at']);
        return ($now - $touch > self::IDLE_AFTER) ? 'idle' : 'online';
    }

    public static function activePercent(array $row, array $lesson, int $now): ?int
    {
        if ($row['status'] === 'manual' && empty($row['last_seen_at'])) {
            return null;
        }
        $span = Lesson::effectiveEnd($lesson, $now) - strtotime($row['checked_in_at']);
        if ($span <= 0) {
            return 100;
        }
        return (int) min(100, round((int) $row['active_seconds'] * 100 / $span));
    }

    public static function roster(array $lesson, int $now): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT e.id AS student_id, e.full_name, g.name AS group_name,
                    la.status, la.checked_in_at, la.last_seen_at, la.last_visible,
                    la.last_interaction_at, la.active_seconds, la.hidden_seconds, la.away_count, la.device_id,
                    la.user_agent, la.id AS la_id
             FROM lesson_groups lg
             JOIN group_students gs ON gs.group_id = lg.group_id
             JOIN school_groups g ON g.id = lg.group_id
             JOIN employees e ON e.id = gs.student_id
             LEFT JOIN lesson_attendance la ON la.lesson_id = lg.lesson_id AND la.student_id = gs.student_id
             WHERE lg.lesson_id = :lid
             ORDER BY g.name, e.full_name'
        );
        $stmt->execute(['lid' => $lesson['id']]);
        $rows = $stmt->fetchAll();

        $deviceCount = [];
        foreach ($rows as $r) {
            if (!empty($r['device_id'])) {
                $deviceCount[$r['device_id']] = ($deviceCount[$r['device_id']] ?? 0) + 1;
            }
        }

        $breaks = [];
        try {
            $breaks = BreakTime::activeMap($now);
        } catch (\Throwable $e) {
            error_log('Break map: ' . $e->getMessage());
        }

        $out = [];
        foreach ($rows as $r) {
            $base = ['student_id' => (int) $r['student_id'], 'full_name' => $r['full_name'], 'group_name' => $r['group_name']];
            if ($r['la_id'] === null) {
                $out[] = $base + [
                    'status' => 'absent', 'state' => 'absent', 'checked_in_at' => null,
                    'active_percent' => null, 'active_seconds' => 0, 'away_count' => 0,
                    'shared_device' => false, 'mobile' => false, 'on_break' => false,
                ];
                continue;
            }
            $onBreak = isset($breaks[(int) $r['student_id']]);
            $out[] = $base + [
                'status'         => $r['status'],
                'state'          => self::liveState($r, $lesson, $now, $onBreak),
                'checked_in_at'  => $r['checked_in_at'],
                'active_percent' => self::activePercent($r, $lesson, $now),
                'active_seconds' => (int) $r['active_seconds'],
                'away_count'     => (int) $r['away_count'],
                'shared_device'  => !empty($r['device_id']) && $deviceCount[$r['device_id']] > 1,
                'mobile'         => (bool) preg_match('/Android|iPhone|iPad|Mobile/i', (string) $r['user_agent']),
                'on_break'       => $onBreak,
            ];
        }
        return $out;
    }
}
