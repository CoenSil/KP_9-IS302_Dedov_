<?php

namespace App\Core;

/**
 * Небольшой помощник для проверки типов колонок в текущей базе.
 *
 * Нужен для совместимости с рабочими базами, где исторически
 * `attendance.updated_at` и `departments.created_at` объявлены как TIME,
 * а приложение ожидает DATETIME. Пока миграция не выполнена, значения
 * пишутся и читаются в формате, который принимает колонка.
 */
class Schema
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function columnType(string $table, string $column): ?string
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $stmt = Database::connect()->prepare(
                'SELECT DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1'
            );
            $stmt->execute(['t' => $table, 'c' => $column]);
            $type = $stmt->fetchColumn();
            self::$cache[$key] = $type !== false && $type !== null ? strtolower((string) $type) : null;
        } catch (\Throwable $e) {
            error_log('Schema::columnType(' . $key . '): ' . $e->getMessage());
            self::$cache[$key] = null;
        }

        return self::$cache[$key];
    }

    public static function isTimeColumn(string $table, string $column): bool
    {
        return self::columnType($table, $column) === 'time';
    }

    /**
     * Значение для записи в колонку: TIME получает только время,
     * DATETIME/TIMESTAMP — полную дату.
     */
    public static function dateValue(string $table, string $column, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();
        return self::isTimeColumn($table, $column)
            ? date('H:i:s', $ts)
            : date('Y-m-d H:i:s', $ts);
    }

    /**
     * Преобразует значение колонки в timestamp.
     * Для TIME-колонок дата берётся из переданного $date.
     */
    public static function timestamp(?string $value, string $date): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        if (strpos($value, '-') !== false) {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }

        $ts = strtotime($date . ' ' . $value);
        return $ts === false ? null : $ts;
    }
}
