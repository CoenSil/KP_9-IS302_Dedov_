<?php

// Пример конфигурации. Скопируйте файл в config/config.php и укажите
// реальные доступы к базе данных. config/config.php в репозиторий не попадает.

define('DB_HOST', 'localhost');
define('DB_NAME', 'opressence');
define('DB_USER', 'opressence');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'OpresSence');
define('BASE_URL', '/');

define('INACTIVITY_TIMEOUT', 5 * 60);

// Перерывы студентов учебных заведений: не более 3 раз в день,
// суммарно не более 10 минут. Сотрудникам компаний свободные перерывы
// не выдаются — только окна, заданные администратором.
define('BREAK_MAX_COUNT', 3);
define('BREAK_TOTAL_SECONDS', 10 * 60);

// Время на переход между рабочими местами (минуты по умолчанию).
define('WORKPLACE_MOVE_MINUTES', 5);

// Проверка присутствия: код из трёх вариантов, 5 минут на ответ.
define('VERIFY_INTERVAL', 20 * 60);
define('VERIFY_TIMEOUT', 5 * 60);

// Форс-мажор: максимальная длина комментария.
define('FORCE_MAJEURE_COMMENT_MAX', 500);

// --- Почта ---------------------------------------------------------------
// transport: 'mail' — отправка через mail() хостинга, 'log' — только запись
// в storage/logs/mail.log (удобно, пока ящик не настроен).
define('MAIL_TRANSPORT', 'mail');
define('MAIL_FROM', 'no-reply@example.ru');
define('MAIL_FROM_NAME', 'OpresSence');

// --- Служебные ключи -----------------------------------------------------
// Придумайте длинные случайные строки и не публикуйте их.
// CRON_KEY  — запуск /cron.php?key=... внешним планировщиком;
// HEALTH_KEY — подробный ответ /health.php;
// MIGRATE_KEY — разовый запуск /migrate.php.
define('CRON_KEY', '');
define('HEALTH_KEY', '');
define('MIGRATE_KEY', '');

// --- Реквизиты для лендинга и документов --------------------------------
define('LEGAL_NAME', 'ИП Фамилия Имя Отчество');
define('LEGAL_INN', '');
define('LEGAL_EMAIL', 'hello@example.ru');
define('LEGAL_PHONE', '');
// Куда приходят заявки с сайта.
define('LEAD_EMAIL', 'hello@example.ru');

date_default_timezone_set('Asia/Novosibirsk');

define('SESSION_LIFETIME', 12 * 60 * 60);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);
