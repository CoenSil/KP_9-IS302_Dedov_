<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$kinds = \App\Models\Lesson::KINDS;
$startsAt = strtotime($lesson['scheduled_start']);
?>
<div class="pagehead">
  <div>
    <h1><?= $h($lesson['title']) ?></h1>
    <p class="pagehead__sub"><?= $h($kinds[$lesson['kind']] ?? $lesson['kind']) ?> · ауд. <?= $h($lesson['room']) ?> · <?= $h(date('d.m.Y H:i', $startsAt)) ?>–<?= $h(date('H:i', strtotime($lesson['scheduled_end']))) ?> ·
      <?= $h($lesson['group_names']) ?> · <a href="<?= BASE_URL ?>index.php?route=teacher/dashboard">все занятия</a></p>
  </div>
  <div class="stack">
    <?php if (!empty($lesson['started_at'])): ?>
      <a class="btn btn-ghost" href="<?= BASE_URL ?>index.php?route=teacher/lesson/export&id=<?= (int) $lesson['id'] ?>">Скачать CSV</a>
    <?php endif; ?>
    <?php if ($status === 'open'): ?>
      <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/lesson/close" onsubmit="return confirm('Завершить занятие? Отметиться после этого будет нельзя.');">
        <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
        <button type="submit" class="btn-danger-ghost">Завершить занятие</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="<?= $flash['type'] === 'error' ? 'error' : 'banner banner--ok' ?>" role="status"><?= $h($flash['message']) ?></div>
<?php endif; ?>

<?php if ($status === 'scheduled'): ?>
  <div class="card">
    <h2>Занятие ещё не начато</h2>
    <?php if ($canStart): ?>
      <p class="card__hint">После нажатия появится код занятия. Покажите его студентам: они вводят код в своём профиле.</p>
      <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/lessons/start" style="margin-top:12px;">
        <input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
        <button type="submit">Начать занятие и показать код</button>
      </form>
    <?php else: ?>
      <p class="card__hint">Начать можно за <?= (int) \App\Models\Lesson::START_EARLY_MINUTES ?> минут до начала, с <?= $h(date('H:i', $startsAt - \App\Models\Lesson::START_EARLY_MINUTES * 60)) ?> (<?= $h(date('d.m.Y', $startsAt)) ?>).</p>
    <?php endif; ?>
  </div>
<?php elseif ($status === 'missed'): ?>
  <div class="card"><div class="empty-state">Время занятия прошло, а оно не было начато.</div></div>
<?php else: ?>

<?php if ($status === 'open'): ?>
<div class="card">
  <h2>Код занятия</h2>
  <div class="codebox codebox--lg"><?= $h($lesson['code']) ?></div>
  <p class="card__hint" style="margin-top:12px; text-align:center;">Покажите код на экране или продиктуйте. Войти могут только студенты групп занятия. Осталось: <strong id="left">—</strong></p>
</div>
<?php endif; ?>

<div class="card">
  <div class="stats">
    <div class="fact"><div class="fact__label">Пришли</div><div class="fact__value" id="cPresent">—</div></div>
    <div class="fact"><div class="fact__label">Всего</div><div class="fact__value" id="cTotal">—</div></div>
    <div class="fact"><div class="fact__label">Статус</div><div class="fact__value" id="cOpen"><?= $status === 'open' ? 'Идёт' : 'Завершено' ?></div></div>
  </div>
</div>

<div class="card card--flush">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Студент</th><th>Группа</th><th>Отметка</th><th>Сейчас</th><th>Активность</th><th>Выходов</th><th></th></tr></thead>
      <tbody id="rows"><tr><td colspan="7">Загрузка…</td></tr></tbody>
    </table>
  </div>
</div>

<p class="card__hint">«Активность» — доля времени, когда страница занятия была открыта на экране телефона. «Выходов» — сколько раз студент сворачивал страницу или терял связь. Ручная отметка нужна, если студент пришёл, но не смог ввести код.</p>

