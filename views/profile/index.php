<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль пользователя</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/../partials/header.php'; ?>

<div class="container mb-5">
    <h2 class="mb-4">Профиль пользователя</h2>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Данные аккаунта</div>
        <div class="card-body">
            <form method="POST" action="/profile/edit">
                <div class="mb-3">
                    <label class="form-label">Никнейм</label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
