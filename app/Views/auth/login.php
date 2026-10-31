<div class="auth">
  <aside class="auth__aside">
    <span class="brand">
      <svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="#2447C9"/>
        <path d="M7 21h18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <path d="M9 21v-4M13 21v-6M17 21v-4M21 21v-6" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".55"/>
        <path d="M16 8v13" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
      </svg>
      <span>Opres<span class="brand__sub">Sence</span></span>
    </span>
    <div>
      <h2>Кто на месте — видно сразу</h2>
      <p>Статусы, опоздания и отработанные часы обновляются сами, табель Т-13 выгружается в Excel.</p>
    </div>
    <svg class="auth__ruler" viewBox="0 0 480 96" role="img" aria-label="Шкала рабочего дня: плановое окно, присутствие и текущий момент">
  <rect x="60" y="14" width="324" height="22" rx="5" fill="#fff" fill-opacity=".14" stroke="#fff" stroke-opacity=".35"/>
  <rect x="67.19999999999997" y="21" width="151.20000000000005" height="8" rx="3" fill="#3DBE8B"/>
  <line x1="218.4" y1="6" x2="218.4" y2="64" stroke="#fff" stroke-width="2"/>
  <circle cx="218.4" cy="6" r="4" fill="#fff"/>
  <line x1="24" y1="52" x2="24" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="60" y1="44" x2="60" y2="64" stroke="#8B97AB" stroke-width="2"/><line x1="96" y1="52" x2="96" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="132" y1="52" x2="132" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="168" y1="44" x2="168" y2="64" stroke="#8B97AB" stroke-width="2"/><line x1="204" y1="52" x2="204" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="240" y1="52" x2="240" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="276" y1="44" x2="276" y2="64" stroke="#8B97AB" stroke-width="2"/><line x1="312" y1="52" x2="312" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="348" y1="52" x2="348" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="384" y1="44" x2="384" y2="64" stroke="#8B97AB" stroke-width="2"/><line x1="420" y1="52" x2="420" y2="64" stroke="#8B97AB" stroke-width="1"/><line x1="456" y1="52" x2="456" y2="64" stroke="#8B97AB" stroke-width="1"/><text x="60" y="84" fill="#AAB4C5" font-size="12" text-anchor="middle" font-family="Golos Text, system-ui, sans-serif">09:00</text><text x="168" y="84" fill="#AAB4C5" font-size="12" text-anchor="middle" font-family="Golos Text, system-ui, sans-serif">12:00</text><text x="276" y="84" fill="#AAB4C5" font-size="12" text-anchor="middle" font-family="Golos Text, system-ui, sans-serif">15:00</text><text x="384" y="84" fill="#AAB4C5" font-size="12" text-anchor="middle" font-family="Golos Text, system-ui, sans-serif">18:00</text>
</svg>
  </aside>

  <section class="auth__panel">
    <div class="auth__form">
      <span class="brand auth__mobile-brand">Opres<span class="brand__sub">Sence</span></span>
      <h1>Вход в систему</h1>
      <p class="lede">Укажите код вашей организации, почту и пароль.</p>

      <?php if (!empty($error)): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>index.php?route=auth/login">
        <div class="field">
          <label for="company">Код организации (компании или учебного заведения)</label>
          <input type="text" id="company" name="company" value="<?= htmlspecialchars($companyCode ?? '') ?>"
                 autocomplete="organization" autocapitalize="none" spellcheck="false" required
                 <?= empty($companyCode) ? 'autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required <?= !empty($companyCode) ? 'autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <div class="pw">
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="button" class="pw__toggle" id="pwToggle" aria-controls="password">Показать</button>
          </div>
        </div>
        <button type="submit">Войти</button>
      </form>
      <?php if (!defined('REGISTRATION_ENABLED') || REGISTRATION_ENABLED): ?>
        <p class="lede" style="margin-top:18px;">Нет компании в системе? <a href="<?= BASE_URL ?>index.php?route=auth/register">Зарегистрировать компанию или учебное заведение</a><br>Вы студент? <a href="<?= BASE_URL ?>index.php?route=auth/register-student">Присоединиться по инвайт-коду</a></p>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
document.getElementById('pwToggle').addEventListener('click', function () {
  const input = document.getElementById('password');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  this.textContent = show ? 'Скрыть' : 'Показать';
});
</script>
