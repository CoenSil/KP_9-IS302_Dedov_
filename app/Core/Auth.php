<?php



class Auth
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    
    public static function currentUser(PDO $pdo): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, username, email, phone, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }
}
