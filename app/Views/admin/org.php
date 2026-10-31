<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$dayMask = fn (int $mask) => implode(', ', array_values(array_filter(
    $days,
    fn ($label, $day) => ($mask & (1 << ($day - 1))) !== 0,
    ARRAY_FILTER_USE_BOTH
)));
$moveStatus = [
    'moving'  => ['в пути', 'warn'],
    'arrived' => ['на месте', 'ok'],
    'overdue' => ['просрочен', 'bad'],
];
?>
<div class="pagehead">
  <div>
    <h1>Организация</h1>
    <p class="pagehead__sub">Перерывы, рабочие места и сообщения о форс-мажоре.</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="banner <?= ($flash['type'] ?? 'ok') === 'error' ? 'error' : 'banner--ok' ?>" role="status">
    <?= $h($flash['message'] ?? '') ?>
  </div>
<?php endif; ?>

<nav class="tabs" data-tabs aria-label="Настройки организации">
  <button type="button" class="tabs__btn is-active" data-tab="breaks" aria-selected="true">Перерывы</button>
  <button type="button" class="tabs__btn" data-tab="workplaces" aria-selected="false">Рабочие места</button>
  <button type="button" class="tabs__btn" data-tab="force" aria-selected="false">Форс-мажор</button>
</nav>

