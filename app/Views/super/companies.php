<h1>Компании</h1>
<p class="lede">Каждая компания работает в изолированном пространстве: свои подразделения, должности, сотрудники, администраторы и табель.</p>

<?php if (!empty($flash)): ?>
  <div class="banner banner--ok" role="status"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<p>
  <a class="btn" href="<?= BASE_URL ?>index.php?route=super/companies/create">Добавить компанию</a>
  <a class="btn" href="<?= BASE_URL ?>index.php?route=super/leads">Заявки с сайта</a>
</p>

<div class="card" style="overflow-x:auto;">
  <table>
    <thead>
      <tr>
        <th>Название</th><th>Тип</th><th>Код входа</th><th>Людей</th>
        <th>Тариф</th><th>Доступ</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($companies as $c): ?>
      <?php
        $st = \App\Models\Subscription::state($c);
        $statusTone = match ($st['status']) {
            'active', 'legacy' => 'banner--ok',
            'trial'            => 'banner--info',
            default            => 'banner--warn',
        };
      ?>
      <tr>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= ($c['type'] ?? 'company') === 'school' ? 'Учебное заведение' : 'Компания' ?></td>
        <td><code><?= htmlspecialchars($c['code']) ?></code></td>
        <td><?= (int) $c['employees_count'] ?></td>
        <td>
          <?= htmlspecialchars(\App\Models\Subscription::planLabel($c)) ?>
          <div class="card__hint" style="margin:2px 0 0;"><?= htmlspecialchars($st['label']) ?><?= $st['until'] ? ' · до ' . htmlspecialchars($st['until']) : '' ?></div>
        </td>
        <td><span class="banner <?= $statusTone ?>" style="margin:0;padding:4px 10px;font-size:12.5px;"><?= (int) $c['is_active'] === 1 ? 'Включена' : 'Отключена' ?></span></td>
        <td>
          <details>
            <summary class="btn" style="display:inline-block;cursor:pointer;">Тариф</summary>
            <form method="post" action="<?= BASE_URL ?>index.php?route=super/companies/plan" class="stack" style="margin-top:10px;min-width:230px;">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <div class="field">
                <label>План</label>
                <select name="plan">
                  <?php foreach (\App\Models\Subscription::PLANS as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= ($c['plan'] ?? 'trial') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label>Оплачено до</label>
                <input type="date" name="paid_until" value="<?= htmlspecialchars(substr((string) ($c['paid_until'] ?? ''), 0, 10)) ?>">
              </div>
              <div class="field">
                <label>Пробный до</label>
                <input type="date" name="trial_ends_at" value="<?= htmlspecialchars(substr((string) ($c['trial_ends_at'] ?? ''), 0, 10)) ?>">
              </div>
              <div class="field">
                <label>Лимит людей (0 — без лимита)</label>
                <input type="number" name="max_employees" min="0" value="<?= (int) ($c['max_employees'] ?? 0) ?>">
              </div>
              <div class="field">
                <label>Лимит студентов (0 — без лимита)</label>
                <input type="number" name="max_students" min="0" value="<?= (int) ($c['max_students'] ?? 0) ?>">
              </div>
              <button type="submit" class="btn">Сохранить</button>
            </form>
          </details>

          <form method="post" action="<?= BASE_URL ?>index.php?route=super/companies/toggle"
                onsubmit="return confirm('<?= (int) $c['is_active'] === 1 ? 'Отключить компанию? Все её пользователи потеряют доступ.' : 'Включить компанию?' ?>');"
                style="margin-top:8px;">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="btn"><?= (int) $c['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($companies)): ?>
      <tr><td colspan="7">Компаний пока нет.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="card__hint">
  Пробный период выдаётся автоматически на 14 дней при регистрации. Оплата — по счёту;
  после поступления денег укажите дату в поле «Оплачено до».
</p>
