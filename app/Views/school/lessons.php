<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$kinds = \App\Models\Lesson::KINDS;
$statusTag = ['scheduled' => ['Запланировано', 'idle'], 'open' => ['Идёт', 'ok'], 'finished' => ['Завершено', 'idle'], 'missed' => ['Не проводилось', 'warn']];
$row = function (array $l, bool $deletable) use ($h, $kinds, $statusTag) {
    $st = \App\Models\Lesson::status($l);
    ob_start(); ?>
    <tr>
      <td><?= $h(date('d.m.Y', strtotime($l['scheduled_start']))) ?><br><span class="card__hint"><?= $h(date('H:i', strtotime($l['scheduled_start']))) ?>–<?= $h(date('H:i', strtotime($l['scheduled_end']))) ?></span></td>
      <td><?= $h($l['title']) ?></td>
      <td><?= $h($kinds[$l['kind']] ?? $l['kind']) ?></td>
      <td><?= $h($l['room']) ?></td>
      <td><?= $h($l['teacher_name']) ?></td>
      <td><?= $h($l['group_names']) ?></td>
      <td><span class="tag tag--<?= $statusTag[$st][1] ?>"><?= $h($statusTag[$st][0]) ?></span><?= $st === 'open' || $st === 'finished' ? ' ' . (int) $l['present_count'] . '/' . (int) $l['students_count'] : '' ?></td>
      <td class="stack">
        <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>index.php?route=school/lesson&id=<?= (int) $l['id'] ?>">Открыть</a>
        <?php if ($deletable && empty($l['started_at'])): ?>
          <form method="post" action="<?= BASE_URL ?>index.php?route=school/lessons/delete" onsubmit="return confirm('Удалить занятие из расписания?');">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <button type="submit" class="btn-danger-ghost btn-sm">Удалить</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php return ob_get_clean();
};
?>
<div class="pagehead"><div><h1>Расписание</h1><p class="pagehead__sub">Занятия назначает администрация. Преподаватель только проводит их.</p></div></div>

<?php include __DIR__ . '/_flash.php'; ?>
<?php if (!empty($errors)): ?>
  <div class="error"><?php foreach ($errors as $e): ?><div><?= $h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <h2>Новое занятие</h2>
  <?php if (empty($teachers) || empty($groups)): ?>
    <div class="empty-state">Чтобы составить расписание, добавьте <a href="<?= BASE_URL ?>index.php?route=school/people">преподавателя</a> и <a href="<?= BASE_URL ?>index.php?route=school/groups">группу</a>, затем назначьте группу преподавателю.</div>
  <?php else: ?>
  <form method="post" action="<?= BASE_URL ?>index.php?route=school/lessons" id="lessonForm">
    <div class="stack">
      <div class="field">
        <label for="title">Название занятия</label>
        <input type="text" id="title" name="title" value="<?= $h($v['title']) ?>" maxlength="255" placeholder="Например, Базы данных" required>
      </div>
      <div class="field">
        <label for="kind">Тип</label>
        <select id="kind" name="kind">
          <?php foreach ($kinds as $k => $label): ?>
            <option value="<?= $h($k) ?>" <?= $v['kind'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="room">Аудитория</label>
        <input type="text" id="room" name="room" value="<?= $h($v['room']) ?>" maxlength="64" placeholder="305" required>
      </div>
    </div>
    <div class="stack" style="margin-top:12px;">
      <div class="field">
        <label for="date">Дата</label>
        <input type="date" id="date" name="date" value="<?= $h($v['date']) ?>" required>
      </div>
      <div class="field">
        <label for="start">Начало</label>
        <input type="time" id="start" name="start" value="<?= $h($v['start']) ?>" required>
      </div>
      <div class="field">
        <label for="end">Окончание</label>
        <input type="time" id="end" name="end" value="<?= $h($v['end']) ?>" required>
      </div>
      <div class="field">
        <label for="late">Опоздание после, минут</label>
        <input type="number" id="late" name="late" value="<?= $h($v['late']) ?>" min="0" max="120" required>
      </div>
    </div>
    <div class="field" style="margin-top:12px;">
      <label for="teacher_id">Преподаватель</label>
      <select id="teacher_id" name="teacher_id" required>
        <option value="">— выберите —</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (string) $v['teacher_id'] === (string) $t['id'] ? 'selected' : '' ?>><?= $h($t['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <fieldset class="groups-pick">
      <legend>Группы (для лекции можно выбрать несколько)</legend>
      <p class="card__hint" id="groupsHint">Сначала выберите преподавателя: доступны только его группы.</p>
      <?php foreach ($groups as $g): ?>
        <label class="check" data-group="<?= (int) $g['id'] ?>">
          <input type="checkbox" name="groups[]" value="<?= (int) $g['id'] ?>" <?= in_array((int) $g['id'], $v['groups'], true) ? 'checked' : '' ?>> <?= $h($g['name']) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <button type="submit" style="margin-top:14px;">Добавить в расписание</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Предстоящие и идущие</h2>
  <?php if (empty($upcoming)): ?>
    <div class="empty-state">Занятий в расписании нет.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Дата</th><th>Занятие</th><th>Тип</th><th>Ауд.</th><th>Преподаватель</th><th>Группы</th><th>Статус</th><th></th></tr></thead>
      <tbody><?php foreach ($upcoming as $l) { echo $row($l, true); } ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php if (!empty($past)): ?>
<div class="card">
  <h2>Прошедшие</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Дата</th><th>Занятие</th><th>Тип</th><th>Ауд.</th><th>Преподаватель</th><th>Группы</th><th>Статус</th><th></th></tr></thead>
    <tbody><?php foreach ($past as $l) { echo $row($l, true); } ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<script>
(function () {
  var map = <?= json_encode($teacherMap, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?>;
  var teacher = document.getElementById('teacher_id');
  var hint = document.getElementById('groupsHint');
  if (!teacher) return;
  var items = document.querySelectorAll('[data-group]');

  function apply() {
    var allowed = map[teacher.value] || [];
    var any = false;
    items.forEach(function (el) {
      var ok = allowed.indexOf(parseInt(el.getAttribute('data-group'), 10)) !== -1;
      var input = el.querySelector('input');
      el.style.display = ok ? '' : 'none';
      input.disabled = !ok;
      if (!ok) input.checked = false;
      if (ok) any = true;
    });
    hint.textContent = !teacher.value
      ? 'Сначала выберите преподавателя: доступны только его группы.'
      : (any ? 'Отметьте группы, которые будут на занятии.' : 'Этому преподавателю не назначены группы. Назначьте их на странице группы.');
  }
  teacher.addEventListener('change', apply);
  apply();
})();
</script>
