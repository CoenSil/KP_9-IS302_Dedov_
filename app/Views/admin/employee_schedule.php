<div class="form-narrow">
<h1>График сотрудника — <?= htmlspecialchars($employee['full_name']) ?></h1>
<p class="lede">
  Должность «<?= htmlspecialchars($employee['position_name']) ?>» задаёт время по умолчанию.
  Если заполнить поля ниже — для этого сотрудника будет действовать своё время.
</p>

<?php if ($success): ?>
  <div class="banner banner--ok" role="status"><strong>График сохранён</strong></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= BASE_URL ?>index.php?route=admin/employees/schedule&id=<?= (int) $employee['id'] ?>">
    <div class="field">
      <label for="custom_planned_login">Индивидуальное время прихода</label>
      <input type="time" id="custom_planned_login" name="custom_planned_login"
             value="<?= htmlspecialchars(substr((string) $employee['custom_planned_login'], 0, 5)) ?>">
    </div>
    <div class="field">
      <label for="custom_planned_logout">Индивидуальное время ухода</label>
      <input type="time" id="custom_planned_logout" name="custom_planned_logout"
             value="<?= htmlspecialchars(substr((string) $employee['custom_planned_logout'], 0, 5)) ?>">
    </div>
    <p style="color: var(--ink-soft); font-size: 13px; margin: -8px 0 20px;">
      Сейчас действует: <strong><?= $employee['planned_login'] ? substr($employee['planned_login'], 0, 5) : '—' ?></strong>
      – <strong><?= $employee['planned_logout'] ? substr($employee['planned_logout'], 0, 5) : '—' ?></strong>
      <?= ($employee['custom_planned_login'] || $employee['custom_planned_logout']) ? '(индивидуальное)' : '(по должности)' ?>.
      Оставьте оба поля пустыми и сохраните, чтобы вернуться к графику должности.
    </p>
    <button type="submit">Сохранить</button>
  </form>
</div>

<p style="margin-top:20px;"><a href="<?= BASE_URL ?>index.php?route=admin/dashboard">← к списку сотрудников</a></p>
</div>
