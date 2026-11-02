<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Одноразовые ссылки восстановления пароля.
 *
 * В базе хранится только хеш токена: если база утечёт, ссылками
 * воспользоваться нельзя.
 */
class PasswordReset
{
    public const TTL_MINUTES = 60;

    public static function create(int $employeeId, string $ip): string
    {
        $db     = Database::connect();
        $token  = bin2hex(random_bytes(32));
        $now    = date('Y-m-d H:i:s');
        $expire = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);

        $db->prepare('DELETE FROM password_resets WHERE employee_id = :eid')
           ->execute(['eid' => $employeeId]);
        $db->prepare('DELETE FROM password_resets WHERE expires_at < :now')
           ->execute(['now' => $now]);

        $db->prepare(
            'INSERT INTO password_resets (employee_id, token_hash, expires_at, requested_ip, created_at)
             VALUES (:eid, :hash, :exp, :ip, :now)'
        )->execute([
            'eid'  => $employeeId,
            'hash' => hash('sha256', $token),
            'exp'  => $expire,
            'ip'   => mb_substr($ip, 0, 45),
            'now'  => $now,
        ]);

        return $token;
    }

    /**
     * @return array{id:int, employee_id:int, full_name:string, email:string}|null
     */
    public static function findValid(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $stmt = Database::connect()->prepare(
            "SELECT pr.id, pr.employee_id, e.full_name, e.email
             FROM password_resets pr
             JOIN employees e ON e.id = pr.employee_id
             WHERE pr.token_hash = :hash
               AND pr.used_at IS NULL
               AND pr.expires_at >= :now
             LIMIT 1"
        );
        $stmt->execute(['hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function consume(int $id): void
    {
        $stmt = Database::connect()->prepare('UPDATE password_resets SET used_at = :now WHERE id = :id');
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    public static function purge(): int
    {
        $stmt = Database::connect()->prepare(
            'DELETE FROM password_resets WHERE expires_at < :now OR used_at IS NOT NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s', time() - 86400)]);
        return $stmt->rowCount();
    }
}
