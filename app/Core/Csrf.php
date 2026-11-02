<?php

namespace App\Core;

/**
 * Защита от подделки межсайтовых запросов (CSRF).
 *
 * Токен живёт в сессии, подставляется во все формы автоматически
 * (через буфер вывода) и проверяется один раз — в точке входа.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    private const FIELD = '_token';

    public static function token(): string
    {
        $current = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($current) || strlen($current) < 32) {
            $current = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $current;
        }
        return $current;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function submitted(): ?string
    {
        $token = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($token) ? $token : null;
    }

    public static function check(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /**
     * Буфер вывода: добавляет скрытое поле во все POST-формы страницы.
     * Так не нужно править каждый шаблон вручную.
     */
    public static function inject(string $html): string
    {
        if ($html === '' || stripos($html, '<form') === false) {
            return $html;
        }
        if (!self::isHtmlResponse()) {
            return $html;
        }

        $field = self::field();

        return (string) preg_replace_callback(
            '/<form\b[^>]*>/i',
            static function (array $match) use ($field): string {
                if (preg_match('/method\s*=\s*["\']?\s*get/i', $match[0])) {
                    return $match[0];
                }
                return $match[0] . $field;
            },
            $html
        );
    }

    private static function isHtmlResponse(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }
        return true;
    }
}
