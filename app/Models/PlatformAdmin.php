<?php

namespace App\Models;

use App\Core\Database;

class PlatformAdmin
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM platform_admins WHERE email = :e LIMIT 1');
        $stmt->execute(['e' => $email]);
        return $stmt->fetch() ?: null;
    }
}
