<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

class Workplace
{
    private static bool $tablesChecked = false;

    public const STATUS_MOVING  = 'moving';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_OVERDUE = 'overdue';

    public static function ensureTables(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'workplaces'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS workplaces (
                    id INT NOT NULL AUTO_INCREMENT,
                    company_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    is_active TINYINT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_company (company_id, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        if (!$db->query("SHOW TABLES LIKE 'workplace_moves'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS workplace_moves (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    company_id INT NOT NULL,
                    work_date DATE NOT NULL,
                    from_workplace_id INT NULL,
                    to_workplace_id INT NULL,
                    arrive_by DATETIME NULL,
                    arrived_at DATETIME NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'moving\',
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_employee_date (employee_id, work_date),
                    KEY idx_company_date (company_id, work_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }

        // Старые установки: добавляем колонки, если их ещё нет.
        // У существующих записей статус считается «на месте» — так текущее
        // рабочее место не потеряется после обновления.
        self::ensureColumn(
            'workplace_moves',
            'arrive_by',
            'ALTER TABLE workplace_moves ADD COLUMN arrive_by DATETIME NULL AFTER to_workplace_id'
        );
        self::ensureColumn(
            'workplace_moves',
            'arrived_at',
            'ALTER TABLE workplace_moves ADD COLUMN arrived_at DATETIME NULL AFTER arrive_by'
        );
        self::ensureColumn(
            'workplace_moves',
            'status',
            'ALTER TABLE workplace_moves ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT \'arrived\' AFTER arrived_at'
        );

        self::$tablesChecked = true;
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        try {
            $stmt = Database::connect()->prepare(
                'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $stmt->execute(['t' => $table, 'c' => $column]);
            $row = $stmt->fetch();
            if ($row && (int) $row['n'] > 0) {
                return;
            }
            Database::connect()->exec($sql);
        } catch (\Throwable $e) {
            error_log('Workplace column ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }

    public static function allForCompany(int $companyId, bool $onlyActive = false): array
    {
        self::ensureTables();
        $sql = 'SELECT * FROM workplaces WHERE company_id = :cid';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, name';
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id, int $companyId): ?array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT * FROM workplaces WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'cid' => $companyId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $companyId, string $name): int
    {
        self::ensureTables();
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO workplaces (company_id, name, is_active, created_at) VALUES (:cid, :name, 1, :now)'
        )->execute(['cid' => $companyId, 'name' => $name, 'now' => date('Y-m-d H:i:s')]);
        return (int) $db->lastInsertId();
    }

    public static function setActive(int $id, int $companyId, bool $active): void
    {
        self::ensureTables();
        Database::connect()->prepare(
            'UPDATE workplaces SET is_active = :a WHERE id = :id AND company_id = :cid'
        )->execute(['a' => $active ? 1 : 0, 'id' => $id, 'cid' => $companyId]);
    }

    public static function nameExists(int $companyId, string $name, ?int $exceptId = null): bool
    {
        self::ensureTables();
        $sql = 'SELECT id FROM workplaces WHERE company_id = :cid AND name = :name';
        $params = ['cid' => $companyId, 'name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connect()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    /**
     * Подтверждённое текущее рабочее место (куда сотрудник уже пришёл).
     */
    public static function currentFor(int $employeeId, ?string $date = null): ?array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $stmt = Database::connect()->prepare(
            'SELECT wm.id, wm.to_workplace_id, wm.created_at, wm.arrive_by, wm.arrived_at, wm.status, w.name
             FROM workplace_moves wm
             LEFT JOIN workplaces w ON w.id = wm.to_workplace_id
             WHERE wm.employee_id = :eid AND wm.work_date = :d
               AND (wm.arrived_at IS NOT NULL OR wm.status = \'arrived\')
             ORDER BY wm.id DESC LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Переход, который ещё не подтверждён («в пути» или просрочен).
     */
    public static function pendingFor(int $employeeId, ?string $date = null): ?array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $stmt = Database::connect()->prepare(
            'SELECT wm.*, wf.name AS from_name, wt.name AS to_name
             FROM workplace_moves wm
             LEFT JOIN workplaces wf ON wf.id = wm.from_workplace_id
             LEFT JOIN workplaces wt ON wt.id = wm.to_workplace_id
             WHERE wm.employee_id = :eid AND wm.work_date = :d
               AND wm.arrived_at IS NULL AND wm.status IN (\'moving\', \'overdue\')
             ORDER BY wm.id DESC LIMIT 1'
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function movesFor(int $employeeId, ?string $date = null, int $limit = 8): array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $limit = max(1, min(50, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT wm.id, wm.created_at, wm.arrive_by, wm.arrived_at, wm.status,
                    wf.name AS from_name, wt.name AS to_name
             FROM workplace_moves wm
             LEFT JOIN workplaces wf ON wf.id = wm.from_workplace_id
             LEFT JOIN workplaces wt ON wt.id = wm.to_workplace_id
             WHERE wm.employee_id = :eid AND wm.work_date = :d
             ORDER BY wm.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['eid' => $employeeId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    public static function recentForCompany(int $companyId, ?string $date = null, int $limit = 20): array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $limit = max(1, min(100, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT wm.id, wm.created_at, wm.arrive_by, wm.arrived_at, wm.status,
                    e.full_name, wf.name AS from_name, wt.name AS to_name
             FROM workplace_moves wm
             JOIN employees e ON e.id = wm.employee_id
             LEFT JOIN workplaces wf ON wf.id = wm.from_workplace_id
             LEFT JOIN workplaces wt ON wt.id = wm.to_workplace_id
             WHERE wm.company_id = :cid AND wm.work_date = :d
             ORDER BY wm.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    public static function currentMap(int $companyId, ?string $date = null): array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $stmt = Database::connect()->prepare(
            'SELECT wm.employee_id, wm.to_workplace_id, wm.from_workplace_id, wm.created_at,
                    wm.arrive_by, wm.arrived_at, wm.status, w.name
             FROM workplace_moves wm
             JOIN (
                 SELECT employee_id, MAX(id) AS max_id
                 FROM workplace_moves
                 WHERE company_id = :cid AND work_date = :d
                 GROUP BY employee_id
             ) last ON last.max_id = wm.id
             LEFT JOIN workplaces w ON w.id = wm.to_workplace_id'
        );
        $stmt->execute(['cid' => $companyId, 'd' => $date]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['employee_id']] = $row;
        }
        return $map;
    }

    /**
     * Начинает переход. Сотрудник должен успеть подтвердить прибытие
     * за отведённое компанией время.
     */
    public static function move(int $employeeId, int $companyId, int $toId): array
    {
        self::ensureTables();
        self::finalizeOverdue($companyId);

        $to = self::find($toId, $companyId);
        if (!$to || (int) $to['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Рабочее место не найдено или отключено.'];
        }

        $current = self::currentFor($employeeId);
        $fromId = $current && !empty($current['to_workplace_id']) ? (int) $current['to_workplace_id'] : null;
        if ($fromId === $toId) {
            return ['ok' => true, 'same' => true, 'state' => self::stateFor($employeeId, $companyId)];
        }

        $pending = self::pendingFor($employeeId);
        if ($pending) {
            $left = $pending['arrive_by'] ? strtotime($pending['arrive_by']) - time() : 0;
            if ($left > 0) {
                return [
                    'ok' => false,
                    'error' => 'Сначала подтвердите, что вы на месте: нажмите «Я на месте».',
                    'state' => self::stateFor($employeeId, $companyId),
                ];
            }
            return [
                'ok' => false,
                'error' => 'Предыдущий переход просрочен. Нажмите «Я на месте», чтобы закрыть его.',
                'state' => self::stateFor($employeeId, $companyId),
            ];
        }

        $now = time();
        $seconds = Settings::workplaceMoveSeconds($companyId);
        $db = Database::connect();
        $db->prepare(
            'INSERT INTO workplace_moves
                (employee_id, company_id, work_date, from_workplace_id, to_workplace_id,
                 arrive_by, arrived_at, status, created_at)
             VALUES (:eid, :cid, :d, :from, :to, :arrive_by, NULL, \'moving\', :now)'
        )->execute([
            'eid'       => $employeeId,
            'cid'       => $companyId,
            'd'         => date('Y-m-d', $now),
            'from'      => $fromId,
            'to'        => $toId,
            'arrive_by' => date('Y-m-d H:i:s', $now + $seconds),
            'now'       => date('Y-m-d H:i:s', $now),
        ]);

        return ['ok' => true, 'same' => false, 'state' => self::stateFor($employeeId, $companyId)];
    }

    /**
     * Сотрудник подтверждает, что дошёл до нового места.
     */
    public static function arrive(int $employeeId, int $companyId): array
    {
        self::ensureTables();
        self::finalizeOverdue($companyId);

        $pending = self::pendingFor($employeeId);
        if (!$pending) {
            return ['ok' => false, 'error' => 'Активного перехода нет.', 'state' => self::stateFor($employeeId, $companyId)];
        }

        $now = time();
        $deadline = $pending['arrive_by']
            ? strtotime($pending['arrive_by'])
            : strtotime($pending['created_at']) + Settings::workplaceMoveSeconds($companyId);
        $late = $now > $deadline;

        Database::connect()->prepare(
            'UPDATE workplace_moves SET arrived_at = :now, status = :status WHERE id = :id'
        )->execute([
            'now'    => date('Y-m-d H:i:s', $now),
            'status' => $late ? self::STATUS_OVERDUE : self::STATUS_ARRIVED,
            'id'     => $pending['id'],
        ]);

        return [
            'ok'    => true,
            'late'  => $late,
            'state' => self::stateFor($employeeId, $companyId),
        ];
    }

    public static function stateFor(int $employeeId, int $companyId): array
    {
        self::finalizeOverdue($companyId);

        $moveSeconds = Settings::workplaceMoveSeconds($companyId);
        $pending = self::pendingFor($employeeId);
        $current = self::currentFor($employeeId);

        $state = [
            'moving'       => false,
            'overdue'      => false,
            'seconds_left' => 0,
            'move_seconds' => $moveSeconds,
            'arrive_by'    => null,
            'to_name'      => null,
            'from_name'    => null,
            'current'      => $current['name'] ?? null,
            'current_id'   => $current ? (int) $current['to_workplace_id'] : null,
        ];

        if ($pending) {
            $deadline = $pending['arrive_by']
                ? strtotime($pending['arrive_by'])
                : strtotime($pending['created_at']) + $moveSeconds;
            $left = $deadline - time();

            $state['moving']       = true;
            $state['overdue']      = $left <= 0 || $pending['status'] === self::STATUS_OVERDUE;
            $state['seconds_left'] = max(0, $left);
            $state['arrive_by']    = date('H:i', $deadline);
            $state['to_name']      = $pending['to_name'] ?? null;
            $state['from_name']    = $pending['from_name'] ?? null;
            $state['current_id']   = $pending['to_workplace_id'] ? (int) $pending['to_workplace_id'] : $state['current_id'];
        }

        return $state;
    }

    /**
     * Переводит просроченные переходы в статус «просрочен» и ставит
     * отметку администратору. Каждый переход отмечается один раз.
     */
    public static function finalizeOverdue(int $companyId, ?int $now = null): int
    {
        self::ensureTables();
        $now = $now ?? time();
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT id, employee_id FROM workplace_moves
             WHERE company_id = :cid AND status = \'moving\' AND arrived_at IS NULL
               AND arrive_by IS NOT NULL AND arrive_by < :now
             ORDER BY id LIMIT 100'
        );
        $stmt->execute(['cid' => $companyId, 'now' => date('Y-m-d H:i:s', $now)]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return 0;
        }

        $upd = $db->prepare(
            'UPDATE workplace_moves SET status = \'overdue\' WHERE id = :id AND status = \'moving\''
        );
        $done = 0;
        foreach ($rows as $row) {
            $upd->execute(['id' => $row['id']]);
            if ($upd->rowCount() < 1) {
                continue;
            }
            $done++;
            try {
                Verification::mark(
                    (int) $row['employee_id'],
                    $companyId,
                    'workplace_overdue',
                    'Не подтвердил переход между рабочими местами за отведённое время'
                );
            } catch (\Throwable $e) {
                error_log('Workplace overdue mark: ' . $e->getMessage());
            }
        }
        return $done;
    }
}
