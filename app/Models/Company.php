<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

class Company
{
    public const TYPE_COMPANY = 'company';
    public const TYPE_SCHOOL  = 'school';

    public static function normalizeType(?string $type): string
    {
        return $type === self::TYPE_SCHOOL ? self::TYPE_SCHOOL : self::TYPE_COMPANY;
    }

    public static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    public static function isValidCode(string $code): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $code);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM companies WHERE code = :c LIMIT 1');
        $stmt->execute(['c' => self::normalizeCode($code)]);
        return $stmt->fetch() ?: null;
    }

    public static function codeExists(string $code): bool
    {
        return self::findByCode($code) !== null;
    }

    public static function isActive(int $id): bool
    {
        $stmt = Database::connect()->prepare('SELECT is_active FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row && (int) $row['is_active'] === 1;
    }

    public static function allWithStats(): array
    {
        return Database::connect()->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM employees e WHERE e.company_id = c.id) AS employees_count,
                    (SELECT COUNT(*) FROM employees e JOIN positions p ON p.id = e.position_id
                      WHERE e.company_id = c.id AND p.role = 'admin') AS admins_count
             FROM companies c
             ORDER BY c.name"
        )->fetchAll();
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connect()->prepare('UPDATE companies SET is_active = :a WHERE id = :id');
        $stmt->execute(['a' => $active ? 1 : 0, 'id' => $id]);
    }

    public static function setSubscription(
        int $id,
        string $plan,
        ?string $paidUntil,
        ?string $trialEnds,
        int $maxEmployees,
        int $maxStudents
    ): void {
        $stmt = Database::connect()->prepare(
            'UPDATE companies
             SET plan = :plan, paid_until = :paid, trial_ends_at = :trial,
                 max_employees = :me, max_students = :ms
             WHERE id = :id'
        );
        $stmt->execute([
            'plan'  => $plan,
            'paid'  => $paidUntil,
            'trial' => $trialEnds,
            'me'    => $maxEmployees,
            'ms'    => $maxStudents,
            'id'    => $id,
        ]);
    }

    public static function countEmployees(int $id): int
    {
        $stmt = Database::connect()->prepare('SELECT COUNT(*) FROM employees WHERE company_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public static function createWithAdmin(
        string $name,
        string $code,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        string $type = self::TYPE_COMPANY
    ): int {
        $type = self::normalizeType($type);
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $departmentCreatedAt = Schema::dateValue('departments', 'created_at');
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO companies (name, code, type, is_active, created_at) VALUES (:n, :c, :t, 1, :now)')
               ->execute(['n' => $name, 'c' => $code, 't' => $type, 'now' => $now]);
            $companyId = (int) $db->lastInsertId();

            if (Schema::columnType('companies', 'plan') !== null) {
                $db->prepare(
                    'UPDATE companies
                     SET plan = :plan, trial_ends_at = :trial, max_employees = 50, max_students = 500
                     WHERE id = :id'
                )->execute([
                    'plan'  => 'trial',
                    'trial' => date('Y-m-d H:i:s', time() + Subscription::TRIAL_DAYS * 86400),
                    'id'    => $companyId,
                ]);
            }

            $db->prepare('INSERT INTO departments (company_id, name, code, created_at) VALUES (:cid, :n, :c, :now)')
               ->execute(['cid' => $companyId, 'n' => 'Администрация', 'c' => 'ADM', 'now' => $departmentCreatedAt]);
            $departmentId = (int) $db->lastInsertId();

            $positionStmt = $db->prepare(
                'INSERT INTO positions (company_id, name, department_id, role, planned_login, planned_logout)
                 VALUES (:cid, :pn, :dep, :role, :planned_login, :planned_logout)'
            );
            $addPosition = function (string $name, int $dep, string $role) use ($positionStmt, $companyId, $db): int {
                $positionStmt->execute([
                    'cid'             => $companyId,
                    'pn'              => $name,
                    'dep'             => $dep,
                    'role'            => $role,
                    'planned_login'   => '09:00:00',
                    'planned_logout'  => '18:00:00',
                ]);
                return (int) $db->lastInsertId();
            };

            if ($type === self::TYPE_SCHOOL) {
                $positionId = $addPosition('Директор', $departmentId, 'admin');
                $addPosition('Администратор', $departmentId, 'admin');
                $addPosition('Преподаватель', $departmentId, 'teacher');

                $db->prepare('INSERT INTO departments (company_id, name, code, created_at) VALUES (:cid, :n, :c, :now)')
                   ->execute(['cid' => $companyId, 'n' => 'Студенты', 'c' => 'STU', 'now' => $departmentCreatedAt]);
                $addPosition('Студент', (int) $db->lastInsertId(), 'student');
            } else {
                $positionId = $addPosition('Администратор', $departmentId, 'admin');
            }

            $db->prepare(
                'INSERT INTO employees (company_id, full_name, position_id, email, password_hash)
                 VALUES (:cid, :fn, :pos, :em, :h)'
            )->execute([
                'cid' => $companyId,
                'fn'  => $adminName,
                'pos' => $positionId,
                'em'  => $adminEmail,
                'h'   => password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            $db->commit();
            return $companyId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
