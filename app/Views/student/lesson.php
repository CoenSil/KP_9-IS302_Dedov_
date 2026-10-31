<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$kinds = \App\Models\Lesson::KINDS;
$breakMode = $break['mode'] ?? 'none';
$breakActive = !empty($break['active']);
$breakTotal = max(0, (int) ($break['max'] ?? 0));
$breakCountMax = max(0, (int) ($break['count_max'] ?? 3));
?>
<div class="form-narrow">
<h1><?= $h($lesson['title']) ?></h1>
<p class="lede"><?= $h($kinds[$lesson['kind']] ?? $lesson['kind']) ?> · ауд. <?= $h($lesson['room']) ?> · <?= $h($lesson['teacher_name']) ?> · до <?= $h(date('H:i', strtotime($lesson['scheduled_end']))) ?>
  · <a href="<?= BASE_URL ?>index.php?route=student/dashboard">назад</a></p>

<?php if (!$open): ?>
  <div class="card">
    <h2>Занятие завершено</h2>
    <p class="card__hint">Вы были отмечены в <?= $h(date('H:i', strtotime($row['checked_in_at']))) ?>.</p>
  </div>
<?php else: ?>
  <div class="card breakcard" id="breakCard">
    <div class="breakcard__text">
      <p class="card__label">Перерыв</p>
      <p class="card__hint" id="breakSub" style="margin:0;">
        <?php if ($breakMode === 'free'): ?>
          До <?= (int) $breakCountMax ?> перерывов в день, суммарно не больше
          <?= (int) floor($breakTotal / 60) ?> минут. Время перерыва засчитывается как присутствие.
        <?php else: ?>
          Перерывы для студентов не предусмотрены.
        <?php endif; ?>
      </p>
      <p class="card__hint" id="breakStats" style="margin:6px 0 0;"></p>
      <p class="card__hint" id="breakMsg" role="alert" style="margin:6px 0 0; color: var(--danger);"></p>
    </div>
    <div class="breakcard__ctl">
      <span class="lunch__timer" id="breakTimer" hidden>00:00</span>
      <button type="button" class="btn-ghost" id="breakBtn" <?= $breakMode === 'free' ? '' : 'disabled' ?>>
        <?= $breakActive ? 'Завершить перерыв' : 'Сделать перерыв' ?>
      </button>
    </div>
  </div>

  <div class="card" id="trackCard">
    <span class="tag tag--ok"><?= $row['status'] === 'late' ? 'Опоздали, но отмечены' : 'Вы отмечены' ?></span>
    <p class="card__hint" style="margin-top:12px;">Не сворачивайте эту страницу, не блокируйте экран и не уменьшайте окно браузера до конца занятия: время, пока она открыта на весь экран, засчитывается как активность.</p>
    <div class="stats" style="margin-top:16px;">
      <div class="fact"><div class="fact__label">Активно</div><div class="fact__value" id="mActive">—</div></div>
      <div class="fact"><div class="fact__label">Выходов</div><div class="fact__value" id="mAway">—</div></div>
      <div class="fact"><div class="fact__label">До конца</div><div class="fact__value" id="mLeft">—</div></div>
    </div>
    <p class="card__hint" id="conn" style="margin-top:12px;"></p>
    <p class="card__hint" id="smallNote" style="margin-top:4px; color: var(--danger);" hidden>
      Окно браузера уменьшено больше чем наполовину — это время не засчитывается. Разверните окно.
    </p>
    <p class="card__hint" id="wakeNote" style="margin-top:4px;"></p>
  </div>
<?php endif; ?>
</div>