<script>
(function () {
  var BASE = '<?= BASE_URL ?>index.php?route=';
  var LESSON_ID = <?= (int) $lesson['id'] ?>;
  var OPEN = <?= $status === 'open' ? 'true' : 'false' ?>;
  var STATUS = {present: 'Пришёл', late: 'Опоздал', manual: 'Отмечен вручную', absent: 'Нет'};
  var STATE = {
    online: ['В сети', 'ok'], break: ['Перерыв', 'ok'], idle: ['Нет касаний', 'warn'], hidden: ['Свернул', 'warn'],
    offline: ['Нет связи', 'bad'], manual: ['Вручную', 'idle'], finished: ['—', 'idle'], absent: ['—', 'idle']
  };

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function mark(studentId, action) {
    var fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('student_id', studentId);
    fd.append('do', action);
    fetch(BASE + 'teacher/lesson/mark', {method: 'POST', body: fd, credentials: 'same-origin'}).then(load);
  }

  function fmtLeft(s) {
    var m = Math.floor(s / 60), sec = s % 60;
    return m + ' мин ' + (sec < 10 ? '0' : '') + sec + ' с';
  }

  function render(d) {
    if (!d || !d.ok) return;
    document.getElementById('cPresent').textContent = d.present;
    document.getElementById('cTotal').textContent = d.total;
    document.getElementById('cOpen').textContent = d.status === 'open' ? 'Идёт' : 'Завершено';
    var left = document.getElementById('left');
    if (left) left.textContent = d.status === 'open' ? fmtLeft(d.left) : 'завершено';
    if (OPEN && d.status !== 'open') { location.reload(); return; }

    var tbody = document.getElementById('rows');
    tbody.textContent = '';
    if (!d.students.length) {
      var tr0 = el('tr'); var td0 = el('td', null, 'В группах занятия пока нет студентов.'); td0.colSpan = 7;
      tr0.appendChild(td0); tbody.appendChild(tr0); return;
    }
    d.students.forEach(function (s) {
      var tr = el('tr');
      var name = el('td', null, s.full_name);
      if (s.shared_device) name.appendChild(el('span', 'badge-custom', 'общее устройство'));
      if (s.mobile) name.appendChild(el('span', 'badge-custom', 'телефон'));
      tr.appendChild(name);
      tr.appendChild(el('td', null, s.group_name));

      var st = el('td');
      st.appendChild(el('span', 'tag tag--' + (s.status === 'absent' ? 'bad' : s.status === 'late' ? 'warn' : 'ok'), STATUS[s.status] || s.status));
      if (s.checked_in_at) st.appendChild(el('div', 'card__hint', s.checked_in_at.substr(11, 5)));
      tr.appendChild(st);

      var info = STATE[s.state] || ['—', 'idle'];
      var now = el('td'); now.appendChild(el('span', 'tag tag--' + info[1], info[0])); tr.appendChild(now);

      var act = el('td');
      if (s.active_percent === null) {
        act.textContent = '—';
      } else {
        var meter = el('div', 'meter'); var bar = el('span'); bar.style.width = s.active_percent + '%';
        meter.appendChild(bar); act.appendChild(meter);
        act.appendChild(el('div', 'card__hint', s.active_percent + '% · ' + Math.round(s.active_seconds / 60) + ' мин'));
      }
      tr.appendChild(act);
      tr.appendChild(el('td', null, s.status === 'absent' ? '—' : String(s.away_count)));

      var actions = el('td');
      if (s.status === 'absent') {
        var b = el('button', 'btn-ghost btn-sm', 'Отметить'); b.type = 'button';
        b.onclick = function () { mark(s.student_id, 'add'); };
        actions.appendChild(b);
      } else {
        var r = el('button', 'btn-danger-ghost btn-sm', 'Снять'); r.type = 'button';
        r.onclick = function () { mark(s.student_id, 'remove'); };
        actions.appendChild(r);
      }
      tr.appendChild(actions);
      tbody.appendChild(tr);
    });
  }

  function load() {
    fetch(BASE + 'teacher/lesson/status&id=' + LESSON_ID, {credentials: 'same-origin'})
      .then(function (r) { return r.json(); }).then(render).catch(function () {});
  }
  load();
  if (OPEN) setInterval(load, 4000);
})();
</script>
<?php endif; ?>
