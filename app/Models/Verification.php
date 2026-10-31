<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Settings;

class Verification
{
    private static bool $tablesChecked = false;

    public static function ensureTables(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        $db = Database::connect();
        if (!$db->query("SHOW TABLES LIKE 'verification_challenges'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS verification_challenges (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    company_id INT NOT NULL,
                    token CHAR(32) NOT NULL,
                    code TINYINT NOT NULL,
                    options TEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                    attempts TINYINT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    answered_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_token (token),
                    KEY idx_employee_status (employee_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        if (!$db->query("SHOW TABLES LIKE 'verification_marks'")->fetch()) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS verification_marks (
                    id INT NOT NULL AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    company_id INT NOT NULL,
                    kind VARCHAR(32) NOT NULL,
                    detail VARCHAR(255) NOT NULL,
                    work_date DATE NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_company_date (company_id, work_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        self::$tablesChecked = true;
    }

    public static function issue(int $employeeId, int $companyId): array
    {
        self::ensureTables();
        $db = Database::connect();
        $now = time();

        $db->prepare(
            'UPDATE verification_challenges SET status = \'superseded\', answered_at = :now
             WHERE employee_id = :eid AND status = \'pending\''
        )->execute(['now' => date('Y-m-d H:i:s', $now), 'eid' => $employeeId]);

        $code = random_int(10, 99);
        $options = [$code];
        while (count($options) < 3) {
            $candidate = random_int(10, 99);
            if (!in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }
        shuffle($options);

        $token = bin2hex(random_bytes(16));
        $expires = $now + Settings::verifyTimeout();

        $db->prepare(
            'INSERT INTO verification_challenges
                (employee_id, company_id, token, code, options, expires_at, status, attempts, created_at)
             VALUES (:eid, :cid, :token, :code, :options, :expires, \'pending\', 0, :created)'
        )->execute([
            'eid'     => $employeeId,
            'cid'     => $companyId,
            'token'   => $token,
            'code'    => $code,
            'options' => json_encode($options),
            'expires' => date('Y-m-d H:i:s', $expires),
            'created' => date('Y-m-d H:i:s', $now),
        ]);

        return [
            'token'      => $token,
            'code'       => $code,
            'options'    => $options,
            'expires_at' => $expires,
            'attempts'   => 0,
        ];
    }

    public static function rowFor(string $token, int $employeeId): ?array
    {
        self::ensureTables();
        $stmt = Database::connect()->prepare(
            'SELECT * FROM verification_challenges WHERE token = :token AND employee_id = :eid LIMIT 1'
        );
        $stmt->execute(['token' => $token, 'eid' => $employeeId]);
        return $stmt->fetch() ?: null;
    }

    public static function answer(array $challenge, int $employeeId, string $answer): array
    {
        $token = (string) ($challenge['token'] ?? '');
        $row = $token !== '' ? self::rowFor($token, $employeeId) : null;
        if (!$row) {
            return ['status' => 'missing', 'attempts_left' => 0];
        }
        if ($row['status'] !== 'pending') {
            return ['status' => 'missing', 'attempts_left' => 0];
        }

        $now = time();
        if (strtotime($row['expires_at']) <= $now) {
            self::closeRow((int) $row['id'], 'expired');
            return ['status' => 'expired', 'attempts_left' => 0];
        }

        if ((int) $answer === (int) $row['code']) {
            self::closeRow((int) $row['id'], 'passed');
            return ['status' => 'ok', 'attempts_left' => 0];
        }

        $attempts = (int) $row['attempts'] + 1;
        if ($attempts >= 3) {
            self::closeRow((int) $row['id'], 'failed', $attempts);
            return ['status' => 'failed', 'attempts_left' => 0];
        }

        Database::connect()->prepare(
            'UPDATE verification_challenges SET attempts = :n WHERE id = :id'
        )->execute(['n' => $attempts, 'id' => $row['id']]);

        return ['status' => 'wrong', 'attempts_left' => 3 - $attempts];
    }

    public static function expire(array $challenge, int $employeeId): bool
    {
        $token = (string) ($challenge['token'] ?? '');
        $row = $token !== '' ? self::rowFor($token, $employeeId) : null;
        if (!$row || $row['status'] !== 'pending') {
            return false;
        }
        self::closeRow((int) $row['id'], 'expired');
        return true;
    }

    public static function isExpired(array $challenge, int $employeeId): bool
    {
        $token = (string) ($challenge['token'] ?? '');
        if ($token === '') {
            return false;
        }
        $row = self::rowFor($token, $employeeId);
        if (!$row) {
            return true;
        }
        return $row['status'] !== 'pending' || strtotime($row['expires_at']) <= time();
    }

    public static function mark(int $employeeId, int $companyId, string $kind, string $detail): void
    {
        self::ensureTables();
        $now = date('Y-m-d H:i:s');
        Database::connect()->prepare(
            'INSERT INTO verification_marks
                (employee_id, company_id, kind, detail, work_date, created_at)
             VALUES (:eid, :cid, :kind, :detail, :d, :now)'
        )->execute([
            'eid'    => $employeeId,
            'cid'    => $companyId,
            'kind'   => mb_substr($kind, 0, 32),
            'detail' => mb_substr($detail, 0, 255),
            'd'      => date('Y-m-d'),
            'now'    => $now,
        ]);
    }

    public static function markCollapse(int $employeeId, int $companyId, string $detail = 'Проверка присутствия не пройдена за 5 минут'): void
    {
        self::mark($employeeId, $companyId, 'app_collapsed', $detail);
    }

    public static function collapseFeed(int $companyId, ?string $date = null, int $limit = 20): array
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $limit = max(1, min(100, $limit));
        $stmt = Database::connect()->prepare(
            "SELECT v.id, v.detail, v.created_at, e.full_name, p.name AS position_name
             FROM verification_marks v
             JOIN employees e ON e.id = v.employee_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE v.company_id = :cid AND v.work_date = :d AND v.kind = 'app_collapsed'
             ORDER BY v.id DESC LIMIT {$limit}"
        );
        $stmt->execute(['cid' => $companyId, 'd' => $date]);
        return $stmt->fetchAll();
    }

    public static function collapseCount(int $companyId, ?string $date = null): int
    {
        self::ensureTables();
        $date = $date ?? date('Y-m-d');
        $stmt = Database::connect()->prepare(
            'SELECT COUNT(*) AS n FROM verification_marks
             WHERE company_id = :cid AND work_date = :d AND kind = \'app_collapsed\''
        );
        $stmt->execute(['cid' => $companyId, 'd' => $date]);
        $row = $stmt->fetch();
        return $row ? (int) $row['n'] : 0;
    }

    private static function closeRow(int $id, string $status, int $attempts = 0): void
    {
        Database::connect()->prepare(
            'UPDATE verification_challenges
             SET status = :status, attempts = GREATEST(attempts, :attempts), answered_at = :now
             WHERE id = :id'
        )->execute([
            'status'   => $status,
            'attempts' => $attempts,
            'now'      => date('Y-m-d H:i:s'),
            'id'       => $id,
        ]);
    }
}
