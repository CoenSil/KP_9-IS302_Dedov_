<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$url = function (string $o, string $w, string $a): string {
    $base = BASE_URL . 'index.php?route=';
    if ($a === 'login') {
        return $base . 'auth/login&org=' . $o . '&who=' . $w;
    }
    if ($o === 'school' && $w === 'student') {
        return $base . 'auth/register-student';
    }
    return $base . 'auth/register&org=' . $o;
};
$school  = $org === 'school';
$student = $school && $who === 'student';
$codeLabel = $school ? 'Код учебного заведения' : 'Код компании';
$title = $act === 'login'
    ? ($student ? 'Вход для студента' : ($school ? 'Вход для сотрудников' : 'Вход в компанию'))
    : ($student ? 'Регистрация студента' : ($school ? 'Регистрация учебного заведения' : 'Регистрация компании'));
$lede = $act === 'login'
    ? 'Укажите ' . ($school ? 'код учебного заведения' : 'код компании') . ', почту и пароль.'
    : ($student ? 'Введите инвайт-код вашей группы и создайте профиль. Вы будете закреплены за этой группой.'
        : ($school ? 'Вы станете директором и администратором. Сможете добавлять администраторов, преподавателей, группы и расписание.'
            : 'Вы станете администратором компании и сможете добавлять подразделения, должности и сотрудников.'));
?>
<div class="auth">
  <section class="auth__panel" style="grid-column: 1 / -1;">
    <div class="auth__form">

      <nav class="seg" aria-label="Тип организации">
        <a href="<?= $url('company', 'staff', $act) ?>" <?= !$school ? 'class="is-active" aria-current="page"' : '' ?>>Компания</a>
        <a href="<?= $url('school', $who === 'student' ? 'student' : 'staff', $act) ?>" <?= $school ? 'class="is-active" aria-current="page"' : '' ?>>Учебное заведение</a>
      </nav>

      <?php if ($school): ?>
        <nav class="seg seg--sub" aria-label="Кто вы">
          <a href="<?= $url('school', 'student', $act) ?>" <?= $student ? 'class="is-active" aria-current="page"' : '' ?>>Студент</a>
          <a href="<?= $url('school', 'staff', $act) ?>" <?= !$student ? 'class="is-active" aria-current="page"' : '' ?>>Работник</a>
        </nav>
      <?php endif; ?>

      <h1><?= $h($title) ?></h1>
      <p class="lede"><?= $h($lede) ?></p>

      <?php if (!empty($error)): ?>
        <div class="error" role="alert"><?= $h($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="error" role="alert"><?php foreach ($errors as $e): ?><div><?= $h($e) ?></div><?php endforeach; ?></div>
      <?php endif; ?>

      <?php if ($act === 'login'): ?>
        <form method="post" action="<?= BASE_URL ?>index.php?route=auth/login">
          <input type="hidden" name="org" value="<?= $h($org) ?>">
          <input type="hidden" name="who" value="<?= $h($who) ?>">
          <div class="field">
            <label for="company"><?= $h($codeLabel) ?></label>
            <input type="text" id="company" name="company" value="<?= $h($companyCode) ?>"
                   autocapitalize="none" spellcheck="false" required <?= $companyCode === '' ? 'autofocus' : '' ?>>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required <?= $companyCode !== '' ? 'autofocus' : '' ?>>
          </div>
          <div class="field">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <button type="submit">Войти</button>
        </form>
        <p class="switchline"><a href="<?= BASE_URL ?>index.php?route=auth/forgot">Забыли пароль?</a></p>
        <?php if ($student): ?>
          <p class="switchline">Нет профиля? <a href="<?= $url('school', 'student', 'register') ?>">Регистрация по инвайт-коду</a></p>
        <?php elseif ($registrationOpen): ?>
          <p class="switchline"><a href="<?= $url($org, 'staff', 'register') ?>"><?= $school ? 'Зарегистрировать учебное заведение' : 'Зарегистрировать компанию' ?></a></p>
        <?php endif; ?>

      <?php elseif ($student): ?>
        <form method="post" action="<?= BASE_URL ?>index.php?route=auth/register-student">
          <div class="field">
            <label for="invite">Инвайт-код группы</label>
            <input type="text" id="invite" name="invite" value="<?= $h(\App\Models\Group::formatCode($v['invite'])) ?>"
                   autocapitalize="characters" spellcheck="false" autocomplete="off" placeholder="XXXX-XXXX" required <?= $v['invite'] === '' ? 'autofocus' : '' ?>>
          </div>
          <div class="field">
            <label for="full_name">Ваше ФИО</label>
            <input type="text" id="full_name" name="full_name" value="<?= $h($v['full_name']) ?>" required <?= $v['invite'] !== '' ? 'autofocus' : '' ?>>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= $h($v['email']) ?>" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
          </div>
          <div class="field">
            <label for="password">Пароль (не короче 6 символов)</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label for="password2">Повторите пароль</label>
            <input type="password" id="password2" name="password2" autocomplete="new-password" required>
          </div>
          <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <button type="submit">Создать профиль</button>
        </form>
        <p class="switchline">Уже есть профиль? <a href="<?= $url('school', 'student', 'login') ?>">Войти</a></p>

      <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>index.php?route=auth/register">
          <input type="hidden" name="org" value="<?= $h($org) ?>">
          <div class="field">
            <label for="name"><?= $school ? 'Название учебного заведения' : 'Название компании' ?></label>
            <input type="text" id="name" name="name" value="<?= $h($v['name']) ?>" required autofocus>
          </div>
          <div class="field">
            <label for="code">Код для входа (латиница, цифры, дефис)</label>
            <input type="text" id="code" name="code" value="<?= $h($v['code']) ?>"
                   pattern="[a-zA-Z0-9][a-zA-Z0-9\-]{1,63}" autocapitalize="none" spellcheck="false" required>
          </div>
          <div class="field">
            <label for="admin_name"><?= $school ? 'ФИО директора' : 'Ваше ФИО' ?></label>
            <input type="text" id="admin_name" name="admin_name" value="<?= $h($v['admin_name']) ?>" required>
          </div>
          <div class="field">
            <label for="admin_email">Email</label>
            <input type="email" id="admin_email" name="admin_email" value="<?= $h($v['admin_email']) ?>" autocomplete="username" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
          </div>
          <div class="field">
            <label for="password">Пароль (не короче 6 символов)</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label for="password2">Повторите пароль</label>
            <input type="password" id="password2" name="password2" autocomplete="new-password" required>
          </div>
          <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <button type="submit">Зарегистрироваться</button>
        </form>
        <p class="switchline">Уже зарегистрированы? <a href="<?= $url($org, 'staff', 'login') ?>">Войти</a></p>
      <?php endif; ?>

    </div>
  </section>
</div>
