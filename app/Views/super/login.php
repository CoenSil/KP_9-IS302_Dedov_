<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">
      <h1>Вход для владельца платформы</h1>
      <p class="lede">Управление компаниями. Сотрудникам и администраторам компаний — на <a href="<?= BASE_URL ?>index.php?route=auth/login">обычную страницу входа</a>.</p>

      <?php if (!empty($error)): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>index.php?route=super/login">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required autofocus>
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Войти</button>
      </form>
    </div>
  </section>
</div>
