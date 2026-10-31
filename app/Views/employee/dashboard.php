<?php
$nameParts = preg_split('/\s+/u', trim($session['full_name']));
$greetName = $nameParts[1] ?? $nameParts[0];
$statusLabels = [
  'working' => 'в работе',
  'late' => 'опоздание ' . $status['late_minutes'] . ' мин',
  'finished' => 'день завершён',
  'not_working' => 'не работает',
];
$pillClass = !empty($status['on_break']) ? 'break' : (!empty($status['on_lunch']) ? 'lunch' : $status['status']);
$pillText = !empty($status['on_break'])
    ? 'перерыв до ' . $status['break_until']
    : (!empty($status['on_lunch']) ? 'на обеде до ' . $status['lunch_until'] : ($statusLabels[$status['status']] ?? $status['status']));
$breakCountMax = \App\Core\Settings::breakMaxCount();
?>
<div id="empNotifBox"></div>

<nav class="tabs" data-tabs aria-label="Разделы рабочего дня">
  <button type="button" class="tabs__btn is-active" data-tab="overview" aria-selected="true">Обзор</button>
  <button type="button" class="tabs__btn" data-tab="breaks" aria-selected="false">Перерывы</button>
  <button type="button" class="tabs__btn" data-tab="force" aria-selected="false">Форс-мажор</button>
</nav>

<div class="tab-panel" data-tab-panel="overview">
<section class="hero" aria-labelledby="heroTitle">
  <div class="hero__top">
    <div>
      <p class="hero__hello" id="heroTitle">Здравствуйте, <?= htmlspecialchars($greetName) ?></p>
      <div class="clock" id="clock">--:--:--</div>
      <p class="hero__date" id="todayDate"></p>
    </div>
    <span class="status-pill status-<?= $pillClass ?>" id="statusPill"><?= htmlspecialchars($pillText) ?></span>
  </div>

  <div class="dayline dayline--lg" id="myDay" role="img" aria-label="Шкала рабочего дня">
    <div class="dayline__plan" id="myPlan" hidden></div>
    <div class="dayline__gap" id="myGap" hidden></div>
    <div class="dayline__fill" id="myFill" hidden></div>
    <div class="dayline__now" id="myNow" hidden></div>
  </div>
  <div class="dayaxis dayaxis--lg" id="myAxis" aria-hidden="true"></div>
  <ul class="legend">
    <li><i class="l-plan"></i>рабочее время по графику</li>
    <li><i class="l-ok"></i>вы на месте</li>
    <li><i class="l-late"></i>пришли с опозданием</li>
    <li><i class="l-gap"></i>вас ждали, но не было</li>
    <li><i class="l-lunch"></i>обед (засчитывается)</li>
    <li><i class="l-break"></i>перерыв (засчитывается)</li>
  </ul>

  <div class="facts">
    <div class="fact"><span class="fact__label">Последний вход</span>
      <strong class="fact__value" id="logInDisplay"><?= $status['last_login_at'] ? date('H:i', strtotime($status['last_login_at'])) : '—' ?></strong></div>
    <div class="fact"><span class="fact__label">Последний выход</span>
      <strong class="fact__value" id="logOutDisplay"><?= $status['last_logout_at'] ? date('H:i', strtotime($status['last_logout_at'])) : '—' ?></strong></div>
    <div class="fact"><span class="fact__label">Отработано сегодня</span>
      <strong class="fact__value" id="workedDisplay"><?= sprintf('%dч %02dм', intdiv((int) $status['worked_seconds'], 3600), intdiv((int) $status['worked_seconds'] % 3600, 60)) ?></strong></div>
    <div class="fact"><span class="fact__label">Ваш график</span>
      <strong class="fact__value" id="scheduleDisplay"><?= ($plannedLogin && $plannedLogout) ? substr($plannedLogin, 0, 5) . '–' . substr($plannedLogout, 0, 5) : 'не задан' ?></strong></div>
  </div>
</section>

<div class="grid-2">
  <div class="card" id="countdownCard">
    <p class="card__label">До конца смены</p>
    <p class="card__hint" id="countdownSub">—</p>
    <div class="clock" id="countdown">--:--:--</div>
    <div class="countdown-bar"><div class="countdown-bar__fill" id="countdownFill"></div></div>
  </div>

  <div class="card" id="activityCard">
    <p class="card__label">Активность</p>
    <p class="card__hint" id="activitySub">—</p>
    <p class="card__hint" style="margin-bottom:0;">Двигайте мышь или нажимайте клавиши — отсчёт начнётся заново. Если вкладка бездействует дольше <?= INACTIVITY_TIMEOUT / 60 ?> мин, день завершится сам.</p>
    <div class="countdown-bar"><div class="countdown-bar__fill" id="activityFill"></div></div>
    <p class="card__hint" id="bgNote" style="margin:14px 0 0;"></p>
    <p class="card__hint" id="smallNote" style="margin:8px 0 0; color: var(--danger);" hidden>
      Окно браузера уменьшено больше чем наполовину — активность не засчитывается. Разверните окно.
    </p>
    <button type="button" class="btn-ghost btn-sm" id="idleBtn" style="margin-top:8px;" hidden>Разрешить контроль активности</button>
  </div>
</div>

<div class="card lunch" id="lunchCard">
  <div class="lunch__text">
    <p class="card__label">Обед</p>
    <p class="card__hint" id="lunchSub" style="margin:0;">Время обеда засчитывается в рабочее.</p>
    <p class="card__hint" id="lunchMsg" role="alert" style="margin:6px 0 0; color: var(--danger);"></p>
  </div>
  <div class="lunch__ctl">
    <span class="lunch__timer" id="lunchTimer" hidden>00:00</span>
    <button type="button" class="btn-ghost" id="lunchBtn" disabled>Уйти на обед</button>
  </div>
</div>

<div class="card share" id="shareCard">
  <div class="share__text">
    <p class="card__label">Показать экран администратору</p>
    <p class="card__hint" id="shareHint" style="margin:0;">
      Включается только вашим действием: браузер спросит, какой экран или окно показывать.
      Остановить можно здесь или кнопкой «Stop sharing» в браузере.
    </p>
  </div>
  <div class="share__ctl">
    <span class="status-pill status-not_working" id="shareStatus">выключено</span>
    <button type="button" class="btn-ghost" id="shareBtn">Включить трансляцию</button>
  </div>
