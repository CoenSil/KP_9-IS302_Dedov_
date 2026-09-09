<?php
session_start();

$config = require '../../../db.php'; 
$host = $config['host'];
$db = $config['db'];
$user = $config['user'];
$password = $config['password'];
$charset = $config['charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (\PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($email) || empty($phone) || empty($password)) {
            $errorMsg = "Все поля обязательны для заполнения";
        } else {
            $p_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, password_hash, email, phone, role) 
                    VALUES (:username, :password_hash, :email, :phone, 'client')";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $p_hash,
                ':email' => $email,
                ':phone' => $phone
            ]);
            
            $successMsg = "Регистрация прошла успешно!";
            $_POST = [];
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $errorMsg = "Пользователь с таким именем или email уже существует.";
        } else {
            $errorMsg = "Ошибка при регистрации: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Регистрация</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errorMsg)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($successMsg)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Имя пользователя</label>
                            <input type="text" name="username" class="form-control" required 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="tel" name="phone" class="form-control" 
                                   pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" 
                                   placeholder="123-456-7890"
                                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                            <small class="text-muted">Формат: 123-456-7890</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Пароль</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Зарегистрироваться</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php">Уже есть аккаунт? Войти</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>