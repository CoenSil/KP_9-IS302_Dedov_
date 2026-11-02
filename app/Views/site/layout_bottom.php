</main>

<footer class="lp-footer">
  <div class="lp-wrap lp-footer__inner">
    <div>
      <strong>OpresSence</strong>
      <p>Учёт посещаемости и рабочего времени для онлайн-школ и учебных центров.</p>
    </div>
    <div class="lp-footer__links">
      <a href="<?= BASE_URL ?>index.php?route=site/offer">Публичная оферта</a>
      <a href="<?= BASE_URL ?>index.php?route=site/privacy">Политика обработки персональных данных</a>
      <a href="<?= BASE_URL ?>index.php?route=site/contacts">Контакты</a>
    </div>
    <div class="lp-footer__legal">
      <span>© <?= date('Y') ?> <?= htmlspecialchars($contacts['name'] ?? 'OpresSence') ?></span>
      <?php if (!empty($contacts['inn'])): ?>
        <span>ИНН <?= htmlspecialchars($contacts['inn']) ?></span>
      <?php endif; ?>
      <span><?= htmlspecialchars($contacts['email'] ?? '') ?></span>
    </div>
  </div>
</footer>
</body>
</html>
