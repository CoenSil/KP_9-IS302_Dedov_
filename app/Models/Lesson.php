<?php

namespace App\Models;

use App\Core\Database;

class Lesson
{
    public const KINDS = ['lecture' => 'Лекция', 'seminar' => 'Семинар'];
    public const START_EARLY_MINUTES = 15;

    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    private static function generateCode(int $companyId): string
    {
        $check = Database::connect()->prepare(
            'SELECT 1 FROM lessons WHERE company_id = :cid AND code = :c AND started_at IS NOT NULL AND closed_at IS NULL AND scheduled_end > :now LIMIT 1'
        );
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $check->execute(['cid' => $companyId, 'c' => $code, 'now' => date('Y-m-d H:i:s')]);
        } while ($check->fetchColumn());
        return $code;
    }

    public static function create(
        int $companyId, int $createdBy, int $teacherId, string $title, string $kind, string $room,
        int $startTs, int $endTs, int $lateAfter, array $groupIds
    ): int {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO lessons (company_id, teacher_id, created_by, title, kind, room, scheduled_start, scheduled_end, late_after_minutes, created_at)
                 VALUES (:cid, :tid, :by, :title, :kind, :room, :start, :end, :late, :now)'
            )->execute([
                'cid' => $companyId, 'tid' => $teacherId, 'by' => $createdBy, 'title' => $title,
                'kind' => $kind, 'room' => $room,
                'start' => date('Y-m-d H:i:s', $startTs), 'end' => date('Y-m-d H:i:s', $endTs),
                'late' => $lateAfter, 'now' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) $db->lastInsertId();
            $link = $db->prepare('INSERT INTO lesson_groups (lesson_id, group_id) VALUES (:lid, :gid)');
            foreach (array_unique($groupIds) as $gid) {
                $link->execute(['lid' => $id, 'gid' => $gid]);
            }
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function conflicts(int $companyId, int $teacherId, string $room, array $groupIds, int $startTs, int $endTs): array
    {
        $in = implode(',', array_fill(0, max(1, count($groupIds)), '?'));
        $stmt = Database::connect()->prepare(
            "SELECT l.id, l.teacher_id, l.room,
                    (SELECT COUNT(*) FROM lesson_groups lg WHERE lg.lesson_id = l.id AND lg.group_id IN ($in)) AS shared_groups
             FROM lessons l
             WHERE l.company_id = ? AND l.scheduled_start < ? AND l.scheduled_end > ?"
        );
        $stmt->execute(array_merge(
            $groupIds ?: [0], [$companyId, date('Y-m-d H:i:s', $endTs), date('Y-m-d H:i:s', $startTs)]
        ));

        $found = [];
        foreach ($stmt->fetchAll() as $r) {
            if ((int) $r['teacher_id'] === $teacherId) {
                $found['teacher'] = 'Преподаватель уже занят в это время.';
            }
            if (mb_strtolower($r['room']) === mb_strtolower($room)) {
                $found['room'] = 'Аудитория ' . $room . ' уже занята в это время.';
            }
            if ((int) $r['shared_groups'] > 0) {
                $found['groups'] = 'У одной из выбранных групп в это время уже есть занятие.';
            }
        }
        return array_values($found);
    }

    private static function baseSelect(): string
    {
        return 'SELECT l.*, t.full_name AS teacher_name,
                       (SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR \', \')
                        FROM lesson_groups lg JOIN school_groups g ON g.id = lg.group_id WHERE lg.lesson_id = l.id) AS group_names,
                       (SELECT COUNT(*) FROM lesson_attendance la WHERE la.lesson_id = l.id) AS present_count,
                       (SELECT COUNT(*) FROM group_students gs JOIN lesson_groups lg ON lg.group_id = gs.group_id
                        WHERE lg.lesson_id = l.id) AS students_count
                FROM lessons l JOIN employees t ON t.id = l.teacher_id';
    }

    public static function find(int $id, int $companyId): ?array
    {
        $stmt = Database::connect()->prepare(self::baseSelect() . ' WHERE l.id = :id AND l.company_id = :cid LIMIT 1');
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        return $stmt->fetch() ?: null;
    }

    public static function findForTeacher(int $id, int $teacherId, int $companyId): ?array
    {
        $lesson = self::find($id, $companyId);
        return ($lesson && (int) $lesson['teacher_id'] === $teacherId) ? $lesson : null;
    }

    public static function findOpenByCode(int $companyId, string $code): ?array
    {
        $stmt = Database::connect()->prepare(
            self::baseSelect() . ' WHERE l.company_id = :cid AND l.code = :code AND l.started_at IS NOT NULL
               AND l.closed_at IS NULL AND l.scheduled_end > :now ORDER BY l.started_at DESC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => self::normalizeCode($code), 'now' => date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }

    public static function groupIds(int $lessonId): array
    {
        $stmt = Database::connect()->prepare('SELECT group_id FROM lesson_groups WHERE lesson_id = :lid');
        $stmt->execute(['lid' => $lessonId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function status(array $lesson, ?int $now = null): string
    {
        $now = $now ?? time();
        if (!empty($lesson['started_at'])) {
            return (empty($lesson['closed_at']) && $now < strtotime($lesson['scheduled_end'])) ? 'open' : 'finished';
        }
        return $now >= strtotime($lesson['scheduled_end']) ? 'missed' : 'scheduled';
    }

    public static function isOpen(array $lesson, ?int $now = null): bool
    {
        return self::status($lesson, $now) === 'open';
    }

    public static function canStart(array $lesson, ?int $now = null): bool
    {
        $now = $now ?? time();
        return self::status($lesson, $now) === 'scheduled'
            && $now >= strtotime($lesson['scheduled_start']) - self::START_EARLY_MINUTES * 60;
    }

    public static function effectiveEnd(array $lesson, ?int $now = null): int
    {
        $now = $now ?? time();
        $end = strtotime($lesson['scheduled_end']);
        if (!empty($lesson['closed_at'])) {
            $end = min($end, strtotime($lesson['closed_at']));
        }
        return min($now, $end);
    }

    public static function lateThreshold(array $lesson): int
    {
        $base = max(strtotime($lesson['scheduled_start']), strtotime($lesson['started_at'] ?? $lesson['scheduled_start']));
        return $base + (int) $lesson['late_after_minutes'] * 60;
    }

    public static function start(int $id, int $companyId): void
    {
        Database::connect()->prepare(
            'UPDATE lessons SET code = :code, started_at = :now
             WHERE id = :id AND company_id = :cid AND started_at IS NULL AND closed_at IS NULL'
        )->execute(['code' => self::generateCode($companyId), 'now' => date('Y-m-d H:i:s'), 'id' => $id, 'cid' => $companyId]);
    }

    public static function close(int $id, int $companyId): void
    {
        Database::connect()->prepare(
            'UPDATE lessons SET closed_at = :now WHERE id = :id AND company_id = :cid AND closed_at IS NULL'
        )->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id, 'cid' => $companyId]);
    }

    public static function deleteIfNotStarted(int $id, int $companyId): bool
    {
        $lesson = self::find($id, $companyId);
        if (!$lesson || !empty($lesson['started_at'])) {
            return false;
        }
        $db = Database::connect();
        $db->prepare('DELETE FROM lesson_groups WHERE lesson_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM lessons WHERE id = :id AND company_id = :cid')->execute(['id' => $id, 'cid' => $companyId]);
        return true;
    }

    public static function listForCompany(int $companyId, string $scope, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $upcoming = $scope === 'upcoming';
        $stmt = Database::connect()->prepare(
            self::baseSelect() . ' WHERE l.company_id = :cid AND l.scheduled_end ' . ($upcoming ? '>=' : '<') . ' :now
             ORDER BY l.scheduled_start ' . ($upcoming ? 'ASC' : 'DESC') . " LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId, 'now' => date('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    public static function listForTeacher(int $teacherId, int $companyId, string $scope, int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        $upcoming = $scope === 'upcoming';
        $stmt = Database::connect()->prepare(
            self::baseSelect() . ' WHERE l.company_id = :cid AND l.teacher_id = :tid AND l.scheduled_end ' . ($upcoming ? '>=' : '<') . ' :now
             ORDER BY l.scheduled_start ' . ($upcoming ? 'ASC' : 'DESC') . " LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId, 'tid' => $teacherId, 'now' => date('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    public static function todayForCompany(int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            self::baseSelect() . ' WHERE l.company_id = :cid AND l.scheduled_start >= :a AND l.scheduled_start < :b ORDER BY l.scheduled_start'
        );
        $stmt->execute(['cid' => $companyId, 'a' => date('Y-m-d 00:00:00'), 'b' => date('Y-m-d 00:00:00', strtotime('+1 day'))]);
        return $stmt->fetchAll();
    }

    public static function openForGroup(int $groupId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT l.id, l.title, l.kind, l.room, l.scheduled_start, l.scheduled_end, t.full_name AS teacher_name
             FROM lessons l
             JOIN lesson_groups lg ON lg.lesson_id = l.id AND lg.group_id = :gid
             JOIN employees t ON t.id = l.teacher_id
             WHERE l.started_at IS NOT NULL AND l.closed_at IS NULL AND l.scheduled_end > :now
             ORDER BY l.scheduled_start'
        );
        $stmt->execute(['gid' => $groupId, 'now' => date('Y-m-d H:i:s')]);
        return $stmt->fetchAll();
    }

    public static function historyForStudent(int $studentId, ?int $groupId, ?string $joinedAt, int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT l.id, l.title, l.kind, l.room, l.scheduled_start, l.scheduled_end, l.closed_at, l.started_at,
                    t.full_name AS teacher_name,
                    la.status, la.checked_in_at, la.active_seconds, la.away_count
             FROM lessons l
             JOIN employees t ON t.id = l.teacher_id
             LEFT JOIN lesson_attendance la ON la.lesson_id = l.id AND la.student_id = :sid
             WHERE (la.id IS NOT NULL
                    OR (l.id IN (SELECT lg.lesson_id FROM lesson_groups lg WHERE lg.group_id = :gid)
                        AND l.started_at IS NOT NULL AND l.scheduled_start >= :joined
                        AND (l.closed_at IS NOT NULL OR l.scheduled_end <= :now)))
             ORDER BY l.scheduled_start DESC LIMIT {$limit}"
        );
        $stmt->execute([
            'sid' => $studentId,
            'gid' => $groupId ?? 0,
            'joined' => $joinedAt ? date('Y-m-d H:i:s', strtotime($joinedAt) - 86400) : '1970-01-01 00:00:00',
            'now' => date('Y-m-d H:i:s'),
        ]);
        return $stmt->fetchAll();
    }
}
