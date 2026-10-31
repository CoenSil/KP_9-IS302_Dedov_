<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">
      <h1>Регистрация</h1>
      <p class="lede">Выберите, что вы подключаете. Вы станете администратором и сможете приглашать людей.</p>

      <?php if (!empty($errors)): ?>
        <div class="error" role="alert">
          <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>index.php?route=auth/register">
        <div class="type-switch" role="radiogroup" aria-label="Тип организации">
          <label>
            <input type="radio" name="type" value="company" <?= $v['type'] !== 'school' ? 'checked' : '' ?>>
            <span>Компания<small>Учёт рабочего времени сотрудников</small></span>
          </label>
          <label>
            <input type="radio" name="type" value="school" <?= $v['type'] === 'school' ? 'checked' : '' ?>>
            <span>Учебное заведение<small>Посещаемость студентов и учеников</small></span>
          </label>
        </div>
        <div class="field">
          <label for="name">Название организации</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($v['name']) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="code">Код организации для входа (латиница, цифры, дефис)</label>
          <input type="text" id="code" name="code" value="<?= htmlspecialchars($v['code']) ?>"
                 pattern="[a-zA-Z0-9][a-zA-Z0-9\-]{1,63}" autocapitalize="none" spellcheck="false" required>
        </div>
        <div class="field">
          <label for="admin_name">Ваше ФИО</label>
          <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($v['admin_name']) ?>" required>
        </div>
        <div class="field">
          <label for="admin_email">Email</label>
          <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($v['admin_email']) ?>" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
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
        <button type="submit">Зарегистрироваться</button>
      </form>
      <p class="lede" style="margin-top:18px;">Уже зарегистрированы? <a href="<?= BASE_URL ?>index.php?route=auth/login">Войти</a><br>Вы студент? <a href="<?= BASE_URL ?>index.php?route=auth/register-student">Присоединиться по инвайт-коду</a></p>
    </div>
  </section>
</div>
