<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$statusClass = match ($state['status']) {
    'active', 'legacy' => 'banner--ok',
    'trial'            => 'banner--info',
    default            => 'banner--warn',
};
?>
<div class="pagehead">
  <div>
    <h1>Подписка</h1>
    <p class="pagehead__sub">Тариф, срок действия и лимиты вашей организации.</p>
  </div>
  <a class="btn" href="<?= BASE_URL ?>index.php?route=<?= htmlspecialchars($homeRoute ?? 'admin/dashboard') ?>">На главную</a>
</div>

<div class="card stack">
  <div class="banner <?= $statusClass ?>" role="status">
    <strong><?= $h($state['label']) ?></strong>
    <?php if (!empty($state['until'])): ?>
      · до <?= $h($state['until']) ?>
    <?php endif; ?>
    <?php if ($state['status'] === 'trial' && $state['days_left'] !== null): ?>
      · осталось <?= (int) $state['days_left'] ?> дн.
    <?php endif; ?>
  </div>

  <table>
    <tbody>
      <tr><th style="text-align:left; width:220px;">Организация</th><td><?= $h($company['name'] ?? '') ?></td></tr>
      <tr><th style="text-align:left;">Код входа</th><td><code><?= $h($company['code'] ?? '') ?></code></td></tr>
      <tr><th style="text-align:left;">Тариф</th><td><?= $h($planLabel) ?></td></tr>
      <tr><th style="text-align:left;">Сотрудники</th><td><?= (int) $employeesCount ?> из <?= $limitEmployees > 0 ? (int) $limitEmployees : '∞' ?></td></tr>
      <tr><th style="text-align:left;">Студенты</th><td>до <?= $limitStudents > 0 ? (int) $limitStudents : '∞' ?></td></tr>
    </tbody>
  </table>

  <?php if ($state['status'] === 'expired'): ?>
    <p class="card__hint">
      Доступ к разделам приостановлен. Чтобы продлить подписку, напишите на
      <a href="mailto:<?= $h(defined('LEGAL_EMAIL') ? LEGAL_EMAIL : 'hello@opressence.ru') ?>"><?= $h(defined('LEGAL_EMAIL') ? LEGAL_EMAIL : 'hello@opressence.ru') ?></a>
      и укажите код организации. Счёт выставим в течение рабочего дня.
    </p>
  <?php else: ?>
    <p class="card__hint">
      Оплата — по счёту, без автосписаний. Продление и изменение тарифа оформляет
      администратор платформы. Данные при смене тарифа сохраняются.
    </p>
  <?php endif; ?>
</div>
