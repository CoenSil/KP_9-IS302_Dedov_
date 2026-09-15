<?php


class AuthController {
    
    
    public function login() {
        global $pdo; 
        $errorMsg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email']);
            $pass = $_POST['password'];

            if (!empty($email) && !empty($pass)) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($pass, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: /profile"); 
                    exit;
                } else {
                    $errorMsg = "Неверный логин или пароль.";
                }
            }
        }
        
        require_once '../views/auth/login.php';
    }

    public function register() {
    global $pdo; 
    $errorMsg = '';
    $successMsg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email) || empty($phone) || empty($password)) {
            $errorMsg = "Все поля обязательны для заполнения";
        } else {
            try {
                $p_hash = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (username, password_hash, email, phone, role) 
                        VALUES (:username, :password_hash, :email, :phone, 'client')";
                $stmt = $pdo->prepare($sql);
                
                $stmt->execute([
                    ':username'      => $username,
                    ':password_hash' => $p_hash,
                    ':email'         => $email,
                    ':phone'         => $phone
                ]);
                
                $successMsg = "Регистрация прошла успешно!";
                $_POST = [];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errorMsg = "Пользователь с таким именем или email уже существует.";
                } else {
                    $errorMsg = "Ошибка при регистрации: " . $e->getMessage();
                }
            }
        }
    }
    
    require_once '../views/auth/register.php';
}

}