</div>

<?php if (($_SESSION['company_type'] ?? 'company') !== 'school'): ?>
<?php
$wpState = $workplace['state'] ?? null;
$wpCurrentName = $wpState['current'] ?? (!empty($workplace['current']['name']) ? $workplace['current']['name'] : null);
$wpMoveName = $wpState['to_name'] ?? null;
$wpMoving = !empty($wpState['moving']);
?>
<div class="card workplace" id="workplaceCard">
  <div class="workplace__text">
    <p class="card__label">Рабочее место</p>
    <p class="card__hint" id="workplaceNow" style="margin:0;">
      <?= $wpCurrentName ? 'Сейчас: ' . htmlspecialchars($wpCurrentName) : 'Место не выбрано' ?>
    </p>
    <p class="card__hint" id="workplaceMoveNote" style="margin:6px 0 0;<?= $wpMoving ? '' : ' display:none;' ?>">
      <?php if ($wpMoving): ?>
        Переход<?= $wpMoveName ? ' на «' . htmlspecialchars($wpMoveName) . '»' : '' ?>.
        Подтвердите прибытие до <?= htmlspecialchars($wpState['arrive_by'] ?? '') ?>.
      <?php endif; ?>
    </p>
  </div>
  <div class="workplace__ctl" id="workplacePickCtl"<?= $wpMoving ? ' hidden' : '' ?>>
    <select id="workplaceSelect" aria-label="Куда переместиться">
      <?php foreach (($workplace['list'] ?? []) as $wp): ?>
        <option value="<?= (int) $wp['id'] ?>" <?= (!empty($wpState['current_id']) && (int) $wpState['current_id'] === (int) $wp['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($wp['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn-ghost" id="workplaceBtn" <?= empty($workplace['list']) ? 'disabled' : '' ?>>Переместиться</button>
  </div>
  <div class="workplace__ctl" id="workplaceMoveCtl"<?= $wpMoving ? '' : ' hidden' ?>>
    <span class="lunch__timer" id="workplaceTimer">--:--</span>
    <button type="button" class="btn-ghost" id="workplaceArriveBtn">Я на месте</button>
  </div>
  <p class="card__hint" id="workplaceMsg" role="alert" style="margin:8px 0 0; color: var(--danger);"></p>
  <p class="card__hint" id="workplaceHint" style="margin:6px 0 0; <?= empty($workplace['list']) ? '' : 'display:none;' ?>">
    Администратор ещё не добавил рабочие места. Обратитесь к нему.
  </p>
</div>
<?php endif; ?>
</div>

<div class="tab-panel" data-tab-panel="breaks" hidden>
<div class="card breakcard" id="breakCard">
  <div class="breakcard__text">
    <p class="card__label">Перерыв</p>
    <p class="card__hint" id="breakSub" style="margin:0;">Перерыв доступен только в окна, заданные администратором.</p>
    <p class="card__hint" id="breakStats" style="margin:6px 0 0;"></p>
    <p class="card__hint" id="breakMsg" role="alert" style="margin:6px 0 0; color: var(--danger);"></p>
  </div>
  <div class="breakcard__ctl">
    <span class="lunch__timer" id="breakTimer" hidden>00:00</span>
    <button type="button" class="btn-ghost" id="breakBtn" disabled>Сделать перерыв</button>
  </div>
</div>

<div class="card" id="breakWindowsCard" hidden>
  <h2>Регламентированные перерывы</h2>
  <p class="card__hint">Перерыв можно взять только в своё окно. Неиспользованное время окна сгорает — перенести его нельзя.</p>
  <ul class="compact-list" id="breakWindows"></ul>
</div>

<p class="card__hint" style="margin-top:14px;">
  Время перерыва засчитывается в рабочее. Если не вернуться вовремя, перерыв завершится автоматически.
</p>
</div>

<div class="tab-panel" data-tab-panel="force" hidden>
<div class="card">
  <h2>Сообщить о форс-мажоре</h2>
  <p class="card__hint">Коротко опишите, что случилось. Сообщение сразу увидит администратор.</p>
  <form id="forceForm">
    <div class="field">
      <label for="forceReason">Причина</label>
      <select id="forceReason" name="reason" required>
        <option value="">— выберите —</option>
        <?php foreach (($forceReasons ?? []) as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="forceComment">Комментарий</label>
      <textarea id="forceComment" name="comment" rows="3" maxlength="<?= (int) \App\Core\Settings::forceCommentMax() ?>" required placeholder="Например: плохо себя чувствую, задержусь на 20 минут"></textarea>
    </div>
    <p class="card__hint" id="forceMsg" role="alert" style="color: var(--danger);"></p>
    <button type="submit" id="forceBtn">Отправить администратору</button>
  </form>
</div>

<div class="card">
  <h2>Мои сообщения</h2>
  <div id="forceHistory">
    <?php if (empty($forceHistory)): ?>
      <div class="empty-state">Сообщений пока нет.</div>
    <?php else: ?>
      <ul class="compact-list">
        <?php foreach ($forceHistory as $fm): ?>
          <li>
            <strong><?= htmlspecialchars(\App\Models\ForceMajeure::label($fm['reason'])) ?></strong>
            <span class="tag tag--<?= $fm['status'] === 'open' ? 'warn' : 'ok' ?>"><?= $fm['status'] === 'open' ? 'на рассмотрении' : 'принято' ?></span>
            <span class="muted"><?= htmlspecialchars(date('d.m H:i', strtotime($fm['created_at']))) ?></span>
            <span class="muted"><?= htmlspecialchars($fm['comment']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
const HEARTBEAT_URL    = '<?= BASE_URL ?>index.php?route=employee/heartbeat';
const AUTOLOGOUT_URL   = '<?= BASE_URL ?>index.php?route=employee/autologout';
const LOGIN_URL        = '<?= BASE_URL ?>index.php?route=auth/login';
const LUNCH_START_URL  = '<?= BASE_URL ?>index.php?route=employee/lunch/start';
const LUNCH_END_URL    = '<?= BASE_URL ?>index.php?route=employee/lunch/end';
const INACTIVITY_MS    = <?= \App\Core\Settings::inactivity() ?> * 1000;
const BG_INACTIVITY_MS = <?= \App\Core\Settings::backgroundInactivity() ?> * 1000;
const HEARTBEAT_MS     = 30 * 1000;
const SECURE_CONTEXT   = window.isSecureContext === true;

let lastActivity = Date.now();
let lastSentHeartbeat = 0;
let lastTickAt = Date.now();
let inBackground = document.hidden || !document.hasFocus();
let onLunch = false;
let onBreak = false;
let idleDetector = null;

function smallWindow() {
  return !!(window.OPRES_WINDOW && window.OPRES_WINDOW.tooSmall());
}

['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
  document.addEventListener(evt, () => {
    if (smallWindow()) return;
    lastActivity = Date.now();
  }, { passive: true });
});

function markBackground() { inBackground = true; }
function markForeground() {
  if (!inBackground) return;
  inBackground = false;

  if (!onLunch && !onBreak && !smallWindow() && Date.now() - lastActivity <= BG_INACTIVITY_MS) lastActivity = Date.now();
}
document.addEventListener('visibilitychange', () => (document.hidden ? markBackground() : markForeground()));
window.addEventListener('blur', markBackground);
window.addEventListener('focus', () => { if (!document.hidden) markForeground(); });

function currentMode() {
  if (onLunch || onBreak) return 'lunch';
  if (smallWindow()) return 'fg';
  if (idleDetector) return 'idle';
  return inBackground ? 'bg' : 'fg';
}
function currentLimitMs() { return currentMode() === 'bg' ? BG_INACTIVITY_MS : INACTIVITY_MS; }

const FORM_HEADERS = { 'Content-Type': 'application/x-www-form-urlencoded' };

function sendHeartbeat() {
  lastSentHeartbeat = Date.now();
  fetch(HEARTBEAT_URL, { method: 'POST', headers: FORM_HEADERS, body: 'mode=' + currentMode() }).catch(() => {});
}

function doAutoLogout(idleMs) {

  fetch(AUTOLOGOUT_URL, {
    method: 'POST', headers: FORM_HEADERS,
    body: 'idle_ms=' + Math.round(idleMs) + '&limit_ms=' + currentLimitMs(),
  })
    .then(r => r.json())
    .then(d => {
      if (d && d.ok === false && (d.error === 'on_lunch' || d.error === 'on_break')) { refreshStatus(); return; }
      window.location.href = LOGIN_URL;
    })
    .catch(() => { window.location.href = LOGIN_URL; });
}

function tick() {
  const now = Date.now();
  const gap = now - lastTickAt;
  lastTickAt = now;

  if (onLunch || onBreak) {
    lastActivity = now;
    if (now - lastSentHeartbeat >= HEARTBEAT_MS) sendHeartbeat();
    return;
  }

  const small = smallWindow();

  if (!small && idleDetector && idleDetector.userState === 'active' && idleDetector.screenState === 'unlocked') {
    lastActivity = now;
  }

  if (!small && gap > 120000 && inBackground && !idleDetector) {
    lastActivity = Math.min(now, lastActivity + gap);
  }

  const idleFor = now - lastActivity;
  if (idleFor >= currentLimitMs()) {
    doAutoLogout(idleFor);
    return;
  }

  if (now - lastSentHeartbeat >= HEARTBEAT_MS) sendHeartbeat();
}

(function startTicker() {
  try {
    const url = URL.createObjectURL(new Blob(['setInterval(function(){postMessage(0)},5000);'], { type: 'text/javascript' }));
    const worker = new Worker(url);
    worker.onmessage = tick;
    return;
  } catch (e) {  }
  setInterval(tick, 5000);
})();

function renderClock() {
  document.getElementById('clock').textContent =
    new Date().toLocaleTimeString('ru-RU');
}
renderClock();
setInterval(renderClock, 1000);

const LOG_IN_ISO = <?= $status['last_login_at'] ? json_encode(str_replace(' ', 'T', $status['last_login_at'])) : 'null' ?>;
const logInTime = LOG_IN_ISO ? new Date(LOG_IN_ISO).getTime() : null;

function pad(n) { return String(n).padStart(2, '0'); }
function formatHMS(totalSeconds) {
  const s = Math.max(0, Math.floor(totalSeconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  return pad(h) + ':' + pad(m) + ':' + pad(sec);
}

function renderActivityBar() {
  const fill = document.getElementById('activityFill');
  const sub = document.getElementById('activitySub');
  const small = smallWindow();
  const smallNote = document.getElementById('smallNote');
  if (smallNote) smallNote.hidden = !(small && !onLunch && !onBreak);

  if (onLunch || onBreak) {
    fill.style.width = '100%';
    fill.style.background = 'var(--brand)';
    sub.textContent = (onBreak ? 'Перерыв' : 'Обед') + ': авто-выход отключён';
    return;
  }

  const limit = small ? INACTIVITY_MS : currentLimitMs();
  const remainingMs = Math.max(0, limit - (Date.now() - lastActivity));
  const percent = (remainingMs / limit) * 100;

  fill.style.width = percent + '%';
  sub.textContent = 'До авто-выхода: ' + formatHMS(remainingMs / 1000)
    + (small ? ' (окно уменьшено)' : (currentMode() === 'bg' ? ' (вкладка в фоне)' : ''));

  if (percent <= 20) {
    fill.style.background = 'var(--danger)';
  } else if (percent <= 50) {
    fill.style.background = 'var(--warn)';
  } else {
    fill.style.background = 'var(--accent)';
  }
}

setInterval(renderActivityBar, 250);
renderActivityBar();

let PLANNED_LOGOUT = <?= $plannedLogout ? json_encode(substr($plannedLogout, 0, 5)) : 'null' ?>;
const todayISO = new Date().toISOString().slice(0, 10);
let plannedLogoutTime = PLANNED_LOGOUT ? new Date(todayISO + 'T' + PLANNED_LOGOUT + ':00').getTime() : null;
let SHIFT_SECONDS = (logInTime && plannedLogoutTime) ? Math.max(1, (plannedLogoutTime - logInTime) / 1000) : null;

let hornPlayed = false;

function playHorn() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();

    [0, 0.35, 0.7].forEach(delay => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'square';
      osc.frequency.value = 880;
      gain.gain.value = 0.15;
      osc.connect(gain).connect(ctx.destination);
      osc.start(ctx.currentTime + delay);
      osc.stop(ctx.currentTime + delay + 0.25);
    });
  } catch (e) {

  }
}

function renderCountdown() {
  const el = document.getElementById('countdown');
  const sub = document.getElementById('countdownSub');
  const fill = document.getElementById('countdownFill');
  const card = document.getElementById('countdownCard');

  if (!plannedLogoutTime) {
    el.textContent = '—';
    sub.textContent = 'Для вашей должности график не задан';
    return;
  }

  const diff = (plannedLogoutTime - Date.now()) / 1000;

  if (diff > 0) {
    el.textContent = formatHMS(diff);
    sub.textContent = 'Плановое окончание: ' + PLANNED_LOGOUT;
    card.classList.remove('countdown--overtime');
    card.classList.toggle('countdown--warning', diff <= 30 * 60);
    card.classList.toggle('countdown--critical', diff <= 5 * 60);

    if (SHIFT_SECONDS) {
      const progress = Math.min(100, Math.max(0, 100 - (diff / SHIFT_SECONDS) * 100));
      fill.style.width = progress + '%';
    }
  } else {

    el.textContent = '+' + formatHMS(-diff);
    sub.textContent = 'Переработка сверх плана (' + PLANNED_LOGOUT + ')';
    card.classList.add('countdown--overtime');
    card.classList.remove('countdown--warning', 'countdown--critical');
    fill.style.width = '100%';

    if (!hornPlayed) {
      hornPlayed = true;
      playHorn();
    }
  }
}
renderCountdown();
setInterval(renderCountdown, 1000);

const SHARE_FRAME_URL = '<?= BASE_URL ?>index.php?route=employee/screen/frame';
const SHARE_STOP_URL  = '<?= BASE_URL ?>index.php?route=employee/screen/stop';

const shareBtn = document.getElementById('shareBtn');
const shareStatus = document.getElementById('shareStatus');
let shareStream = null;
let shareInterval = null;

if (!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia)) {

  shareBtn.disabled = true;
  shareBtn.textContent = 'Трансляция недоступна';
  document.getElementById('shareHint').textContent =
    'Трансляция экрана недоступна: браузер её не поддерживает, либо сайт открыт не по HTTPS.';
}

async function startSharing() {
  try {
    shareStream = await navigator.mediaDevices.getDisplayMedia({ video: { frameRate: 5 } });
  } catch (e) {
    return;
  }

  const video = document.createElement('video');
  video.srcObject = shareStream;
  video.muted = true;
  await video.play();

  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');

  shareInterval = setInterval(() => {
    if (!video.videoWidth) return;
    const scale = Math.min(1, 960 / video.videoWidth);

    canvas.width = video.videoWidth * scale;
    canvas.height = video.videoHeight * scale;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const frame = canvas.toDataURL('image/jpeg', 0.5);
    fetch(SHARE_FRAME_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'frame=' + encodeURIComponent(frame),
    }).catch(() => {});
  }, 2000);

  shareStream.getVideoTracks()[0].addEventListener('ended', stopSharing);

  shareBtn.textContent = 'Остановить трансляцию';
  shareStatus.textContent = 'идёт трансляция';
  shareStatus.className = 'status-pill status-working';
}

