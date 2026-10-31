<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$kinds = \App\Models\Lesson::KINDS;
$labels = ['present' => ['Пришёл', 'ok'], 'late' => ['Опоздал', 'warn'], 'manual' => ['Отмечен преподавателем', 'ok']];
$total = count($history);
$attended = count(array_filter($history, fn ($r) => !empty($r['status'])));
?>
<div class="pagehead">
  <div>
    <h1>Моя посещаемость</h1>
    <p class="pagehead__sub"><?= $group ? 'Группа: <strong>' . $h($group['name']) . '</strong> · ' : '' ?>код учебного заведения: <code><?= $h($_SESSION['company_code'] ?? '') ?></code></p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="<?= $flash['type'] === 'error' ? 'error' : 'banner banner--ok' ?>" role="status"><?= $h($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$group): ?>
  <div class="error" role="alert">Вы не прикреплены к группе, поэтому отметиться на занятиях не сможете. Обратитесь к администратору учебного заведения.</div>
<?php endif; ?>

<div class="card">
  <h2>Код занятия</h2>
  <p class="card__hint">Преподаватель показывает код в начале занятия. Введите его: группа определится автоматически.</p>
  <div class="field" style="margin-top:12px;">
    <label for="code" class="sr-only">Код занятия</label>
    <input type="text" id="code" class="codeinput" maxlength="8" autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="ABC123" <?= $group ? '' : 'disabled' ?>>
  </div>
  <p class="card__hint" id="err" role="alert" style="color: var(--danger); min-height: 1.4em;"></p>
  <button type="button" id="go" <?= $group ? '' : 'disabled' ?>>Отметиться</button>
</div>

<?php if (!empty($openLessons)): ?>
<div class="card">
  <h2>Сейчас идут занятия вашей группы</h2>
  <?php foreach ($openLessons as $l): ?>
    <div class="rowline">
      <div>
        <strong><?= $h($l['title']) ?></strong>
        <div class="card__hint"><?= $h($kinds[$l['kind']] ?? $l['kind']) ?> · ауд. <?= $h($l['room']) ?> · <?= $h($l['teacher_name']) ?> · до <?= $h(date('H:i', strtotime($l['scheduled_end']))) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>История<?= $total ? ' · посещено ' . $attended . ' из ' . $total : '' ?></h2>
  <?php if (empty($history)): ?>
    <div class="empty-state">Занятий пока не было.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Дата</th><th>Занятие</th><th>Тип</th><th>Преподаватель</th><th>Статус</th><th>Активность</th></tr></thead>
        <tbody>
        <?php foreach ($history as $r): ?>
          <?php
          $info = $labels[$r['status'] ?? ''] ?? ['Не был', 'bad'];
          $pct = null;
          if (!empty($r['status']) && ($r['status'] !== 'manual' || (int) $r['active_seconds'] > 0)) {
              $end = min(strtotime($r['scheduled_end']), !empty($r['closed_at']) ? strtotime($r['closed_at']) : PHP_INT_MAX, time());
              $span = $end - strtotime($r['checked_in_at']);
              $pct = $span > 0 ? (int) min(100, round($r['active_seconds'] * 100 / $span)) : 100;
          }
          ?>
          <tr>
            <td><?= $h(date('d.m.Y H:i', strtotime($r['scheduled_start']))) ?></td>
            <td><?= $h($r['title']) ?></td>
            <td><?= $h($kinds[$r['kind']] ?? $r['kind']) ?></td>
            <td><?= $h($r['teacher_name']) ?></td>
            <td><span class="tag tag--<?= $info[1] ?>"><?= $h($info[0]) ?></span></td>
            <td><?= $pct === null ? '—' : $pct . '%' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var BASE = '<?= BASE_URL ?>index.php?route=';
  var go = document.getElementById('go'), code = document.getElementById('code'), err = document.getElementById('err');

  function deviceId() {
    try {
      var id = localStorage.getItem('att_device');
      if (!id) {
        var a = new Uint8Array(12);
        crypto.getRandomValues(a);
        id = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        localStorage.setItem('att_device', id);
      }
      return id;
    } catch (e) { return ''; }
  }

  function submit() {
    err.textContent = '';
    go.disabled = true;
    var fd = new FormData();
    fd.append('code', code.value);
    fd.append('device_id', deviceId());
    fetch(BASE + 'student/checkin', {method: 'POST', body: fd, credentials: 'same-origin'})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { location.href = BASE + 'student/lesson&id=' + d.lesson_id; return; }
        err.textContent = d.error || 'Не удалось отметиться.';
        go.disabled = false;
      })
      .catch(function () { err.textContent = 'Нет связи с сервером. Проверьте интернет и повторите.'; go.disabled = false; });
  }

  if (go && !go.disabled) {
    go.addEventListener('click', submit);
    code.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
  }
})();
</script>
