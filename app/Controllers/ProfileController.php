<?php

require_once __DIR__ . '/../Core/Auth.php';

class ProfileController
{
   
    public function index(): void
    {
        Auth::requireAuth();
        global $pdo;

        $user = Auth::currentUser($pdo);

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        require __DIR__ . '/../../views/profile/index.php';
    }

   
    public function edit(): void
    {
        Auth::requireAuth();
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /profile');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if ($username === '' || $email === '') {
            $_SESSION['flash_error'] = 'Никнейм и email обязательны для заполнения.';
            header('Location: /profile');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Введите корректный email.';
            header('Location: /profile');
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET username = :username, email = :email, phone = :phone WHERE id = :id'
            );
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':phone'    => $phone,
                ':id'       => $_SESSION['user_id'],
            ]);

            $_SESSION['flash_success'] = 'Данные профиля успешно обновлены.';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = $e->getCode() == 23000
                ? 'Такой email или никнейм уже используется другим пользователем.'
                : 'Не удалось обновить данные. Попробуйте позже.';
        }

        header('Location: /profile');
        exit;
    }

    
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}
