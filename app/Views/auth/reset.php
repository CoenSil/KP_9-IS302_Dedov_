<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$loginUrl = BASE_URL . 'index.php?route=auth/login';
?>
<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">

      <h1>Новый пароль</h1>

      <?php if (!empty($done)): ?>
        <p class="lede">Пароль сохранён. Теперь можно войти с новым паролем.</p>
        <div class="banner banner--ok" role="status">Готово.</div>
        <p class="switchline"><a href="<?= $loginUrl ?>">Перейти ко входу</a></p>
      <?php elseif (empty($token) && empty($error)): ?>
        <p class="lede">Ссылка неполная. Запросите восстановление пароля ещё раз.</p>
        <p class="switchline"><a href="<?= BASE_URL ?>index.php?route=auth/forgot">Запросить ссылку</a></p>
      <?php else: ?>
        <?php if (!empty($email)): ?>
          <p class="lede">Профиль: <?= $h($email) ?></p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($token)): ?>
          <form method="post" action="<?= BASE_URL ?>index.php?route=auth/reset">
            <input type="hidden" name="token" value="<?= $h($token) ?>">
            <div class="field">
              <label for="password">Новый пароль (не короче 8 символов)</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
            </div>
            <div class="field">
              <label for="password2">Повторите пароль</label>
              <input type="password" id="password2" name="password2" autocomplete="new-password" required>
            </div>
            <button type="submit">Сохранить пароль</button>
          </form>
        <?php endif; ?>

        <p class="switchline">
          <a href="<?= BASE_URL ?>index.php?route=auth/forgot">Запросить новую ссылку</a>
        </p>
      <?php endif; ?>

    </div>
  </section>
</div>
