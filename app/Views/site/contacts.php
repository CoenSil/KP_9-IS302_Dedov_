<?php $b = BASE_URL; $h = fn ($s) => htmlspecialchars((string) $s); ?>
<section class="lp-section">
  <div class="lp-wrap lp-contacts">
    <div>
      <h1>Связаться с нами</h1>
      <p class="lp-section__lead">Ответим в рабочий день. Если хотите сразу посмотреть сервис — напишите об этом в сообщении, назначим демонстрацию.</p>

      <ul class="lp-contacts__list">
        <li><span>Почта</span><a href="mailto:<?= $h($contacts['email']) ?>"><?= $h($contacts['email']) ?></a></li>
        <?php if (!empty($contacts['phone'])): ?>
          <li><span>Телефон</span><a href="tel:<?= $h(preg_replace('/[^0-9+]/', '', $contacts['phone'])) ?>"><?= $h($contacts['phone']) ?></a></li>
        <?php endif; ?>
        <li><span>Реквизиты</span><?= $h($contacts['name']) ?><?= !empty($contacts['inn']) ? ', ИНН ' . $h($contacts['inn']) : '' ?></li>
      </ul>
    </div>

    <div class="lp-form-card">
      <?php if (!empty($sent)): ?>
        <div class="lp-alert lp-alert--ok" role="status">
          Заявка отправлена. Мы свяжемся с вами в ближайший рабочий день.
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="lp-alert lp-alert--error" role="alert"><?= $h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= $b ?>index.php?route=site/lead" class="lp-form">
        <label>
          <span>Как к вам обращаться</span>
          <input type="text" name="name" maxlength="190" required>
        </label>
        <label>
          <span>Школа или учебный центр</span>
          <input type="text" name="school" maxlength="190" placeholder="Название">
        </label>
        <label>
          <span>Email или телефон</span>
          <input type="text" name="contact" maxlength="190" required>
        </label>
        <label>
          <span>Сообщение</span>
          <textarea name="message" rows="4" maxlength="2000" placeholder="Сколько преподавателей и студентов, что важно учесть"></textarea>
        </label>
        <div class="lp-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="lp-check">
          <input type="checkbox" name="consent" value="1" required>
          <span>Согласен с <a href="<?= $b ?>index.php?route=site/privacy" target="_blank" rel="noopener">политикой обработки персональных данных</a></span>
        </label>
        <button type="submit" class="lp-btn">Отправить заявку</button>
      </form>
    </div>
  </div>
</section>
