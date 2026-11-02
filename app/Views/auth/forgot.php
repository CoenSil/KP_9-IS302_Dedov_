<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$loginUrl = BASE_URL . 'index.php?route=auth/login';
?>
<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">

      <h1>Восстановление пароля</h1>

      <?php if (!empty($sent)): ?>
        <p class="lede">Если профиль с таким email существует, мы отправили письмо со ссылкой для смены пароля. Ссылка действует один час.</p>
        <div class="banner banner--ok" role="status">Проверьте почту и папку «Спам».</div>
        <p class="switchline"><a href="<?= $loginUrl ?>">Вернуться ко входу</a></p>
      <?php else: ?>
        <p class="lede">Укажите код организации и email, под которым вы входите. Мы пришлём ссылку для нового пароля.</p>

        <?php if (!empty($error)): ?>
          <div class="error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>index.php?route=auth/forgot">
          <div class="field">
            <label for="company">Код организации</label>
            <input type="text" id="company" name="company" value="<?= $h($companyCode ?? '') ?>"
                   autocapitalize="none" spellcheck="false" required autofocus>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" maxlength="254"
                   inputmode="email" autocapitalize="none" spellcheck="false" required>
          </div>
          <button type="submit">Отправить ссылку</button>
        </form>

        <p class="switchline"><a href="<?= $loginUrl ?>">Вернуться ко входу</a></p>
      <?php endif; ?>

    </div>
  </section>
</div>
