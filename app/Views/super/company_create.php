<div class="form-narrow">
<h1>Новая компания</h1>
<p class="lede">Будут созданы компания, подразделение «Администрация», должность «Администратор» и первый администратор компании.</p>

<?php if (!empty($created)): ?>
  <div class="banner banner--ok" role="status">
    <h2 style="color: var(--accent);">Компания «<?= htmlspecialchars($created['name']) ?>» создана</h2>
    <p style="color: var(--ink-soft);">
      Код для входа: <strong><?= htmlspecialchars($created['code']) ?></strong><br>
      Администратор: <?= htmlspecialchars($created['email']) ?><br>
      Ссылка для входа: <code><?= htmlspecialchars(BASE_URL . 'index.php?route=auth/login&c=' . $created['code']) ?></code>
    </p>
    <?php if ($created['password']): ?>
      <p style="color: var(--ink-soft);">Временный пароль (показывается один раз):</p>
      <p class="clock" style="font-size: 26px;"><?= htmlspecialchars($created['password']) ?></p>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>index.php?route=super/companies" class="btn" style="margin-top:6px;">К списку компаний</a>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= BASE_URL ?>index.php?route=super/companies/create">
    <div class="type-switch" role="radiogroup" aria-label="Тип организации">
      <label><input type="radio" name="type" value="company" <?= ($v['type'] ?? 'company') !== 'school' ? 'checked' : '' ?>><span>Компания<small>Учёт сотрудников</small></span></label>
      <label><input type="radio" name="type" value="school" <?= ($v['type'] ?? '') === 'school' ? 'checked' : '' ?>><span>Учебное заведение<small>Посещаемость студентов</small></span></label>
    </div>
    <div class="field">
      <label for="name">Название организации</label>
      <input type="text" id="name" name="name" value="<?= htmlspecialchars($v['name']) ?>" required>
    </div>
    <div class="field">
      <label for="code">Код компании (латиница, цифры, дефис)</label>
      <input type="text" id="code" name="code" value="<?= htmlspecialchars($v['code']) ?>" pattern="[a-zA-Z0-9][a-zA-Z0-9\-]{1,63}" required>
    </div>
    <div class="field">
      <label for="admin_name">ФИО администратора</label>
      <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($v['admin_name']) ?>" required>
    </div>
    <div class="field">
      <label for="admin_email">Email администратора</label>
      <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($v['admin_email']) ?>" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
    </div>
    <div class="field">
      <label for="password">Пароль (необязательно — можно сгенерировать)</label>
      <input type="text" id="password" name="password" value="<?= htmlspecialchars($v['password']) ?>" placeholder="Оставьте пустым, чтобы сгенерировать автоматически">
    </div>
    <button type="submit">Создать компанию</button>
  </form>
</div>
</div>
