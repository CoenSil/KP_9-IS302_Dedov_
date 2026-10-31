<?php

namespace App\Core;

class Validator
{
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Возвращает текст ошибки или null, если адрес корректен.
     * Проверка без обращения к DNS: на shared-хостинге она может зависать.
     */
    public static function emailError(string $email, string $field = 'Email'): ?string
    {
        $email = self::normalizeEmail($email);

        if ($email === '') {
            return 'Укажите ' . mb_strtolower($field) . '.';
        }
        if (mb_strlen($email) > 254) {
            return $field . ' слишком длинный (максимум 254 символа).';
        }
        if (preg_match('/\s/u', $email) || str_contains($email, '..')) {
            return $field . ' указан некорректно. Пример: name@company.ru';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $field . ' указан некорректно. Пример: name@company.ru';
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) > 64 || strlen($domain) > 253) {
            return $field . ' указан некорректно: слишком длинная часть адреса.';
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $domain)) {
            return $field . ' указан некорректно: проверьте домен.';
        }
        if (!preg_match('/\.[a-z]{2,24}$/i', $domain)) {
            return $field . ' указан некорректно: домен должен заканчиваться на .ru, .com и т. п.';
        }

        return null;
    }

    public static function validEmail(string $email): bool
    {
        return self::emailError($email) === null;
    }
}
