<?php

namespace App\Core;

/**
 * Отправка писем через mail().
 *
 * На shared-хостинге Beget mail() работает, если в панели создан почтовый
 * ящик на домене сайта. Транспорт можно переключить на «log» — тогда письма
 * не уходят, а пишутся в storage/logs/mail.log (удобно на время отладки).
 */
class Mailer
{
    public static function send(string $to, string $subject, string $text): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (defined('MAIL_TRANSPORT') && MAIL_TRANSPORT === 'log') {
            self::log($to, $subject, $text, 'log');
            return true;
        }

        $from     = defined('MAIL_FROM') ? (string) MAIL_FROM : ('no-reply@' . self::host());
        $fromName = defined('MAIL_FROM_NAME') ? (string) MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'OpresSence');

        $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromEncoded    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';

        $headers = [
            'From: ' . $fromEncoded,
            'Reply-To: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: OpresSence',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $body = str_replace("\n", "\r\n", $body);

        $ok = false;
        try {
            $ok = @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
        }

        if (!$ok) {
            self::log($to, $subject, $text, 'fail');
        }

        return (bool) $ok;
    }

    /**
     * Письмо со ссылкой восстановления пароля.
     */
    public static function sendPasswordReset(string $to, string $fullName, string $link, int $minutes): bool
    {
        $name = $fullName !== '' ? $fullName : 'коллега';
        $text = "Здравствуйте, {$name}!\n\n"
            . "Вы запросили восстановление пароля в " . self::appName() . ".\n"
            . "Ссылка действует {$minutes} минут и открывается только один раз:\n\n"
            . $link . "\n\n"
            . "Если вы не запрашивали восстановление, просто проигнорируйте это письмо — пароль останется прежним.\n\n"
            . "— " . self::appName();

        return self::send($to, 'Восстановление пароля в ' . self::appName(), $text);
    }

    public static function appName(): string
    {
        return defined('APP_NAME') ? (string) APP_NAME : 'OpresSence';
    }

    private static function host(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        return $host;
    }

    private static function log(string $to, string $subject, string $text, string $status): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $status . ' to=' . $to
            . ' subject=' . $subject . "\n" . $text . "\n---\n";
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
    }
}
