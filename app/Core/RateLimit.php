<?php

namespace App\Core;

/**
 * Простое ограничение частоты действий без Redis и без cron.
 *
 * Состояние хранится в файлах storage/ratelimit: подходит для shared-хостинга
 * и не требует дополнительных таблиц в базе.
 */
class RateLimit
{
    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Учитывает попытку и сообщает, разрешена ли она.
     *
     * @return array{allowed: bool, retry: int, count: int}
     */
    public static function hit(string $key, int $limit, int $window): array
    {
        $limit  = max(1, $limit);
        $window = max(1, $window);
        $now    = time();

        self::gc();

        $file = self::dir() . '/' . hash('sha256', $key) . '.json';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // Нет доступа к диску — не блокируем работу приложения.
            return ['allowed' => true, 'retry' => 0, 'count' => 0];
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return ['allowed' => true, 'retry' => 0, 'count' => 0];
            }

            $raw  = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            if (!is_array($data) || (int) ($data['reset'] ?? 0) <= $now) {
                $data = ['count' => 0, 'reset' => $now + $window];
            }

            $data['count'] = (int) ($data['count'] ?? 0) + 1;
            $allowed = $data['count'] <= $limit;
            $retry   = max(0, (int) $data['reset'] - $now);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($data));
            fflush($fp);

            return ['allowed' => $allowed, 'retry' => $retry, 'count' => (int) $data['count']];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function clear(string $key): void
    {
        $file = self::dir() . '/' . hash('sha256', $key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Человеческое описание паузы: «через 3 мин».
     */
    public static function retryText(int $seconds): string
    {
        if ($seconds <= 60) {
            return 'через минуту';
        }
        $minutes = (int) ceil($seconds / 60);
        return 'через ' . $minutes . ' мин';
    }

    private static function gc(): void
    {
        // Чистим старые файлы примерно в 1 случае из 100 обращений.
        if (random_int(1, 100) !== 1) {
            return;
        }
        $dir = self::dir();
        $files = @glob($dir . '/*.json') ?: [];
        $limit = time() - 86400;
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $limit) {
                @unlink($file);
            }
        }
    }
}
