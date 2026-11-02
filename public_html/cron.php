<?php

/**
 * Плановые задачи без системного cron.
 *
 * На бесплатном тарифе Beget cron недоступен, поэтому скрипт можно вызывать
 * внешним бесплатным планировщиком (cron-job.org, UptimeRobot и т. п.)
 * по адресу: /cron.php?key=CRON_KEY
 *
 * Ключ задаётся в config/config.php: define('CRON_KEY', 'длинная-случайная-строка');
 * Пока ключ не задан, веб-доступ закрыт; из консоли скрипт работает всегда.
 */

require __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Settings;
use App\Models\Attendance;
use App\Models\PasswordReset;
use App\Services\AbsenceMonitor;

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = defined('CRON_KEY') ? (string) CRON_KEY : '';
    $given    = (string) ($_GET['key'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(120);
}

ignore_user_abort(true);

$lockFile = dirname(__DIR__) . '/storage/cron.lock';
$lock = @fopen($lockFile, 'c');
if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
    if (!$isCli) {
        echo json_encode(['ok' => false, 'error' => 'Уже выполняется'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$result = ['ok' => true, 'time' => date('c')];

try {
    $result['closed_shifts'] = Attendance::closeStale(Settings::backgroundInactivity());
} catch (\Throwable $e) {
    $result['closed_shifts'] = null;
    $result['close_error'] = $e->getMessage();
    error_log('Cron closeStale: ' . $e->getMessage());
}

try {
    AbsenceMonitor::checkThrottled(120);
    $result['absence_checked'] = true;
} catch (\Throwable $e) {
    $result['absence_checked'] = false;
    $result['absence_error'] = $e->getMessage();
    error_log('Cron absence: ' . $e->getMessage());
}

try {
    $result['reset_tokens_purged'] = PasswordReset::purge();
} catch (\Throwable $e) {
    $result['reset_tokens_purged'] = null;
    error_log('Cron purge resets: ' . $e->getMessage());
}

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
@file_put_contents(
    $logDir . '/cron.log',
    '[' . date('Y-m-d H:i:s') . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

if ($lock !== false) {
    flock($lock, LOCK_UN);
    fclose($lock);
}

echo $isCli
    ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL
    : json_encode($result, JSON_UNESCAPED_UNICODE);
