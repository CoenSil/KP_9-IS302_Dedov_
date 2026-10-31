<div class="form-narrow">
<h1>Новый сотрудник</h1>
<p class="lede">Создание учётной записи. Если пароль не указан — система сгенерирует временный и покажет его один раз.</p>

<?php if (!empty($success)): ?>
  <div class="banner banner--ok" role="status">
    <h2 style="color: var(--accent);">Сотрудник зарегистрирован</h2>
    <?php if ($generatedPassword): ?>
      <p style="color: var(--ink-soft);">
        Временный пароль (сообщите сотруднику, он больше нигде не будет показан):
      </p>
      <p class="clock" style="font-size: 26px;"><?= htmlspecialchars($generatedPassword) ?></p>
    <?php else: ?>
      <p style="color: var(--ink-soft);">Пароль установлен вручную при регистрации.</p>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>index.php?route=admin/employees/create" class="btn" style="margin-top:6px;">Добавить ещё одного</a>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error">
    <?php foreach ($errors as $e): ?>
      <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= BASE_URL ?>index.php?route=admin/employees/create">
    <div class="field">
      <label for="full_name">ФИО</label>
      <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($fullName ?? '') ?>" required>
    </div>

    <div class="field">
      <label for="email">Email (используется для входа)</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
    </div>

    <div class="field">
      <label for="position_id">Должность и подразделение</label>
      <select id="position_id" name="position_id" required >
        <option value="">— выберите —</option>
        <?php foreach ($positions as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ((int) ($positionId ?? 0) === (int) $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['department_name']) ?> — <?= htmlspecialchars($p['name']) ?>
            <?= $p['planned_login'] ? ' (' . substr($p['planned_login'], 0, 5) . '–' . substr($p['planned_logout'], 0, 5) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($positions)): ?>
        <p style="color: var(--danger); font-size: 13px; margin-top:6px;">
          В таблице positions нет ни одной должности с role = 'employee'. Добавьте её перед регистрацией.
        </p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="password">Пароль (необязательно — можно сгенерировать)</label>
      <input type="text" id="password" name="password" placeholder="Оставьте пустым, чтобы сгенерировать автоматически">
    </div>

    <button type="submit">Зарегистрировать сотрудника</button>
  </form>
</div>
</div>
