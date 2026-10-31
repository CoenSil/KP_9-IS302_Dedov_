<div class="pagehead">
  <div>
    <h1>Сотрудники сегодня</h1>
    <p class="pagehead__sub"><span id="todayLabel"><?= date('d.m.Y') ?></span> · данные обновляются сами</p>
  </div>
  <a class="btn" href="<?= BASE_URL ?>index.php?route=admin/employees/create">Добавить сотрудника</a>
</div>

<div id="notifBox"></div>
<div id="attentionBox"></div>

<div class="toolbar">
  <div class="chips" id="chips" role="group" aria-label="Фильтр по статусу"></div>
  <div class="toolbar__right">
    <input type="search" id="searchBox" placeholder="Поиск по имени" aria-label="Поиск по имени">
    <select id="deptFilter" aria-label="Подразделение"><option value="">Все подразделения</option></select>
  </div>
</div>

<div class="card card--flush">
  <div class="table-wrap">
    <table class="staff">
      <thead>
        <tr>
          <th>Сотрудник</th>
          <th class="col-day"><div class="dayaxis" id="dayAxis" aria-hidden="true"></div></th>
          <th>Вход – выход</th>
          <th class="num">Отработано</th>
          <th>Статус</th>
          <th>График</th>
          <th><span class="sr-only">Действия</span></th>
        </tr>
      </thead>
      <tbody id="employeesBody"></tbody>
    </table>
  </div>
</div>

