<?php
$h = fn ($s) => htmlspecialchars((string) $s);
$statusTag = ['scheduled' => ['Запланировано', 'idle'], 'open' => ['Идёт', 'ok'], 'finished' => ['Завершено', 'idle'], 'missed' => ['Не проводилось', 'warn']];
$label = ['present' => ['Пришёл', 'ok'], 'late' => ['Опоздал', 'warn'], 'manual' => ['Отмечен преподавателем', 'ok'], 'absent' => ['Нет', 'bad']];
?>
<div class="pagehead">
  <div>
    <h1><?= $h($lesson['title']) ?></h1>
    <p class="pagehead__sub"><a href="<?= BASE_URL ?>index.php?route=school/lessons">← Расписание</a></p>
  </div>
  <?php if (!empty($lesson['started_at'])): ?>
    <a class="btn btn-ghost" href="<?= BASE_URL ?>index.php?route=school/lesson/export&id=<?= (int) $lesson['id'] ?>">Скачать CSV</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="stats">
    <div class="fact"><div class="fact__label">Тип</div><div class="fact__value"><?= $h(\App\Models\Lesson::KINDS[$lesson['kind']] ?? $lesson['kind']) ?></div></div>
    <div class="fact"><div class="fact__label">Аудитория</div><div class="fact__value"><?= $h($lesson['room']) ?></div></div>
    <div class="fact"><div class="fact__label">Время</div><div class="fact__value"><?= $h(date('d.m H:i', strtotime($lesson['scheduled_start']))) ?>–<?= $h(date('H:i', strtotime($lesson['scheduled_end']))) ?></div></div>
    <div class="fact"><div class="fact__label">Статус</div><div class="fact__value"><?= $h($statusTag[$status][0]) ?></div></div>
  </div>
  <p class="card__hint" style="margin-top:12px;">Преподаватель: <?= $h($lesson['teacher_name']) ?> · Группы: <?= $h($lesson['group_names']) ?></p>
</div>

<?php if (empty($lesson['started_at'])): ?>
  <div class="card"><div class="empty-state">Занятие ещё не начато преподавателем.</div></div>
<?php else: ?>
<div class="card card--flush">
  <div class="table-wrap"><table>
    <thead><tr><th>Студент</th><th>Группа</th><th>Отметка</th><th>Время</th><th>Активность</th><th>Выходов</th></tr></thead>
    <tbody>
    <?php foreach ($roster as $r): $l = $label[$r['status']] ?? [$r['status'], 'idle']; ?>
      <tr>
        <td><?= $h($r['full_name']) ?><?= $r['shared_device'] ? ' <span class="badge-custom">общее устройство</span>' : '' ?></td>
        <td><?= $h($r['group_name']) ?></td>
        <td><span class="tag tag--<?= $l[1] ?>"><?= $h($l[0]) ?></span></td>
        <td><?= $r['checked_in_at'] ? $h(date('H:i', strtotime($r['checked_in_at']))) : '—' ?></td>
        <td><?= $r['active_percent'] === null ? '—' : (int) $r['active_percent'] . '%' ?></td>
        <td><?= $r['status'] === 'absent' ? '—' : (int) $r['away_count'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($roster)): ?><tr><td colspan="6">В группах занятия пока нет студентов.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
