<?php
$h = fn ($s) => htmlspecialchars((string) $s);
?>
<div class="card stack" style="max-width:480px;">
  <h1>Смена пароля</h1>
  <p class="lede">Пароль должен быть не короче 8 символов. После смены остальные открытые сеансы продолжат работать до выхода.</p>

  <?php if (!empty($done)): ?>
    <div class="banner banner--ok" role="status">Пароль изменён.</div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="error" role="alert"><?= $h($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>index.php?route=auth/password" class="stack">
    <div class="field">
      <label for="current">Текущий пароль</label>
      <input type="password" id="current" name="current" autocomplete="current-password" required autofocus>
    </div>
    <div class="field">
      <label for="password">Новый пароль (не короче 8 символов)</label>
      <input type="password" id="password" name="password" autocomplete="new-password" required>
    </div>
    <div class="field">
      <label for="password2">Повторите новый пароль</label>
      <input type="password" id="password2" name="password2" autocomplete="new-password" required>
    </div>
    <button type="submit">Сохранить пароль</button>
  </form>
</div>