function stopSharing() {
  if (shareInterval) clearInterval(shareInterval);
  shareInterval = null;
  if (shareStream) {
    shareStream.getTracks().forEach(t => t.stop());
    shareStream = null;
  }
  fetch(SHARE_STOP_URL, { method: 'POST' }).catch(() => {});
  shareBtn.textContent = 'Включить трансляцию';
  shareStatus.textContent = 'выключено';
  shareStatus.className = 'status-pill status-not_working';
}

shareBtn.addEventListener('click', () => {
  if (shareStream) {
    stopSharing();
  } else {
    startSharing();
  }
});

window.addEventListener('beforeunload', () => {
  if (shareStream) {
    navigator.sendBeacon(SHARE_STOP_URL);
  }
});

const STATUS_URL = '<?= BASE_URL ?>index.php?route=employee/status';
const STATUS_LABELS_EMP = {
  working: 'в работе',
  finished: 'день завершён',
  not_working: 'не работает',
};

async function refreshStatus() {
  try {
    const res = await fetch(STATUS_URL);
    const data = await res.json();
    if (!data.ok) return;
    const s = data.status;

    document.getElementById('logInDisplay').textContent =
      s.last_login_at ? new Date(s.last_login_at.replace(' ', 'T')).toLocaleTimeString('ru-RU', {hour:'2-digit', minute:'2-digit'}) : '—';
    document.getElementById('logOutDisplay').textContent =
      s.last_logout_at ? new Date(s.last_logout_at.replace(' ', 'T')).toLocaleTimeString('ru-RU', {hour:'2-digit', minute:'2-digit'}) : '—';

    const workedH = Math.floor(s.worked_seconds / 3600);
    const workedM = Math.floor((s.worked_seconds % 3600) / 60);
    document.getElementById('workedDisplay').textContent = workedH + 'ч ' + String(workedM).padStart(2, '0') + 'м';

    const pill = document.getElementById('statusPill');
    const label = s.on_break
      ? ('перерыв до ' + s.break_until)
      : (s.on_lunch
        ? ('на обеде до ' + s.lunch_until)
        : (s.status === 'late' ? ('опоздание ' + s.late_minutes + ' мин') : (STATUS_LABELS_EMP[s.status] || s.status)));
    pill.textContent = label;
    pill.className = 'status-pill status-' + (s.on_break ? 'break' : (s.on_lunch ? 'lunch' : s.status));
    if (typeof applyLunch === 'function') applyLunch(s);
    if (typeof applyBreak === 'function') applyBreak(s);

    if (data.plannedLogin && data.plannedLogout) {
      document.getElementById('scheduleDisplay').textContent = data.plannedLogin + '–' + data.plannedLogout;
    } else {
      document.getElementById('scheduleDisplay').textContent = 'не задан';
    }

    if (data.plannedLogout && data.plannedLogout !== PLANNED_LOGOUT) {
      PLANNED_LOGOUT = data.plannedLogout;
      plannedLogoutTime = new Date(todayISO + 'T' + PLANNED_LOGOUT + ':00').getTime();
      SHIFT_SECONDS = (logInTime && plannedLogoutTime) ? Math.max(1, (plannedLogoutTime - logInTime) / 1000) : null;
      hornPlayed = false;
    }
  } catch (e) {

  }
}
setInterval(refreshStatus, 15000);
</script>

