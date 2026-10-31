<?php $h = fn ($s) => htmlspecialchars((string) $s); ?>
<div class="pagehead"><div><h1>Учебные группы</h1><p class="pagehead__sub">Студент состоит ровно в одной группе. Преподавателям группы назначаются на странице группы.</p></div></div>

<?php include __DIR__ . '/_flash.php'; ?>

<div class="card">
  <?php if (empty($groups)): ?>
    <div class="empty-state">Групп пока нет. Создайте первую.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Группа</th><th>Студентов</th><th>Преподаватели</th><th>Инвайт-код</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($groups as $g): ?>
          <tr>
            <td><?= $h($g['name']) ?></td>
            <td><?= (int) $g['students_count'] ?></td>
            <td><?= $g['teachers'] ? $h($g['teachers']) : '<span class="card__hint">не назначены</span>' ?></td>
            <td><code><?= $h(\App\Models\Group::formatCode($g['invite_code'])) ?></code></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>index.php?route=school/group&id=<?= (int) $g['id'] ?>">Открыть</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>index.php?route=school/groups" class="stack" style="margin-top:18px;">
    <div class="field">
      <label for="name">Новая группа</label>
      <input type="text" id="name" name="name" placeholder="Например, ИС-21" maxlength="255" required>
    </div>
    <button type="submit">Создать группу</button>
  </form>
</div>
