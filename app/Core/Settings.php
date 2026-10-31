<?php

namespace App\Core;

class Settings
{
    public static function inactivity(): int
    {
        return defined('INACTIVITY_TIMEOUT') ? (int) INACTIVITY_TIMEOUT : 300;
    }

    public static function backgroundInactivity(): int
    {
        $bg = defined('BACKGROUND_INACTIVITY_TIMEOUT') ? (int) BACKGROUND_INACTIVITY_TIMEOUT : 1800;
        return max(self::inactivity(), $bg);
    }

    public static function lunchMax(): int
    {
        return defined('LUNCH_MAX_SECONDS') ? max(0, (int) LUNCH_MAX_SECONDS) : 3600;
    }

    public static function breakMaxCount(): int
    {
        return defined('BREAK_MAX_COUNT') ? max(0, (int) BREAK_MAX_COUNT) : 3;
    }

    public static function breakTotal(): int
    {
        return defined('BREAK_TOTAL_SECONDS') ? max(0, (int) BREAK_TOTAL_SECONDS) : 600;
    }

    public static function verifyInterval(): int
    {
        return defined('VERIFY_INTERVAL') ? max(60, (int) VERIFY_INTERVAL) : 20 * 60;
    }

    public static function verifyTimeout(): int
    {
        return defined('VERIFY_TIMEOUT') ? max(30, (int) VERIFY_TIMEOUT) : 5 * 60;
    }

    public static function forceCommentMax(): int
    {
        return defined('FORCE_MAJEURE_COMMENT_MAX') ? max(10, (int) FORCE_MAJEURE_COMMENT_MAX) : 500;
    }

    /**
     * Сколько секунд даётся сотруднику на переход между рабочими местами.
     * Значение компании важнее общей настройки из config.php.
     */
    public static function workplaceMoveSeconds(?int $companyId = null): int
    {
        $default = defined('WORKPLACE_MOVE_MINUTES') ? max(1, (int) WORKPLACE_MOVE_MINUTES) * 60 : 300;
        if ($companyId !== null && $companyId > 0) {
            try {
                $value = \App\Models\CompanySetting::workplaceMoveSeconds($companyId);
                if ($value !== null && $value > 0) {
                    return $value;
                }
            } catch (\Throwable $e) {
                error_log('Company settings: ' . $e->getMessage());
            }
        }
        return $default;
    }
}
