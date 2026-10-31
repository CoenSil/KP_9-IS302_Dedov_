<?php
/**
 * Разовая утилита: сгенерировать password_hash для сотрудника/админа.
 * Запуск из консоли: php make_password_hash.php "мойпароль"
 * Полученную строку вставить в поле employees.password_hash.
 */
if ($argc < 2) {
    echo "Использование: php make_password_hash.php \"пароль\"\n";
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
