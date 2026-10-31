<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$statusTag = ['scheduled' => ['Запланировано', 'idle'], 'open' => ['Идёт', 'ok'], 'finished' => ['Завершено', 'idle'], 'missed' => ['Не проводилось', 'warn']];
?>
<div class="pagehead">
  <div>
    <h1><?= $h($_SESSION['company_name'] ?? 'Учебное заведение') ?></h1>
    <p class="pagehead__sub">Код для входа сотрудников и студентов: <code><?= $h($_SESSION['company_code'] ?? '') ?></code></p>
  </div>
</div>

<?php include __DIR__ . '/_flash.php'; ?>

<div class="card">
  <div class="stats">
    <div class="fact"><div class="fact__label">Группы</div><div class="fact__value"><?= (int) $stats['groups_count'] ?></div></div>
    <div class="fact"><div class="fact__label">Преподаватели</div><div class="fact__value"><?= (int) $stats['teachers'] ?></div></div>
    <div class="fact"><div class="fact__label">Студенты</div><div class="fact__value"><?= (int) $stats['students'] ?></div></div>
    <div class="fact"><div class="fact__label">Администраторы</div><div class="fact__value"><?= (int) $stats['admins'] ?></div></div>
  </div>
  <?php if ((int) $stats['ungrouped'] > 0): ?>
    <p class="card__hint" style="margin-top:12px;">Студентов без группы: <?= (int) $stats['ungrouped'] ?>. Они не смогут отметиться на занятиях, пока их не прикрепят к группе (раздел «Люди»).</p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head">
    <h2>Проверка присутствия</h2>
    <span class="tag tag--<?= $collapseCount > 0 ? 'warn' : 'ok' ?>"><?= (int) $collapseCount ?> сегодня</span>
  </div>
  <p class="card__hint">
    Если сотрудник или студент не ответил на проверку за 5 минут, приложение считается свёрнутым.
    Такие случаи сохраняются в журнале — разбирайтесь по мере необходимости.
  </p>
  <?php if (empty($collapseFeed)): ?>
    <div class="empty-state">Сегодня пропущенных проверок нет.</div>
  <?php else: ?>
    <ul class="compact-list">
      <?php foreach ($collapseFeed as $mark): ?>
        <li>
          <strong><?= $h($mark['full_name']) ?></strong>
          <span class="muted"><?= $h($mark['position_name'] ?? '') ?> · <?= $h(date('H:i', strtotime($mark['created_at']))) ?></span>
          <span class="muted"><?= $h($mark['detail']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Занятия сегодня</h2>
  <?php if (empty($today)): ?>
    <div class="empty-state">На сегодня занятий нет. <a href="<?= BASE_URL ?>index.php?route=school/lessons">Добавить в расписание</a></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Время</th><th>Занятие</th><th>Тип</th><th>Ауд.</th><th>Преподаватель</th><th>Группы</th><th>Статус</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($today as $l): $st = \App\Models\Lesson::status($l); ?>
          <tr>
            <td><?= $h(date('H:i', strtotime($l['scheduled_start']))) ?>–<?= $h(date('H:i', strtotime($l['scheduled_end']))) ?></td>
            <td><?= $h($l['title']) ?></td>
            <td><?= $h(\App\Models\Lesson::KINDS[$l['kind']] ?? $l['kind']) ?></td>
            <td><?= $h($l['room']) ?></td>
            <td><?= $h($l['teacher_name']) ?></td>
            <td><?= $h($l['group_names']) ?></td>
            <td><span class="tag tag--<?= $statusTag[$st][1] ?>"><?= $h($statusTag[$st][0]) ?></span><?= $st !== 'scheduled' ? ' ' . (int) $l['present_count'] . '/' . (int) $l['students_count'] : '' ?></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>index.php?route=school/lesson&id=<?= (int) $l['id'] ?>">Открыть</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
