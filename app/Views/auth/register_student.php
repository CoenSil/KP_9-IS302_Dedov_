<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">
      <h1>Регистрация студента</h1>
      <p class="lede">Введите инвайт-код, который выдал преподаватель, и создайте свой профиль. По нему вы будете отмечаться на занятиях.</p>

      <?php if (!empty($errors)): ?>
        <div class="error" role="alert">
          <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>index.php?route=auth/register-student">
        <div class="field">
          <label for="invite">Инвайт-код группы</label>
          <input type="text" id="invite" name="invite" value="<?= htmlspecialchars(\App\Models\Group::formatCode($v['invite'])) ?>"
                 autocapitalize="characters" spellcheck="false" autocomplete="off" placeholder="XXXX-XXXX" required <?= $v['invite'] === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="full_name">Ваше ФИО</label>
          <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($v['full_name']) ?>" required <?= $v['invite'] !== '' ? 'autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($v['email']) ?>" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
        </div>
        <div class="field">
          <label for="password">Пароль (не короче 6 символов)</label>
          <input type="password" id="password" name="password" autocomplete="new-password" required>
        </div>
        <div class="field">
          <label for="password2">Повторите пароль</label>
          <input type="password" id="password2" name="password2" autocomplete="new-password" required>
        </div>
        <div style="position:absolute; left:-9999px;" aria-hidden="true">
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>
        <button type="submit">Создать профиль</button>
      </form>
      <p class="lede" style="margin-top:18px;">Уже есть профиль? <a href="<?= BASE_URL ?>index.php?route=auth/login">Войти</a></p>
    </div>
  </section>
</div>