<script>
const DASHBOARD_DATA_URL = '<?= BASE_URL ?>index.php?route=admin/dashboard/data';
const SCHEDULE_URL       = '<?= BASE_URL ?>index.php?route=admin/employees/schedule';
const ALLOW_REENTRY_URL  = '<?= BASE_URL ?>index.php?route=admin/employees/allow-reentry';
const NOTIF_READ_URL     = '<?= BASE_URL ?>index.php?route=admin/notifications/read';
const NOTIF_CANCEL_URL   = '<?= BASE_URL ?>index.php?route=admin/notifications/cancel';
const FORCE_ACK_URL      = '<?= BASE_URL ?>index.php?route=admin/org/force-ack';
const FORCE_REASONS      = <?= json_encode(\App\Models\ForceMajeure::REASONS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

const STATUS_LABELS = {
  working: 'в работе',
  finished: 'завершил день',
  not_working: 'не работает',
};
const FILTERS = [
  { key: 'all',      label: 'Все',            test: () => true },
  { key: 'working',  label: 'В работе',       test: t => t.status === 'working' || t.status === 'late' },
  { key: 'late',     label: 'Опоздали',       test: t => t.status === 'late' },
  { key: 'finished', label: 'Завершили день', test: t => t.status === 'finished' },
  { key: 'absent',   label: 'Не пришли',      test: t => t.status === 'not_working' },
];

let allEmployees = [];
let serverNow = <?= json_encode($serverNow ?? date('Y-m-d H:i:s')) ?>;
let activeFilter = 'all';

function statusClass(t) { return t.on_break ? 'break' : (t.on_lunch ? 'lunch' : t.status); }
function statusLabel(t) {
  if (t.on_break) return 'перерыв до ' + (t.break_until || '');
  if (t.on_lunch) return 'на обеде до ' + t.lunch_until;
  if (t.status === 'late') return 'опоздание ' + t.late_minutes + ' мин';
  return STATUS_LABELS[t.status] || t.status;
}
function forceLabel(reason) { return FORCE_REASONS[reason] || reason; }
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function hm(value) {
  if (!value) return null;
  return value.length > 8 ? value.slice(11, 16) : value.slice(0, 5);
}
function toMin(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function timeSpan(value, cls) {
  return '<span' + (cls ? ' class="' + cls + '"' : '') + '>' + (value ? hm(value) : '—') + '</span>';
}
function formatWorked(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return h + ' ч ' + String(m).padStart(2, '0') + ' мин';
}
function ruDate(iso) { const p = String(iso || '').split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : iso; }
function initials(name) {
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
}

function computeAxis(list, nowMin) {
  let lo = 8 * 60, hi = 20 * 60;
  list.forEach(e => {
    if (e.planned_login)  lo = Math.min(lo, toMin(hm(e.planned_login)));
    if (e.planned_logout) hi = Math.max(hi, toMin(hm(e.planned_logout)));
    if (e.today.last_login_at)  lo = Math.min(lo, toMin(hm(e.today.last_login_at)));
    if (e.today.last_logout_at) hi = Math.max(hi, toMin(hm(e.today.last_logout_at)));
  });
  hi = Math.max(hi, Math.min(nowMin, 24 * 60));
  const start = Math.max(0, Math.floor(lo / 60) - 0);
  const end = Math.min(24, Math.ceil(hi / 60) + 0);
  return { start, end: Math.max(end, start + 4) };
}
function renderAxis(axis) {
  const ticks = axis.end - axis.start;
  const step = ticks <= 8 ? 1 : ticks <= 14 ? 2 : 3;
  let html = '';
  for (let h = axis.start; h <= axis.end; h += step) {
    html += '<span style="left:' + ((h - axis.start) / ticks * 100) + '%">' + String(h).padStart(2, '0') + '</span>';
  }
  document.getElementById('dayAxis').innerHTML = html;
}
function dayline(e, axis, nowMin) {
  const t = e.today;
  const span = (axis.end - axis.start) * 60;
  const pos = m => Math.min(100, Math.max(0, (m - axis.start * 60) / span * 100));
  let html = '<div class="dayline" style="--ticks:' + (axis.end - axis.start) + '">';

  const pin = e.planned_login ? toMin(hm(e.planned_login)) : null;
  const pout = e.planned_logout ? toMin(hm(e.planned_logout)) : null;
  if (pin !== null && pout !== null) {
    html += '<div class="dayline__plan" style="left:' + pos(pin) + '%;width:' + (pos(pout) - pos(pin)) + '%"></div>';
    if (!t.last_login_at && nowMin > pin) {
      const to = Math.min(nowMin, pout);
      html += '<div class="dayline__gap" style="left:' + pos(pin) + '%;width:' + (pos(to) - pos(pin)) + '%"></div>';
    }
  }
  if (t.last_login_at) {
    const from = toMin(hm(t.last_login_at));
    const to = t.last_logout_at ? toMin(hm(t.last_logout_at)) : Math.max(from, nowMin);
    const cls = t.status === 'finished' ? ' is-done' : (t.status === 'late' ? ' is-late' : '');
    html += '<div class="dayline__fill' + cls + '" style="left:' + pos(from) + '%;width:' + Math.max(0.8, pos(to) - pos(from)) + '%"></div>';
  }
  (t.lunch_segments || []).forEach(seg => {
    const a = toMin(seg.from), b = toMin(seg.to);
    html += '<div class="dayline__lunch" title="Обед ' + seg.from + '–' + seg.to + '" style="left:' + pos(a) + '%;width:' + Math.max(0.8, pos(b) - pos(a)) + '%"></div>';
  });
  (t.break_segments || []).forEach(seg => {
    const a = toMin(seg.from), b = toMin(seg.to);
    html += '<div class="dayline__break" title="Перерыв ' + seg.from + '–' + seg.to + '" style="left:' + pos(a) + '%;width:' + Math.max(0.8, pos(b) - pos(a)) + '%"></div>';
  });
  if (nowMin >= axis.start * 60 && nowMin <= axis.end * 60) {
    html += '<div class="dayline__now" style="left:' + pos(nowMin) + '%"></div>';
  }
  return html + '</div>';
}

function buildChips() {
  document.getElementById('chips').innerHTML = FILTERS.map(f =>
    '<button type="button" class="chip" data-key="' + f.key + '" aria-pressed="' + (f.key === activeFilter) + '">'
    + f.label + ' <span class="chip__count" data-count="' + f.key + '">0</span></button>'
  ).join('');
}
function updateChips() {
  FILTERS.forEach(f => {
    const n = allEmployees.filter(e => f.test(e.today)).length;
    const el = document.querySelector('[data-count="' + f.key + '"]');
    if (el) el.textContent = n;
  });
  document.querySelectorAll('.chip').forEach(c => c.setAttribute('aria-pressed', c.dataset.key === activeFilter));
}
function updateDepartments() {
  const sel = document.getElementById('deptFilter');
  const current = sel.value;
  const names = [...new Set(allEmployees.map(e => e.department_name))].sort((a, b) => a.localeCompare(b, 'ru'));
  sel.innerHTML = '<option value="">Все подразделения</option>'
    + names.map(n => '<option value="' + esc(n) + '">' + esc(n) + '</option>').join('');
  sel.value = names.includes(current) ? current : '';
}

function renderTable() {
  const body = document.getElementById('employeesBody');
  const nowMin = toMin(hm(serverNow));
  const axis = computeAxis(allEmployees, nowMin);
  renderAxis(axis);
  updateChips();

  const f = FILTERS.find(x => x.key === activeFilter) || FILTERS[0];
  const q = document.getElementById('searchBox').value.trim().toLowerCase();
  const dep = document.getElementById('deptFilter').value;
  const list = allEmployees.filter(e =>
    f.test(e.today)
    && (!q || e.full_name.toLowerCase().includes(q))
    && (!dep || e.department_name === dep));

  if (!allEmployees.length) {
    body.innerHTML = '<tr><td colspan="7"><div class="empty-state">Сотрудников пока нет. Добавьте первого — кнопка справа вверху.</div></td></tr>';
    return;
  }
  if (!list.length) {
    body.innerHTML = '<tr><td colspan="7"><div class="empty-state">По этим условиям никого не нашлось. Снимите фильтр или измените запрос.</div></td></tr>';
    return;
  }

  body.innerHTML = list.map(e => {
    const t = e.today;
    const schedule = e.planned_login
      ? esc(hm(e.planned_login)) + '–' + esc(hm(e.planned_logout))
      : 'задать';
    const custom = (e.custom_planned_login || e.custom_planned_logout)
      ? '<span class="badge-custom" title="Индивидуальный график">инд.</span>' : '';

    let reentryUi = '';
    if (t.reentry_requested) {
      reentryUi = '<button type="button" class="allow-reentry request-pending" data-id="' + e.id + '">Запрос на вход</button>';
    } else if (t.reentry_allowed) {
      reentryUi = '<span class="reentry-hint">вход разрешён, ждём</span>';
    } else if (t.status === 'finished') {
      reentryUi = '<button type="button" class="allow-reentry" data-id="' + e.id + '">Разрешить вход</button>';
    }

    return '<tr class="' + (t.reentry_requested ? 'row--attention' : '') + '">'
      + '<td><div class="person"><span class="avatar" aria-hidden="true">' + esc(initials(e.full_name)) + '</span>'
      +   '<div><div class="person__name">' + esc(e.full_name) + (e.force_open ? ' <span class="tag tag--warn">форс-мажор</span>' : '') + '</div>'
      +   '<div class="person__sub">' + esc(e.position_name) + ' · ' + esc(e.department_name)
      +   (e.workplace ? ' · <span class="tag tag--idle">' + esc(e.workplace) + '</span>' : '') + '</div></div></div></td>'
      + '<td class="col-day">' + dayline(e, axis, nowMin) + '</td>'
      + '<td data-label="Вход – выход">' + (t.last_login_at
          ? timeSpan(t.last_login_at, t.login_class) + ' – ' + timeSpan(t.last_logout_at, t.logout_class)
          : '—') + '</td>'
      + '<td class="num" data-label="Отработано">' + formatWorked(t.worked_seconds) + '</td>'
      + '<td><span class="status-pill status-' + statusClass(t) + '">' + esc(statusLabel(t)) + '</span></td>'
      + '<td data-label="График"><a href="' + SCHEDULE_URL + '&id=' + e.id + '">' + schedule + '</a>' + custom + '</td>'
      + '<td>' + reentryUi + '</td>'
      + '</tr>';
  }).join('');
}

function renderNotifications(list) {
  const box = document.getElementById('notifBox');
  box.innerHTML = list.map(n =>
    '<div class="notice" role="alert">'
    + '<div class="notice__title">' + esc(n.title) + '</div>'
    + '<div class="notice__meta">' + esc(n.full_name) + ' · ' + esc(ruDate(n.work_date)) + '</div>'
    + '<p>' + esc(n.text) + '</p>'
    + '<div class="notice__actions">'
    + '<button type="button" class="btn-ghost notif-read" data-id="' + n.id + '">Принято к сведению</button>'
    + '<button type="button" class="btn-danger-ghost notif-cancel" data-id="' + n.id + '">Уважительная причина — отменить</button>'
    + '</div></div>'
  ).join('');
}

function renderForceOpen(list) {
  const box = document.getElementById('attentionBox');
  if (!list || !list.length) { box.innerHTML = ''; return; }
  box.innerHTML = list.map(f =>
    '<div class="notice notice--attention" role="alert">'
    + '<div class="notice__title">Форс-мажор: ' + esc(f.full_name) + '</div>'
    + '<div class="notice__meta">' + esc(forceLabel(f.reason)) + ' · ' + esc(ruDate(f.work_date)) + ' · ' + esc(hm(f.created_at) || '') + '</div>'
    + '<p>' + esc(f.comment) + '</p>'
    + '<div class="notice__actions"><button type="button" class="btn-ghost force-ack" data-id="' + f.id + '">Принять к сведению</button></div>'
    + '</div>'
  ).join('');
}

document.getElementById('attentionBox').addEventListener('click', async (ev) => {
  const btn = ev.target.closest('.force-ack');
  if (!btn) return;
  btn.disabled = true;
  try {
    await fetch(FORCE_ACK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + btn.dataset.id + '&ajax=1',
    });
  } catch (e) {}
  refresh();
});

document.getElementById('notifBox').addEventListener('click', async (ev) => {
  const read = ev.target.closest('.notif-read');
  const cancel = ev.target.closest('.notif-cancel');
  const btn = read || cancel;
  if (!btn) return;
  if (cancel && !confirm('Признать причину отсутствия уважительной и снять уведомление у сотрудника?')) return;
  btn.disabled = true;
  try {
    await fetch(read ? NOTIF_READ_URL : NOTIF_CANCEL_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + btn.dataset.id,
    });
  } catch (e) {}
  refresh();
});

