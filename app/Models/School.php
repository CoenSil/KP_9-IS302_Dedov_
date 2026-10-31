<?php

namespace App\Models;

use App\Core\Database;

class School
{
    public static function stats(int $companyId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM employees e JOIN positions p ON p.id = e.position_id
                  WHERE e.company_id = :c1 AND p.role = 'admin') AS admins,
                (SELECT COUNT(*) FROM employees e JOIN positions p ON p.id = e.position_id
                  WHERE e.company_id = :c2 AND p.role = 'teacher') AS teachers,
                (SELECT COUNT(*) FROM employees e JOIN positions p ON p.id = e.position_id
                  WHERE e.company_id = :c3 AND p.role = 'student') AS students,
                (SELECT COUNT(*) FROM employees e JOIN positions p ON p.id = e.position_id
                  LEFT JOIN group_students gs ON gs.student_id = e.id
                  WHERE e.company_id = :c4 AND p.role = 'student' AND gs.id IS NULL) AS ungrouped,
                (SELECT COUNT(*) FROM school_groups g WHERE g.company_id = :c5) AS groups_count"
        );
        $stmt->execute(['c1' => $companyId, 'c2' => $companyId, 'c3' => $companyId, 'c4' => $companyId, 'c5' => $companyId]);
        return $stmt->fetch();
    }
}
