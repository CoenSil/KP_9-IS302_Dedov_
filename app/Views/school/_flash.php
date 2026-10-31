<?php if (!empty($flash)): ?>
  <div class="<?= $flash['type'] === 'error' ? 'error' : 'banner banner--ok' ?>" role="status"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>
