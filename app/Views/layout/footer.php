</main>
<?php if (!empty($_SESSION['employee_id']) && empty($_SESSION['super_id']) && ($_SESSION['role'] ?? '') !== 'admin'): ?>
<?php
$verifyChallenge = $_SESSION['verify'] ?? null;
if (is_array($verifyChallenge)) {
    $verifyChallenge['server_now'] = time();
}
$verifyMode = ($_SESSION['company_type'] ?? 'company') === 'school' ? 'mark' : 'logout';
?>
<div class="verify" id="verifyModal" hidden>
  <div class="verify__backdrop"></div>
  <div class="verify__dialog" role="dialog" aria-modal="true" aria-labelledby="verifyTitle">
    <p class="verify__eyebrow">Проверка присутствия</p>
    <h2 class="verify__title" id="verifyTitle">Нажмите число <span class="verify__code" id="verifyCode">--</span></h2>
    <p class="verify__hint" id="verifyHint">Выберите правильное число из трёх вариантов — на это есть 5 минут.</p>
    <div class="verify__options" id="verifyOptions"></div>
    <p class="verify__error" id="verifyError" role="alert"></p>
    <div class="verify__footer">
      <span class="verify__timer" id="verifyTimer">05:00</span>
      <span class="verify__mode" id="verifyMode">
        <?= $verifyMode === 'mark'
            ? 'Если не ответить, в журнале появится отметка о сворачивании приложения.'
            : 'Если не ответить, смена завершится автоматически.' ?>
      </span>
    </div>
  </div>
</div>
<script>
window.OPRES_VERIFY = {
  baseUrl: <?= json_encode(BASE_URL, JSON_UNESCAPED_UNICODE) ?>,
  challenge: <?= json_encode($verifyChallenge, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  mode: <?= json_encode($verifyMode) ?>,
  challengeUrl: <?= json_encode(BASE_URL . 'index.php?route=auth/verify/challenge') ?>,
  answerUrl: <?= json_encode(BASE_URL . 'index.php?route=auth/verify') ?>,
  expireUrl: <?= json_encode(BASE_URL . 'index.php?route=auth/verify/expire') ?>,
  loginUrl: <?= json_encode(BASE_URL . 'index.php?route=auth/login') ?>,
  pollMs: 45000
};
</script>
<?php endif; ?>
<?php
$jsPath = dirname(__DIR__, 3) . '/public_html/assets/js/app.js';
$jsVersion = @filemtime($jsPath) ?: time();
?>
<script src="<?= BASE_URL ?>assets/js/app.js?v=<?= $jsVersion ?>" defer></script>
</body>
</html>
