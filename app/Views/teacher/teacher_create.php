<?php $h = fn ($s) => htmlspecialchars((string) $s); ?>
<div class="form-narrow">
<h1>Новый преподаватель</h1>
<p class="lede">Преподаватель получает доступ к своим группам и занятиям. Чужие группы он не видит.</p>

<?php if (!empty($created)): ?>
  <div class="banner banner--ok" role="status">
    <h2 style="color: var(--accent);">Преподаватель «<?= $h($created['name']) ?>» добавлен</h2>
    <p style="color: var(--ink-soft);">Вход: код <strong><?= $h($_SESSION['company_code'] ?? '') ?></strong>, email <?= $h($created['email']) ?></p>
    <?php if ($created['password']): ?>
      <p style="color: var(--ink-soft);">Временный пароль (показывается один раз):</p>
      <p class="clock" style="font-size: 26px;"><?= $h($created['password']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error"><?php foreach ($errors as $e): ?><div><?= $h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/teachers/create">
    <div class="field">
      <label for="full_name">ФИО</label>
      <input type="text" id="full_name" name="full_name" value="<?= $h($v['full_name']) ?>" required>
    </div>
    <div class="field">
      <label for="email">Email (для входа)</label>
      <input type="email" id="email" name="email" value="<?= $h($v['email']) ?>" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
    </div>
    <div class="field">
      <label for="password">Пароль (необязательно — можно сгенерировать)</label>
      <input type="text" id="password" name="password" placeholder="Оставьте пустым, чтобы сгенерировать автоматически">
    </div>
    <button type="submit">Добавить преподавателя</button>
  </form>
</div>
</div>
