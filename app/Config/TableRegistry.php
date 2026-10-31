<?php

namespace App\Config;

class TableRegistry
{
    public static function definitions(): array
    {
        return [
            'departments' => [
                'table' => 'departments',
                'label' => 'Подразделения',
                'fields' => [
                    ['name' => 'name', 'label' => 'Название', 'type' => 'text', 'required' => true],
                    ['name' => 'code', 'label' => 'Код', 'type' => 'text', 'required' => true],
                ],
            ],
            'positions' => [
                'table' => 'positions',
                'label' => 'Должности',
                'fields' => [
                    ['name' => 'name', 'label' => 'Название должности', 'type' => 'text', 'required' => true],
                    [
                        'name' => 'department_id', 'label' => 'Подразделение', 'type' => 'fk_select',
                        'fk_table' => 'departments', 'fk_value' => 'id', 'fk_label' => 'name', 'required' => true,
                    ],
                    ['name' => 'planned_login', 'label' => 'Плановое время прихода', 'type' => 'time', 'required' => false],
                    ['name' => 'planned_logout', 'label' => 'Плановое время ухода', 'type' => 'time', 'required' => false],
                    [
                        'name' => 'role', 'label' => 'Роль', 'type' => 'select', 'required' => true,
                        'options' => ['employee' => 'Сотрудник', 'admin' => 'Администратор'],
                    ],
                ],
            ],
            'employees' => [
                'table' => 'employees',
                'label' => 'Сотрудники',
                'fields' => [
                    ['name' => 'full_name', 'label' => 'ФИО', 'type' => 'text', 'required' => true],
                    [
                        'name' => 'position_id', 'label' => 'Должность', 'type' => 'fk_select',
                        'fk_table' => 'positions', 'fk_value' => 'id', 'fk_label' => 'name', 'required' => true,
                    ],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'password', 'label' => 'Пароль', 'type' => 'password', 'required' => true, 'db_column' => 'password_hash'],
                ],
            ],
            'attendance' => [
                'table' => 'attendance',
                'label' => 'Посещаемость',
                'fields' => [
                    [
                        'name' => 'employee_id', 'label' => 'Сотрудник', 'type' => 'fk_select',
                        'fk_table' => 'employees', 'fk_value' => 'id', 'fk_label' => 'full_name', 'required' => true,
                    ],
                    ['name' => 'work_date', 'label' => 'Дата', 'type' => 'date', 'required' => true],
                    ['name' => 'last_login_at', 'label' => 'Последний вход', 'type' => 'datetime', 'required' => true],
                    ['name' => 'last_logout_at', 'label' => 'Последний выход', 'type' => 'datetime', 'required' => false],
                ],
            ],
            'daily_hours' => [
                'table' => 'daily_hours',
                'label' => 'Отработанные часы по дням',
                'fields' => [
                    [
                        'name' => 'employee_id', 'label' => 'Сотрудник', 'type' => 'fk_select',
                        'fk_table' => 'employees', 'fk_value' => 'id', 'fk_label' => 'full_name', 'required' => true,
                    ],
                    ['name' => 'work_date', 'label' => 'Дата', 'type' => 'date', 'required' => true],
                    ['name' => 'total_seconds', 'label' => 'Отработано секунд (ручная правка, заменяет значение за день)', 'type' => 'number', 'required' => true],
                ],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        $all = self::definitions();
        return $all[$key] ?? null;
    }
}
