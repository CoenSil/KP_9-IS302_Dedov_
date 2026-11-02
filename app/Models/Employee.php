<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Employee
{
    private const BASE_SELECT =
        'SELECT e.id, e.company_id, e.full_name, e.email, e.password_hash,
                e.custom_planned_login, e.custom_planned_logout,
                p.id AS position_id, p.name AS position_name, p.role,
                COALESCE(e.custom_planned_login, p.planned_login) AS planned_login,
                COALESCE(e.custom_planned_logout, p.planned_logout) AS planned_logout,
                d.id AS department_id, d.name AS department_name
         FROM employees e
         JOIN positions p ON p.id = e.position_id
         JOIN departments d ON d.id = p.department_id';

    public static function findByEmail(string $email, int $companyId): ?array
    {
        $stmt = Database::connect()->prepare(
            self::BASE_SELECT . ' WHERE e.email = :email AND e.company_id = :cid LIMIT 1'
        );
        $stmt->execute(['email' => $email, 'cid' => $companyId]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id, ?int $companyId = null): ?array
    {
        $sql = self::BASE_SELECT . ' WHERE e.id = :id';
        $params = ['id' => $id];
        if ($companyId !== null) {
            $sql .= ' AND e.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = Database::connect()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email, int $companyId): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT id FROM employees WHERE email = :email AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['email' => $email, 'cid' => $companyId]);
        return (bool) $stmt->fetch();
    }

    public static function create(int $companyId, string $fullName, int $positionId, string $email, string $plainPassword): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO employees (company_id, full_name, position_id, email, password_hash)
             VALUES (:cid, :full_name, :position_id, :email, :hash)'
        );
        $stmt->execute([
            'cid'         => $companyId,
            'full_name'   => $fullName,
            'position_id' => $positionId,
            'email'       => $email,
            'hash'        => password_hash($plainPassword, PASSWORD_DEFAULT),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function allWithDepartment(int $companyId, ?int $departmentId = null): array
    {
        $sql = self::BASE_SELECT . ' WHERE e.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($departmentId) {
            $sql .= ' AND d.id = :dep';
            $params['dep'] = $departmentId;
        }
        $sql .= ' ORDER BY d.name, e.full_name';

        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        return array_map(function (array $r) {
            unset($r['password_hash']);
            return $r;
        }, $stmt->fetchAll());
    }

    public static function updatePassword(int $id, string $plainPassword): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE employees SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'id'   => $id,
        ]);
    }

    public static function updateSchedule(int $id, int $companyId, ?string $login, ?string $logout): void
    {

        $stmt = Database::connect()->prepare(
            'UPDATE employees
             SET custom_planned_login = :login, custom_planned_logout = :logout
             WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute([
            'login'  => $login !== '' ? $login : null,
            'logout' => $logout !== '' ? $logout : null,
            'id'     => $id,
            'cid'    => $companyId,
        ]);
    }

    public static function listByRole(int $companyId, string $role): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT e.id, e.full_name, e.email, p.name AS position_name, g.id AS group_id, g.name AS group_name
             FROM employees e
             JOIN positions p ON p.id = e.position_id
             LEFT JOIN group_students gs ON gs.student_id = e.id
             LEFT JOIN school_groups g ON g.id = gs.group_id
             WHERE e.company_id = :cid AND p.role = :role
             ORDER BY e.full_name'
        );
        $stmt->execute(['cid' => $companyId, 'role' => $role]);
        return $stmt->fetchAll();
    }
}
