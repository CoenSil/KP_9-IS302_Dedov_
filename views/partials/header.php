<?php

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">Сайт</a>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if (!empty($user)): ?>
                <span class="text-light me-2">
                    Вы авторизованы как <strong><?= htmlspecialchars($user['username']) ?></strong>
                </span>
                <a href="/profile" class="btn btn-outline-light btn-sm">Профиль</a>
                <a href="/logout" class="btn btn-outline-danger btn-sm">Выйти</a>
            <?php else: ?>
                <span class="text-light me-2">Вы не авторизованы</span>
                <a href="/login" class="btn btn-outline-light btn-sm">Войти</a>
                <a href="/register" class="btn btn-success btn-sm">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
