<?php

namespace App\Models;

class ScreenShare
{
    private const MAX_BYTES = 900 * 1024;

    private const FRESH_SECONDS = 10;

    private static function dir(): string
    {
        $dir = __DIR__ . '/../../storage/screens';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function path(int $employeeId): string
    {
        return self::dir() . '/' . $employeeId . '.jpg';
    }

    public static function save(int $employeeId, string $dataUrl): bool
    {
        if (!preg_match('/^data:image\/jpeg;base64,(.+)$/', $dataUrl, $m)) {
            return false;
        }
        $binary = base64_decode($m[1], true);
        if ($binary === false || strlen($binary) === 0 || strlen($binary) > self::MAX_BYTES) {
            return false;
        }
        file_put_contents(self::path($employeeId), $binary, LOCK_EX);
        return true;
    }

    public static function isLive(int $employeeId): bool
    {
        $path = self::path($employeeId);
        return file_exists($path) && (time() - filemtime($path)) <= self::FRESH_SECONDS;
    }

    public static function livePath(int $employeeId): ?string
    {
        return self::isLive($employeeId) ? self::path($employeeId) : null;
    }

    public static function stop(int $employeeId): void
    {
        $path = self::path($employeeId);
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