async function refresh() {
  try {
    const res = await fetch(DASHBOARD_DATA_URL);
    const data = await res.json();
    if (data.ok) {
      allEmployees = data.employees;
      if (data.server_now) serverNow = data.server_now;
      updateDepartments();
      renderTable();
      renderNotifications(data.notifications || []);
      renderForceOpen(data.force_open || []);
    }
  } catch (e) {

  }
}

document.getElementById('employeesBody').addEventListener('click', async (ev) => {
  const btn = ev.target.closest('.allow-reentry');
  if (!btn) return;
  btn.disabled = true;
  btn.textContent = '…';
  try {
    await fetch(ALLOW_REENTRY_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + btn.dataset.id + '&ajax=1',
    });
  } catch (e) {}
  refresh();
});

document.getElementById('chips').addEventListener('click', (ev) => {
  const chip = ev.target.closest('.chip');
  if (!chip) return;
  activeFilter = chip.dataset.key;
  renderTable();
});
document.getElementById('searchBox').addEventListener('input', renderTable);
document.getElementById('deptFilter').addEventListener('change', renderTable);

document.getElementById('todayLabel').textContent =
  new Date().toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });

allEmployees = <?= json_encode($employees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
buildChips();
updateDepartments();
renderTable();
renderNotifications(<?= json_encode($notifications ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
renderForceOpen(<?= json_encode($forceOpen ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
setInterval(refresh, 6000);
</script>