<div class="tab-panel" data-tab-panel="breaks">
  <div class="card">
    <h2>Окна регламентированных перерывов</h2>
    <p class="card__hint">
      Сотрудник может взять перерыв только внутри своего окна. Неиспользованное время окна сгорает.
      Не больше <?= (int) $breakMax ?> перерывов и <?= (int) $breakCap ?> минут суммарно в день.
    </p>

    <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/break-schedule">
      <input type="hidden" name="action" value="create">
      <div class="stack">
        <div class="field">
          <label for="breakLabel">Название</label>
          <input type="text" id="breakLabel" name="label" maxlength="64" placeholder="Например, утренний" required>
        </div>
        <div class="field">
          <label for="breakStart">Начало окна</label>
          <input type="time" id="breakStart" name="start" required>
        </div>
        <div class="field">
          <label for="breakEnd">Конец окна</label>
          <input type="time" id="breakEnd" name="end" required>
        </div>
        <div class="field">
          <label for="breakMinutes">Минут</label>
          <input type="number" id="breakMinutes" name="minutes" min="1" max="30" value="10" required>
        </div>
      </div>
      <fieldset class="groups-pick">
        <legend>Дни недели</legend>
        <?php foreach ($days as $day => $label): ?>
          <label class="check">
            <input type="checkbox" name="days[]" value="<?= (int) $day ?>" <?= $day <= 5 ? 'checked' : '' ?>>
            <?= $h($label) ?>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <button type="submit" style="margin-top:14px;">Добавить окно</button>
    </form>
  </div>

  <div class="card card--flush">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Название</th><th>Окно</th><th>Минут</th><th>Дни</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (empty($schedules)): ?>
          <tr><td colspan="6"><div class="empty-state">Окон пока нет. Пока администратор не задаст окна, перерывы сотрудникам недоступны.</div></td></tr>
        <?php else: ?>
          <?php foreach ($schedules as $s): ?>
            <tr>
              <td><?= $h($s['label']) ?></td>
              <td><?= $h(substr($s['start_time'], 0, 5)) ?>–<?= $h(substr($s['end_time'], 0, 5)) ?></td>
              <td><?= (int) $s['max_minutes'] ?></td>
              <td><?= $h($dayMask((int) $s['days_mask'])) ?></td>
              <td><span class="tag tag--<?= (int) $s['is_active'] === 1 ? 'ok' : 'idle' ?>"><?= (int) $s['is_active'] === 1 ? 'включено' : 'выключено' ?></span></td>
              <td>
                <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/break-schedule" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <input type="hidden" name="active" value="<?= (int) $s['is_active'] === 1 ? '0' : '1' ?>">
                  <button type="submit" class="btn-ghost btn-sm"><?= (int) $s['is_active'] === 1 ? 'Выключить' : 'Включить' ?></button>
                </form>
                <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/break-schedule" style="display:inline;" onsubmit="return confirm('Удалить окно перерыва?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button type="submit" class="btn-danger-ghost btn-sm">Удалить</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="tab-panel" data-tab-panel="workplaces" hidden>
  <div class="card">
    <h2>Рабочие места</h2>
    <p class="card__hint">Сотрудник сам отмечает, куда переместился. Переход нужно подтвердить кнопкой «Я на месте» за отведённое время — иначе администратор увидит отметку о просроченном переходе.</p>
    <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/workplace" class="stack">
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label for="workplaceName">Название места</label>
        <input type="text" id="workplaceName" name="name" maxlength="100" placeholder="Например, склад №2" required>
      </div>
      <button type="submit">Добавить место</button>
    </form>
  </div>

  <div class="card">
    <h2>Время на переход</h2>
    <p class="card__hint">
      Сколько времени даётся сотруднику, чтобы дойти до нового рабочего места и подтвердить прибытие.
      Сейчас: <strong><?= (int) round(((int) $moveSeconds) / 60) ?> мин</strong>.
      Если настройка не задана, используется общее значение <?= (int) round(((int) $moveDefault) / 60) ?> мин.
    </p>
    <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/move-time" class="stack">
      <div class="field">
        <label for="moveMinutes">Минут на переход</label>
        <input type="number" id="moveMinutes" name="minutes" min="1" max="60"
               value="<?= (int) round(((int) $moveSeconds) / 60) ?>" required>
      </div>
      <button type="submit">Сохранить время</button>
    </form>
  </div>

  <div class="card card--flush">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Название</th><th>Статус</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($workplaces)): ?>
          <tr><td colspan="3"><div class="empty-state">Рабочих мест пока нет.</div></td></tr>
        <?php else: ?>
          <?php foreach ($workplaces as $wp): ?>
            <tr>
              <td><?= $h($wp['name']) ?></td>
              <td><span class="tag tag--<?= (int) $wp['is_active'] === 1 ? 'ok' : 'idle' ?>"><?= (int) $wp['is_active'] === 1 ? 'активно' : 'отключено' ?></span></td>
              <td>
                <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/workplace" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $wp['id'] ?>">
                  <input type="hidden" name="active" value="<?= (int) $wp['is_active'] === 1 ? '0' : '1' ?>">
                  <button type="submit" class="btn-ghost btn-sm"><?= (int) $wp['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card card--flush">
    <h2 style="padding:16px 16px 0;">Переходы за сегодня</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Время</th><th>Сотрудник</th><th>Переход</th><th>Подтвердить до</th><th>Статус</th></tr></thead>
        <tbody>
        <?php if (empty($recentMoves)): ?>
          <tr><td colspan="5"><div class="empty-state">Переходов сегодня не было.</div></td></tr>
        <?php else: ?>
          <?php foreach ($recentMoves as $m): ?>
            <?php $st = $moveStatus[$m['status']] ?? [$m['status'], 'idle']; ?>
            <tr>
              <td><?= $h(date('H:i', strtotime($m['created_at']))) ?></td>
              <td><?= $h($m['full_name']) ?></td>
              <td><?= $h($m['from_name'] ?: '—') ?> → <?= $h($m['to_name'] ?: '—') ?></td>
              <td><?= $m['arrive_by'] ? $h(date('H:i', strtotime($m['arrive_by']))) : '—' ?></td>
              <td>
                <span class="tag tag--<?= $h($st[1]) ?>"><?= $h($st[0]) ?></span>
                <?php if (!empty($m['arrived_at'])): ?>
                  <span class="muted">в <?= $h(date('H:i', strtotime($m['arrived_at']))) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="tab-panel" data-tab-panel="force" hidden>
  <div class="card">
    <h2>Открытые сообщения</h2>
    <p class="card__hint">Сотрудник указал причину и комментарий. После разбора нажмите «Принять к сведению».</p>
    <?php if (empty($forceOpen)): ?>
      <div class="empty-state">Открытых сообщений нет.</div>
    <?php else: ?>
      <div class="notice-list">
        <?php foreach ($forceOpen as $f): ?>
          <div class="notice notice--attention">
            <div class="notice__title"><?= $h($f['full_name']) ?> — <?= $h(\App\Models\ForceMajeure::label($f['reason'])) ?></div>
            <div class="notice__meta"><?= $h(date('d.m.Y H:i', strtotime($f['created_at']))) ?> · <?= $h($f['position_name'] ?? '') ?></div>
            <p><?= $h($f['comment']) ?></p>
            <div class="notice__actions">
              <form method="post" action="<?= BASE_URL ?>index.php?route=admin/org/force-ack">
                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                <button type="submit" class="btn-ghost btn-sm">Принять к сведению</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card card--flush">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Дата</th><th>Сотрудник</th><th>Причина</th><th>Комментарий</th><th>Статус</th></tr></thead>
        <tbody>
        <?php if (empty($forceHistory)): ?>
          <tr><td colspan="5"><div class="empty-state">История пока пуста.</div></td></tr>
        <?php else: ?>
          <?php foreach ($forceHistory as $f): ?>
            <tr>
              <td><?= $h(date('d.m.Y H:i', strtotime($f['created_at']))) ?></td>
              <td><?= $h($f['full_name']) ?></td>
              <td><?= $h(\App\Models\ForceMajeure::label($f['reason'])) ?></td>
              <td><?= $h($f['comment']) ?></td>
              <td><span class="tag tag--<?= $f['status'] === 'open' ? 'warn' : 'ok' ?>"><?= $f['status'] === 'open' ? 'открыто' : 'принято' ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