<?php if ($open): ?>
<script>
(function () {
  var BASE = '<?= BASE_URL ?>index.php?route=';
  var LESSON_ID = <?= (int) $lesson['id'] ?>;
  var URL_HB = BASE + 'student/heartbeat';
  var URL_BREAK_START = BASE + 'student/break/start';
  var URL_BREAK_END = BASE + 'student/break/end';
  var URL_ATTENTION = BASE + 'student/attention';
  var interacted = false;
  var conn = document.getElementById('conn');
  var mActive = document.getElementById('mActive'), mAway = document.getElementById('mAway'), mLeft = document.getElementById('mLeft');
  var smallNote = document.getElementById('smallNote');
  var leftSec = null;

  ['touchstart', 'pointerdown', 'keydown', 'scroll', 'click'].forEach(function (ev) {
    window.addEventListener(ev, function () { interacted = true; }, {passive: true});
  });

  function fmtMin(s) { return Math.floor(s / 60) + ' мин'; }
  function fmtLeft(s) { var m = Math.floor(s / 60), x = s % 60; return m + ':' + (x < 10 ? '0' : '') + x; }
  function mmss(sec) {
    sec = Math.max(0, Math.floor(sec));
    return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
  }
  function tooSmall() {
    return !!(window.OPRES_WINDOW && window.OPRES_WINDOW.tooSmall());
  }

  function body(visible) {
    var fd = new FormData();
    fd.append('lesson_id', LESSON_ID);
    fd.append('visible', visible ? '1' : '0');
    fd.append('interacted', interacted ? '1' : '0');
    interacted = false;
    return fd;
  }

  function update(d) {
    if (!d || !d.ok) { conn.textContent = 'Нет связи с сервером — активность не засчитывается.'; return; }
    if (d.open === false) { location.reload(); return; }
    conn.textContent = '';
    mActive.textContent = fmtMin(d.active);
    mAway.textContent = d.away;
    leftSec = d.left;
    mLeft.textContent = fmtLeft(leftSec);
  }

  function beat() {
    var visible = document.visibilityState === 'visible' && !tooSmall();
    fetch(URL_HB, {method: 'POST', body: body(visible), credentials: 'same-origin'})
      .then(function (r) { return r.json(); }).then(update)
      .catch(function () { update(null); });
  }

  function leave() {
    var fd = body(false);
    if (navigator.sendBeacon) { navigator.sendBeacon(URL_HB, fd); }
    else { fetch(URL_HB, {method: 'POST', body: fd, credentials: 'same-origin', keepalive: true}); }
  }

  /* ---------- Слишком маленькое окно ---------- */
  var lastAttention = 0;
  function checkWindow() {
    var small = tooSmall();
    if (smallNote) smallNote.hidden = !small;
    if (!small) return;
    if (Date.now() - lastAttention < 300000) return;
    lastAttention = Date.now();
    try {
      var fd = new FormData();
      fd.append('lesson_id', LESSON_ID);
      fd.append('reason', 'small_window');
      fetch(URL_ATTENTION, {method: 'POST', body: fd, credentials: 'same-origin'}).catch(function () {});
    } catch (e) {}
  }
  window.addEventListener('resize', checkWindow, {passive: true});
  document.addEventListener('visibilitychange', checkWindow);

  /* ---------- Перерыв ---------- */
  var breakState = <?= json_encode($break ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var breakBtn = document.getElementById('breakBtn');
  var breakSub = document.getElementById('breakSub');
  var breakStats = document.getElementById('breakStats');
  var breakMsg = document.getElementById('breakMsg');
  var breakTimer = document.getElementById('breakTimer');
  var breakUntilMs = 0;

  function renderBreak() {
    if (!breakState) return;
    var mode = breakState.mode || 'none';
    var active = !!breakState.active;
    var remaining = Math.max(0, Number(breakState.remaining) || 0);
    var used = Math.max(0, Number(breakState.used) || 0);
    var total = Math.max(0, Number(breakState.max) || 0);
    var count = Number(breakState.count) || 0;
    var countMax = Number(breakState.count_max) || 0;

    if (mode !== 'free') {
      breakBtn.disabled = true;
      breakBtn.textContent = 'Перерыв недоступен';
      breakSub.textContent = 'Перерывы для студентов не предусмотрены.';
      breakStats.textContent = '';
      breakTimer.hidden = true;
      return;
    }

    if (active) {
      breakBtn.disabled = false;
      breakBtn.textContent = 'Завершить перерыв';
      breakTimer.hidden = false;
      breakSub.textContent = 'Вы на перерыве. Время засчитывается как присутствие.';
    } else {
      breakTimer.hidden = true;
      var can = remaining >= 30 && count < countMax;
      breakBtn.disabled = !can;
      breakBtn.textContent = can ? 'Сделать перерыв' : 'Перерыв недоступен';
      breakSub.textContent = can
        ? 'Доступно ' + Math.floor(remaining / 60) + ' мин на перерыв.'
        : 'Лимит перерывов на сегодня исчерпан.';
    }
    breakStats.textContent = 'Использовано ' + Math.floor(used / 60) + ' мин из '
      + Math.floor(total / 60) + ' · перерывов ' + count + ' из ' + countMax + '.';
  }

  function applyBreak(state) {
    if (!state) return;
    breakState = state;
    if (breakState.active) {
      breakUntilMs = Date.now() + Math.max(0, Number(breakState.remaining) || 0) * 1000;
    }
    renderBreak();
  }

  function post(url) {
    return fetch(url, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: ''})
      .then(function (r) { return r.json(); });
  }

  if (breakBtn) {
    breakBtn.addEventListener('click', function () {
      breakBtn.disabled = true;
      breakMsg.textContent = '';
      post(breakState && breakState.active ? URL_BREAK_END : URL_BREAK_START)
        .then(function (d) {
          if (d.break) applyBreak(d.break);
          if (d.ok === false && d.error) breakMsg.textContent = d.error;
          if (d.ok) beat();
        })
        .catch(function () { breakMsg.textContent = 'Нет связи с сервером. Попробуйте ещё раз.'; })
        .then(function () { renderBreak(); });
    });
  }

  setInterval(function () {
    if (!breakState || !breakState.active) return;
    var left = (breakUntilMs - Date.now()) / 1000;
    if (left <= 0) {
      breakState.active = false;
      renderBreak();
      return;
    }
    breakTimer.textContent = mmss(left);
  }, 500);

  function acquireWake() {
    var note = document.getElementById('wakeNote');
    if (!('wakeLock' in navigator)) {
      note.textContent = 'Ваш браузер не умеет удерживать экран включённым: выключайте блокировку экрана на время занятия.';
      return;
    }
    navigator.wakeLock.request('screen').then(function () {
      note.textContent = 'Экран не погаснет, пока страница открыта.';
    }).catch(function () {
      note.textContent = 'Не удалось удержать экран включённым: отключите автоблокировку на время занятия.';
    });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { acquireWake(); beat(); }
    else { leave(); }
  });
  window.addEventListener('pagehide', leave);

  setInterval(beat, 15000);
  setInterval(checkWindow, 5000);
  setInterval(function () {
    if (leftSec !== null && leftSec > 0) { leftSec--; mLeft.textContent = fmtLeft(leftSec); }
  }, 1000);

  applyBreak(breakState);
  checkWindow();
  acquireWake();
  beat();
})();
</script>
<?php endif; ?>
