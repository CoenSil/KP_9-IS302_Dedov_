<?php

namespace App\Models;

use App\Core\Database;

class GenericRecord
{
    public const DIRECT_TABLES = ['departments', 'positions', 'employees'];
    public const VIA_EMPLOYEE_TABLES = ['attendance', 'daily_hours'];

    public static function insert(string $table, array $columns, array $values): int
    {
        $db = Database::connect();
        $cols = implode(', ', $columns);
        $placeholders = implode(', ', array_map(fn ($c) => ':' . $c, $columns));
        $stmt = $db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})");
        $stmt->execute($values);
        return (int) $db->lastInsertId();
    }

    public static function latest(string $table, int $companyId, int $limit = 10): array
    {
        $db = Database::connect();
        $limit = max(1, min(100, $limit));

        if (in_array($table, self::DIRECT_TABLES, true)) {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE company_id = :cid ORDER BY id DESC LIMIT {$limit}");
        } elseif (in_array($table, self::VIA_EMPLOYEE_TABLES, true)) {
            $stmt = $db->prepare(
                "SELECT t.* FROM {$table} t JOIN employees e ON e.id = t.employee_id
                 WHERE e.company_id = :cid ORDER BY t.id DESC LIMIT {$limit}"
            );
        } else {
            throw new \InvalidArgumentException('Таблица не поддерживает изоляцию по компаниям: ' . $table);
        }
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function options(string $table, string $valueCol, string $labelCol, int $companyId): array
    {
        if (!in_array($table, self::DIRECT_TABLES, true)) {
            throw new \InvalidArgumentException('Справочник без company_id: ' . $table);
        }
        $stmt = Database::connect()->prepare(
            "SELECT {$valueCol} AS value, {$labelCol} AS label FROM {$table}
             WHERE company_id = :cid ORDER BY {$labelCol}"
        );
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }
}