<script>

const EMP_NOTIF_URL = '<?= BASE_URL ?>index.php?route=employee/notifications';
const EMP_NOTIF_READ_URL = '<?= BASE_URL ?>index.php?route=employee/notifications/read';
function escN(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function renderEmpNotifications(list) {
  document.getElementById('empNotifBox').innerHTML = list.map(n =>
    '<div class="notice">'
    + '<div class="notice__title">' + escN(n.title) + '</div>'
    + '<div class="notice__meta">' + escN(n.work_date.split('-').reverse().join('.')) + '</div>'
    + '<p>' + escN(n.text) + '</p>'
    + '<div class="notice__actions"><button type="button" class="emp-notif-read" data-id="' + n.id + '">Ознакомлен</button></div>'
    + '</div>'
  ).join('');
}
async function refreshEmpNotifications() {
  try {
    const res = await fetch(EMP_NOTIF_URL);
    const data = await res.json();
    if (data.ok) renderEmpNotifications(data.notifications || []);
  } catch (e) {}
}
document.getElementById('empNotifBox').addEventListener('click', async (ev) => {
  const btn = ev.target.closest('.emp-notif-read');
  if (!btn) return;
  btn.disabled = true;
  try {
    await fetch(EMP_NOTIF_READ_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + btn.dataset.id,
    });
  } catch (e) {}
  refreshEmpNotifications();
});
renderEmpNotifications(<?= json_encode($notifications ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
setInterval(refreshEmpNotifications, 20000);
</script>

<script>

(function () {
  const $ = id => document.getElementById(id);
  const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const isTime = t => /^\d{2}:\d{2}$/.test(t);
  let axisKey = '';

  const todayText = new Date().toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });
  $('todayDate').textContent = todayText.charAt(0).toUpperCase() + todayText.slice(1);

  function renderDay() {
    const now = new Date();
    const nowMin = now.getHours() * 60 + now.getMinutes();
    const login = $('logInDisplay').textContent.trim();
    const logout = $('logOutDisplay').textContent.trim();
    const plan = $('scheduleDisplay').textContent.trim().split('–');
    const hasPlan = plan.length === 2 && isTime(plan[0]) && isTime(plan[1]);
    const pin = hasPlan ? toMin(plan[0]) : null;
    const pout = hasPlan ? toMin(plan[1]) : null;
    const inMin = isTime(login) ? toMin(login) : null;
    const outMin = isTime(logout) ? toMin(logout) : null;

    let lo = 8 * 60, hi = 20 * 60;
    [pin, inMin].forEach(v => { if (v !== null) lo = Math.min(lo, v); });
    [pout, outMin, nowMin].forEach(v => { if (v !== null) hi = Math.max(hi, v); });
    const start = Math.max(0, Math.floor(lo / 60));
    const end = Math.min(24, Math.ceil(hi / 60));
    const span = (end - start) * 60;
    const pos = m => Math.min(100, Math.max(0, (m - start * 60) / span * 100));

    $('myDay').style.setProperty('--ticks', end - start);

    const key = start + '-' + end;
    if (key !== axisKey) {
      axisKey = key;
      const step = (end - start) <= 8 ? 1 : (end - start) <= 14 ? 2 : 3;
      let html = '';
      for (let h = start; h <= end; h += step) {
        html += '<span style="left:' + ((h - start) / (end - start) * 100) + '%">' + String(h).padStart(2, '0') + '</span>';
      }
      $('myAxis').innerHTML = html;
    }

    const planEl = $('myPlan'), gapEl = $('myGap'), fillEl = $('myFill'), nowEl = $('myNow');
    planEl.hidden = !hasPlan;
    if (hasPlan) {
      planEl.style.left = pos(pin) + '%';
      planEl.style.width = (pos(pout) - pos(pin)) + '%';
    }

    const missed = hasPlan && inMin === null && nowMin > pin;
    gapEl.hidden = !missed;
    if (missed) {
      gapEl.style.left = pos(pin) + '%';
      gapEl.style.width = (pos(Math.min(nowMin, pout)) - pos(pin)) + '%';
    }

    fillEl.hidden = inMin === null;
    if (inMin !== null) {
      const to = outMin !== null ? outMin : Math.max(inMin, nowMin);
      const pillCls = $('statusPill').className;
      fillEl.className = 'dayline__fill'
        + (pillCls.includes('status-finished') ? ' is-done' : (pillCls.includes('status-late') ? ' is-late' : ''));
      fillEl.style.left = pos(inMin) + '%';
      fillEl.style.width = Math.max(0.8, pos(to) - pos(inMin)) + '%';
    }

    $('myDay').querySelectorAll('.dayline__lunch, .dayline__break').forEach(n => n.remove());
    (window.__lunchSegments || []).forEach(seg => {
      const a = toMin(seg.from), b = toMin(seg.to);
      const el = document.createElement('div');
      el.className = 'dayline__lunch';
      el.style.left = pos(a) + '%';
      el.style.width = Math.max(0.8, pos(b) - pos(a)) + '%';
      el.title = 'Обед ' + seg.from + '–' + seg.to;
      $('myDay').insertBefore(el, nowEl);
    });
    (window.__breakSegments || []).forEach(seg => {
      const a = toMin(seg.from), b = toMin(seg.to);
      const el = document.createElement('div');
      el.className = 'dayline__break';
      el.style.left = pos(a) + '%';
      el.style.width = Math.max(0.8, pos(b) - pos(a)) + '%';
      el.title = 'Перерыв ' + seg.from + '–' + seg.to;
      $('myDay').insertBefore(el, nowEl);
    });

    nowEl.hidden = false;
    nowEl.style.left = pos(nowMin) + '%';
  }
  window.renderMyDay = renderDay;
  renderDay();
  setInterval(renderDay, 15000);
})();
</script>

