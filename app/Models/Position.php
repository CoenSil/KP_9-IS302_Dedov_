<?php

namespace App\Models;

use App\Core\Database;

class Position
{
    public static function allEmployeePositions(int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT p.id, p.name, p.planned_login, p.planned_logout,
                    d.name AS department_name
             FROM positions p
             JOIN departments d ON d.id = p.department_id
             WHERE p.role = 'employee' AND p.company_id = :cid AND d.company_id = :cid2
             ORDER BY d.name, p.name"
        );
        $stmt->execute(['cid' => $companyId, 'cid2' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function idFor(int $companyId, string $role, ?string $name = null): ?int
    {
        $sql = 'SELECT id FROM positions WHERE company_id = :cid AND role = :role';
        $params = ['cid' => $companyId, 'role' => $role];
        if ($name !== null) {
            $sql .= ' AND name = :name';
            $params['name'] = $name;
        }
        $stmt = Database::connect()->prepare($sql . ' ORDER BY id LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public static function find(int $id, int $companyId): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM positions WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        return $stmt->fetch() ?: null;
    }
}
