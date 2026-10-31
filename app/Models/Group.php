<?php

namespace App\Models;

use App\Core\Database;

class Group
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function formatCode(string $code): string
    {
        return strlen($code) === 8 ? substr($code, 0, 4) . '-' . substr($code, 4) : $code;
    }

    private static function generateCode(): string
    {
        $db = Database::connect();
        $check = $db->prepare('SELECT 1 FROM school_groups WHERE invite_code = :c LIMIT 1');
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $check->execute(['c' => $code]);
        } while ($check->fetchColumn());
        return $code;
    }

    public static function create(int $companyId, string $name): int
    {
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO school_groups (company_id, name, invite_code, created_at) VALUES (:cid, :name, :code, :now)'
        )->execute(['cid' => $companyId, 'name' => $name, 'code' => self::generateCode(), 'now' => date('Y-m-d H:i:s')]);
        return (int) $db->lastInsertId();
    }

    public static function nameExists(int $companyId, string $name): bool
    {
        $stmt = Database::connect()->prepare('SELECT 1 FROM school_groups WHERE company_id = :cid AND name = :n LIMIT 1');
        $stmt->execute(['cid' => $companyId, 'n' => $name]);
        return (bool) $stmt->fetchColumn();
    }

    public static function rotateCode(int $id, int $companyId): void
    {
        Database::connect()->prepare(
            'UPDATE school_groups SET invite_code = :code WHERE id = :id AND company_id = :cid'
        )->execute(['code' => self::generateCode(), 'id' => $id, 'cid' => $companyId]);
    }

    public static function find(int $id, int $companyId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM school_groups WHERE id = :id AND company_id = :cid LIMIT 1');
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        return $stmt->fetch() ?: null;
    }

    public static function findByInvite(string $code): ?array
    {
        $code = self::normalizeCode($code);
        if (strlen($code) !== 8) {
            return null;
        }
        $stmt = Database::connect()->prepare(
            'SELECT g.*, c.name AS company_name, c.code AS company_code, c.is_active, c.type AS company_type
             FROM school_groups g
             JOIN companies c ON c.id = g.company_id
             WHERE g.invite_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['is_active'] !== 1 || $row['company_type'] !== Company::TYPE_SCHOOL) {
            return null;
        }
        return $row;
    }

    public static function forCompany(int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM group_students gs WHERE gs.group_id = g.id) AS students_count,
                    (SELECT GROUP_CONCAT(t.full_name ORDER BY t.full_name SEPARATOR \', \')
                     FROM teacher_groups tg JOIN employees t ON t.id = tg.teacher_id WHERE tg.group_id = g.id) AS teachers
             FROM school_groups g
             WHERE g.company_id = :cid
             ORDER BY g.name'
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function students(int $groupId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT e.id, e.full_name, e.email
             FROM group_students gs JOIN employees e ON e.id = gs.student_id
             WHERE gs.group_id = :gid ORDER BY e.full_name'
        );
        $stmt->execute(['gid' => $groupId]);
        return $stmt->fetchAll();
    }

    public static function ofStudent(int $studentId): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT g.*, gs.joined_at
             FROM group_students gs JOIN school_groups g ON g.id = gs.group_id
             WHERE gs.student_id = :sid LIMIT 1'
        );
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function assignStudent(int $groupId, int $studentId): void
    {
        Database::connect()->prepare(
            'INSERT INTO group_students (group_id, student_id, joined_at) VALUES (:gid, :sid, :now)
             ON DUPLICATE KEY UPDATE group_id = VALUES(group_id), joined_at = VALUES(joined_at)'
        )->execute(['gid' => $groupId, 'sid' => $studentId, 'now' => date('Y-m-d H:i:s')]);
    }

    public static function moveStudent(int $studentId, int $groupId, int $companyId): bool
    {
        $db = Database::connect();
        $check = $db->prepare(
            'SELECT 1 FROM employees e JOIN positions p ON p.id = e.position_id
             WHERE e.id = :sid AND e.company_id = :cid AND p.role = \'student\' LIMIT 1'
        );
        $check->execute(['sid' => $studentId, 'cid' => $companyId]);
        if (!$check->fetchColumn() || !self::find($groupId, $companyId)) {
            return false;
        }
        self::assignStudent($groupId, $studentId);
        return true;
    }

    public static function removeStudent(int $studentId, int $companyId): void
    {
        Database::connect()->prepare(
            'DELETE gs FROM group_students gs
             JOIN school_groups g ON g.id = gs.group_id
             WHERE gs.student_id = :sid AND g.company_id = :cid'
        )->execute(['sid' => $studentId, 'cid' => $companyId]);
    }

    public static function teacherIds(int $groupId): array
    {
        $stmt = Database::connect()->prepare('SELECT teacher_id FROM teacher_groups WHERE group_id = :gid');
        $stmt->execute(['gid' => $groupId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function setTeachers(int $groupId, int $companyId, array $teacherIds): void
    {
        $db = Database::connect();
        $valid = [];
        $teacherIds = array_values(array_unique(array_map('intval', $teacherIds)));
        if ($teacherIds) {
            $in = implode(',', array_fill(0, count($teacherIds), '?'));
            $stmt = $db->prepare(
                "SELECT e.id FROM employees e JOIN positions p ON p.id = e.position_id
                 WHERE e.company_id = ? AND p.role = 'teacher' AND e.id IN ($in)"
            );
            $stmt->execute(array_merge([$companyId], $teacherIds));
            $valid = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        }

        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM teacher_groups WHERE group_id = :gid')->execute(['gid' => $groupId]);
            $insert = $db->prepare('INSERT INTO teacher_groups (teacher_id, group_id) VALUES (:tid, :gid)');
            foreach ($valid as $tid) {
                $insert->execute(['tid' => $tid, 'gid' => $groupId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function forTeacher(int $teacherId, int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT g.id, g.name,
                    (SELECT COUNT(*) FROM group_students gs WHERE gs.group_id = g.id) AS students_count
             FROM teacher_groups tg JOIN school_groups g ON g.id = tg.group_id
             WHERE tg.teacher_id = :tid AND g.company_id = :cid
             ORDER BY g.name'
        );
        $stmt->execute(['tid' => $teacherId, 'cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function assignedToTeacher(int $teacherId, int $companyId): array
    {
        return array_map('intval', array_column(self::forTeacher($teacherId, $companyId), 'id'));
    }

    public static function teacherMap(int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT tg.teacher_id, tg.group_id
             FROM teacher_groups tg JOIN school_groups g ON g.id = tg.group_id
             WHERE g.company_id = :cid'
        );
        $stmt->execute(['cid' => $companyId]);
        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[(int) $r['teacher_id']][] = (int) $r['group_id'];
        }
        return $map;
    }
}