<script>

(function () {
  const $ = id => document.getElementById(id);
  const lunchBtn = $('lunchBtn'), lunchSub = $('lunchSub'), lunchTimer = $('lunchTimer'), lunchMsg = $('lunchMsg');
  let lunchUntilMs = 0, lunchAllowance = 0, lunchMax = 0, lunchUntilText = '', dayOpen = false, lunchAvailable = false;

  const post = url => fetch(url, { method: 'POST', headers: FORM_HEADERS, body: '' }).then(r => r.json());
  const mmss = sec => {
    sec = Math.max(0, Math.floor(sec));
    return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
  };

  window.applyLunch = function (s) {
    const was = onLunch;
    onLunch = !!s.on_lunch;
    lunchAllowance = s.lunch_allowance_seconds || 0;
    lunchMax = s.lunch_max_seconds || 0;
    lunchUntilText = s.lunch_until || '';
    lunchAvailable = !!s.lunch_available;
    dayOpen = s.status !== 'finished' && !!s.last_login_at;
    window.__lunchSegments = s.lunch_segments || [];
    if (onLunch) {
      lunchUntilMs = Date.now() + lunchAllowance * 1000;
      lastActivity = Date.now();
    } else if (was) {
      lastActivity = Date.now();
    }
    renderLunch();
    if (window.renderMyDay) window.renderMyDay();
  };

  function renderLunch() {
    if (onLunch) {
      const left = (lunchUntilMs - Date.now()) / 1000;
      if (left <= 0) {
        onLunch = false;
        lastActivity = Date.now();
        refreshStatus();
        return;
      }
      lunchTimer.hidden = false;
      lunchTimer.textContent = mmss(left);
      lunchSub.textContent = 'Вы на обеде до ' + lunchUntilText + '. Время засчитывается в рабочее, авто-выход отключён.';
      lunchBtn.textContent = 'Вернуться с обеда';
      lunchBtn.className = '';
      lunchBtn.disabled = false;
      return;
    }
    lunchTimer.hidden = true;
    lunchBtn.className = 'btn-ghost';
    if (!lunchAvailable) {
      lunchBtn.disabled = true;
      lunchBtn.textContent = 'Обед недоступен';
      lunchSub.textContent = !dayOpen
        ? 'Обед можно взять во время рабочего дня.'
        : 'Лимит обеда на сегодня исчерпан.';
    } else {
      lunchBtn.disabled = false;
      lunchBtn.textContent = 'Уйти на обед';
      lunchSub.textContent = 'Время обеда засчитывается в рабочее. Доступно сегодня: '
        + Math.floor(lunchAllowance / 60) + ' мин из ' + Math.floor(lunchMax / 60) + '.';
    }
  }
  setInterval(renderLunch, 1000);

  lunchBtn.addEventListener('click', async () => {
    lunchBtn.disabled = true;
    lunchMsg.textContent = '';
    try {
      const d = await post(onLunch ? LUNCH_END_URL : LUNCH_START_URL);
      if (d.status) applyLunch(d.status);
      if (d.ok === false && d.error) lunchMsg.textContent = d.error;
    } catch (e) {
      lunchMsg.textContent = 'Нет связи с сервером. Попробуйте ещё раз.';
    }
    refreshStatus();
  });

  const idleBtn = $('idleBtn'), bgNote = $('bgNote');
  const hasApi = 'IdleDetector' in window;

  function renderIdleUi() {
    const bgMin = Math.round(BG_INACTIVITY_MS / 60000), fgMin = Math.round(INACTIVITY_MS / 60000);
    if (idleDetector) {
      bgNote.textContent = 'Работа в других программах учитывается: включён системный контроль активности.';
      idleBtn.hidden = true;
    } else if (hasApi && SECURE_CONTEXT) {
      bgNote.textContent = 'Чтобы работа в других программах и свёрнутый браузер не считались бездействием, разрешите браузеру следить за активностью.';
      idleBtn.hidden = false;
    } else {
      bgNote.textContent = (SECURE_CONTEXT
        ? 'Браузер не умеет следить за активностью вне вкладки. '
        : 'Сайт открыт без HTTPS, поэтому браузер не позволяет следить за активностью вне вкладки. ')
        + 'Пока вкладка свёрнута, авто-выход сработает через ' + bgMin + ' мин вместо ' + fgMin + '.';
      idleBtn.hidden = true;
    }
  }

  async function startIdleDetector() {
    try {
      const d = new IdleDetector();
      d.addEventListener('change', () => {
        if (d.userState === 'active' && d.screenState === 'unlocked') lastActivity = Date.now();
      });
      await d.start({ threshold: 60000 });
      idleDetector = d;
    } catch (e) {
      idleDetector = null;
    }
    renderIdleUi();
  }

  idleBtn.addEventListener('click', async () => {
    try {
      const permission = await IdleDetector.requestPermission();
      if (permission === 'granted') {
        await startIdleDetector();
      } else {
        bgNote.textContent = 'Доступ не разрешён. Его можно включить в настройках сайта в браузере.';
      }
    } catch (e) {  }
  });

  if (hasApi && SECURE_CONTEXT && navigator.permissions) {
    navigator.permissions.query({ name: 'idle-detection' })
      .then(r => { if (r.state === 'granted') startIdleDetector(); })
      .catch(() => {});
  }
  renderIdleUi();

  applyLunch(<?= json_encode($status, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
})();
</script>

<script>
(function () {
  const $ = id => document.getElementById(id);
  const breakBtn = $('breakBtn'), breakSub = $('breakSub'), breakTimer = $('breakTimer'),
        breakMsg = $('breakMsg'), breakStats = $('breakStats'),
        windowsCard = $('breakWindowsCard'), windowsList = $('breakWindows');
  const BREAK_START_URL = '<?= BASE_URL ?>index.php?route=employee/break/start';
  const BREAK_END_URL   = '<?= BASE_URL ?>index.php?route=employee/break/end';
  const FORCE_LABELS    = <?= json_encode($forceReasons ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  let breakUntilMs = 0;
  let breakAvailable = false;
  let breakActive = false;
  let breakMode = 'none';
  let breakMaxSeconds = <?= (int) \App\Core\Settings::breakTotal() ?>;
  let breakUsedSeconds = 0;
  let breakCount = 0;
  let breakCountMax = <?= (int) $breakCountMax ?>;
  let dayOpen = false;

  const mmss = sec => {
    sec = Math.max(0, Math.floor(sec));
    return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
  };
  const post = (url, body) => fetch(url, {
    method: 'POST', headers: FORM_HEADERS, body: body || '',
  }).then(r => r.json());

  window.applyBreak = function (s) {
    const was = onBreak;
    onBreak = !!s.on_break;
    breakActive = onBreak;
    breakAvailable = !!s.break_available;
    breakMode = s.break_mode || 'none';
    breakMaxSeconds = s.break_max_seconds || 600;
    breakUsedSeconds = Math.max(0, breakMaxSeconds - (s.break_remaining_seconds || 0));
    breakCount = s.break_count || 0;
    breakCountMax = s.break_count_max || 3;
    dayOpen = s.status !== 'finished' && !!s.last_login_at;
    window.__breakSegments = s.break_segments || [];

    if (breakActive) {
      breakUntilMs = Date.now() + (s.break_remaining_seconds || 0) * 1000;
      lastActivity = Date.now();
    } else if (was) {
      lastActivity = Date.now();
    }

    renderBreak(s);
    renderWindows(s.break_windows || []);
    if (window.renderMyDay) window.renderMyDay();
  };

  function renderBreak(s) {
    breakMsg.textContent = '';
    if (breakActive) {
      breakTimer.hidden = false;
      breakBtn.disabled = false;
      breakBtn.textContent = 'Завершить перерыв';
      breakBtn.className = '';
      breakSub.textContent = 'Вы на перерыве до ' + (s.break_until || '') + '. Авто-выход отключён.';
    } else {
      breakTimer.hidden = true;
      breakBtn.className = 'btn-ghost';
      if (!breakAvailable) {
        breakBtn.disabled = true;
        breakBtn.textContent = 'Перерыв недоступен';
        breakSub.textContent = !dayOpen
          ? 'Перерыв можно взять во время рабочего дня.'
          : (breakMode === 'scheduled'
              ? 'Сейчас нет доступного окна перерыва. Следующее — по графику.'
              : (breakMode === 'none'
                  ? 'Администратор не задал окна перерывов. Перерывы недоступны.'
                  : 'Лимит перерывов на сегодня исчерпан.'));
      } else {
        breakBtn.disabled = false;
        breakBtn.textContent = 'Сделать перерыв';
        breakSub.textContent = breakMode === 'scheduled'
          ? 'Сейчас доступно окно регламентированного перерыва.'
          : 'Используйте перерыв, пока доступно время.';
      }
    }

    if (breakMode === 'scheduled') {
      breakStats.textContent = 'Регламентированные перерывы: ' + breakCount + ' из ' + breakCountMax + ' за день.';
    } else if (breakMode === 'none') {
      breakStats.textContent = '';
    } else {
      breakStats.textContent = 'Использовано ' + Math.round(breakUsedSeconds / 60) + ' мин из '
        + Math.round(breakMaxSeconds / 60) + ' · перерывов ' + breakCount + ' из ' + breakCountMax + '.';
    }
  }

  function renderWindows(list) {
    if (!list.length) {
      windowsCard.hidden = true;
      windowsList.innerHTML = '';
      return;
    }
    windowsCard.hidden = false;
    const statusText = { available: 'доступен', upcoming: 'скоро', used: 'использован', expired: 'сгорел', active: 'идёт' };
    const statusTag = { available: 'ok', upcoming: 'idle', used: 'idle', expired: 'warn', active: 'ok' };
    windowsList.innerHTML = list.map(w =>
      '<li><strong>' + escN(w.label) + '</strong> <span class="muted">' + escN(w.start) + '–' + escN(w.end)
      + ' · ' + (w.minutes || 0) + ' мин</span> <span class="tag tag--' + (statusTag[w.status] || 'idle') + '">'
      + (statusText[w.status] || w.status) + '</span></li>'
    ).join('');
  }

  setInterval(() => {
    if (!breakActive) return;
    const left = (breakUntilMs - Date.now()) / 1000;
    if (left <= 0) {
      breakActive = false;
      onBreak = false;
      lastActivity = Date.now();
      refreshStatus();
      return;
    }
    breakTimer.textContent = mmss(left);
  }, 500);

  breakBtn.addEventListener('click', async () => {
    breakBtn.disabled = true;
    breakMsg.textContent = '';
    try {
      const d = await post(breakActive ? BREAK_END_URL : BREAK_START_URL);
      if (d.status) {
        applyBreak(d.status);
        applyLunch(d.status);
      }
      if (d.ok === false && d.error) breakMsg.textContent = d.error;
    } catch (e) {
      breakMsg.textContent = 'Нет связи с сервером. Попробуйте ещё раз.';
    }
    refreshStatus();
  });

  window.applyBreak(<?= json_encode($status, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);

  const wpBtn = $('workplaceBtn'), wpSelect = $('workplaceSelect'),
        wpNow = $('workplaceNow'), wpMsg = $('workplaceMsg'),
        wpPick = $('workplacePickCtl'), wpMoveCtl = $('workplaceMoveCtl'),
        wpArrive = $('workplaceArriveBtn'), wpTimer = $('workplaceTimer'),
        wpNote = $('workplaceMoveNote');
  const WP_MOVE_URL   = '<?= BASE_URL ?>index.php?route=employee/workplace/move';
  const WP_ARRIVE_URL = '<?= BASE_URL ?>index.php?route=employee/workplace/arrive';
  const WP_STATE_URL  = '<?= BASE_URL ?>index.php?route=employee/workplace/state';

  let wpState = <?= json_encode($workplace['state'] ?? null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  let wpDeadlineMs = 0;

  function wpMessage(text, ok) {
    if (!wpMsg) return;
    wpMsg.style.color = ok ? 'var(--accent)' : 'var(--danger)';
    wpMsg.textContent = text || '';
  }

  function wpSetState(state) {
    if (!state) return;
    wpState = state;

    if (wpPick) wpPick.hidden = !!state.moving;
    if (wpMoveCtl) wpMoveCtl.hidden = !state.moving;

    if (wpNow) {
      if (state.moving) {
        wpNow.textContent = (state.to_name ? 'Переход на «' + state.to_name + '»' : 'Переход')
          + (state.from_name ? ' с «' + state.from_name + '»' : '');
      } else {
        wpNow.textContent = state.current ? ('Сейчас: ' + state.current) : 'Место не выбрано';
      }
    }

    if (wpNote) {
      wpNote.style.display = state.moving ? '' : 'none';
      if (state.moving) {
        wpNote.textContent = state.overdue
          ? 'Время на переход истекло. Нажмите «Я на месте» — администратор увидит отметку.'
          : 'Подтвердите прибытие до ' + (state.arrive_by || '') + '.';
      }
    }

    if (wpSelect && state.current_id) {
      wpSelect.value = String(state.current_id);
    }

    if (state.moving) {
      wpDeadlineMs = Date.now() + Math.max(0, Number(state.seconds_left) || 0) * 1000;
      if (wpTimer) wpTimer.textContent = mmss(Math.max(0, Number(state.seconds_left) || 0));
    }
  }

  function wpRenderTimer() {
    if (!wpState || !wpState.moving || !wpTimer) return;
    const left = Math.max(0, Math.round((wpDeadlineMs - Date.now()) / 1000));
    wpTimer.textContent = mmss(left);
    if (left <= 0 && !wpState.overdue) {
      wpState.overdue = true;
      if (wpNote) {
        wpNote.textContent = 'Время на переход истекло. Нажмите «Я на месте» — администратор увидит отметку.';
      }
    }
  }

  if (wpBtn && wpSelect) {
    wpBtn.addEventListener('click', async () => {
      if (!wpSelect.value) return;
      wpBtn.disabled = true;
      wpMessage('', true);
      try {
        const res = await post(WP_MOVE_URL, 'workplace_id=' + encodeURIComponent(wpSelect.value));
        if (res.state) wpSetState(res.state);
        if (res.ok) {
          wpMessage(res.same
            ? 'Вы уже на этом месте.'
            : 'Переход начат. Успейте подтвердить прибытие.', true);
        } else {
          wpMessage(res.error || 'Не удалось сохранить перемещение.', false);
        }
      } catch (e) {
        wpMessage('Нет связи с сервером. Попробуйте ещё раз.', false);
      }
      wpBtn.disabled = false;
    });
  }

  if (wpArrive) {
    wpArrive.addEventListener('click', async () => {
      wpArrive.disabled = true;
      wpMessage('', true);
      try {
        const res = await post(WP_ARRIVE_URL);
        if (res.state) wpSetState(res.state);
        if (res.ok) {
          wpMessage(res.late
            ? 'Прибытие подтверждено с опозданием. Администратор увидит отметку.'
            : 'Прибытие подтверждено.', true);
        } else {
          wpMessage(res.error || 'Не удалось подтвердить прибытие.', false);
        }
      } catch (e) {
        wpMessage('Нет связи с сервером. Попробуйте ещё раз.', false);
      }
      wpArrive.disabled = false;
    });
  }

  wpSetState(wpState);
  setInterval(wpRenderTimer, 500);

  async function refreshWorkplace() {
    try {
      const res = await fetch(WP_STATE_URL);
      const data = await res.json();
      if (data.ok && data.state) wpSetState(data.state);
    } catch (e) {}
  }
  setInterval(refreshWorkplace, 20000);

  const forceForm = $('forceForm');
  if (forceForm) {
    const forceReason = $('forceReason'), forceComment = $('forceComment'),
          forceMsg = $('forceMsg'), forceBtn = $('forceBtn'), forceHistory = $('forceHistory');

    function renderForceHistory(list) {
      if (!list || !list.length) {
        forceHistory.innerHTML = '<div class="empty-state">Сообщений пока нет.</div>';
        return;
      }
      forceHistory.innerHTML = '<ul class="compact-list">' + list.map(f => {
        const created = String(f.created_at || '').slice(0, 16).replace(' ', ' · ');
        return '<li><strong>' + escN(FORCE_LABELS[f.reason] || f.reason) + '</strong> '
          + '<span class="tag tag--' + (f.status === 'open' ? 'warn' : 'ok') + '">'
          + (f.status === 'open' ? 'на рассмотрении' : 'принято') + '</span> '
          + '<span class="muted">' + escN(created) + '</span>'
          + '<span class="muted">' + escN(f.comment) + '</span></li>';
      }).join('') + '</ul>';
    }

    forceForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      forceMsg.textContent = '';
      forceMsg.style.color = 'var(--danger)';
      const comment = forceComment.value.trim();
      if (!forceReason.value) {
        forceMsg.textContent = 'Выберите причину.';
        return;
      }
      if (comment.length < 3) {
        forceMsg.textContent = 'Напишите короткий комментарий (минимум 3 символа).';
        return;
      }
      forceBtn.disabled = true;
      try {
        const res = await post('<?= BASE_URL ?>index.php?route=employee/force-majeure',
          'reason=' + encodeURIComponent(forceReason.value) + '&comment=' + encodeURIComponent(comment));
        if (res.ok) {
          forceComment.value = '';
          forceReason.value = '';
          forceMsg.style.color = 'var(--accent)';
          forceMsg.textContent = 'Сообщение отправлено администратору.';
          renderForceHistory(res.history || []);
        } else {
          forceMsg.textContent = res.error || 'Не удалось отправить сообщение.';
        }
      } catch (e) {
        forceMsg.textContent = 'Нет связи с сервером. Попробуйте ещё раз.';
      }
      forceBtn.disabled = false;
    });
  }
})();
</script>
