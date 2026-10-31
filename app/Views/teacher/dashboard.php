<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$kinds = \App\Models\Lesson::KINDS;
$statusTag = ['scheduled' => ['Запланировано', 'idle'], 'open' => ['Идёт', 'ok'], 'finished' => ['Завершено', 'idle'], 'missed' => ['Не проводилось', 'warn']];
$table = function (array $lessons) use ($h, $kinds, $statusTag) {
    ob_start(); ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Дата и время</th><th>Занятие</th><th>Тип</th><th>Ауд.</th><th>Группы</th><th>Статус</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lessons as $l): $st = \App\Models\Lesson::status($l); ?>
        <tr>
          <td><?= $h(date('d.m.Y', strtotime($l['scheduled_start']))) ?><br><span class="card__hint"><?= $h(date('H:i', strtotime($l['scheduled_start']))) ?>–<?= $h(date('H:i', strtotime($l['scheduled_end']))) ?></span></td>
          <td><?= $h($l['title']) ?></td>
          <td><?= $h($kinds[$l['kind']] ?? $l['kind']) ?></td>
          <td><?= $h($l['room']) ?></td>
          <td><?= $h($l['group_names']) ?></td>
          <td><span class="tag tag--<?= $statusTag[$st][1] ?>"><?= $h($statusTag[$st][0]) ?></span><?= $st === 'open' || $st === 'finished' ? ' ' . (int) $l['present_count'] . '/' . (int) $l['students_count'] : '' ?></td>
          <td><a class="btn <?= $st === 'open' || \App\Models\Lesson::canStart($l) ? '' : 'btn-ghost' ?> btn-sm" href="<?= BASE_URL ?>index.php?route=teacher/lesson&id=<?= (int) $l['id'] ?>"><?= $st === 'open' ? 'Продолжить' : (\App\Models\Lesson::canStart($l) ? 'Начать' : 'Открыть') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php return ob_get_clean();
};
?>
<div class="pagehead">
  <div>
    <h1>Мои занятия</h1>
    <p class="pagehead__sub">Занятия назначает администрация. Вы начинаете их в день занятия и ведёте отметки.</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="<?= $flash['type'] === 'error' ? 'error' : 'banner banner--ok' ?>" role="status"><?= $h($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
  <h2>Предстоящие</h2>
  <?php if (empty($upcoming)): ?>
    <div class="empty-state">Вам пока не назначено занятий.</div>
  <?php else: echo $table($upcoming); endif; ?>
</div>

<?php if (!empty($groups)): ?>
<div class="card">
  <h2>Мои группы</h2>
  <?php foreach ($groups as $g): ?>
    <div class="rowline"><strong><?= $h($g['name']) ?></strong><span class="card__hint"><?= (int) $g['students_count'] ?> студ.</span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($past)): ?>
<div class="card">
  <h2>Прошедшие</h2>
  <?php echo $table($past); ?>
</div>
<?php endif; ?>
