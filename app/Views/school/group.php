<?php $h = fn ($s) => htmlspecialchars((string) $s); $code = \App\Models\Group::formatCode($group['invite_code']); $back = 'school/group&id=' . (int) $group['id']; ?>
<div class="pagehead">
  <div>
    <h1><?= $h($group['name']) ?></h1>
    <p class="pagehead__sub"><a href="<?= BASE_URL ?>index.php?route=school/groups">← Все группы</a></p>
  </div>
</div>

<?php include __DIR__ . '/_flash.php'; ?>

<div class="grid-2">
  <div class="card">
    <h2>Инвайт-код группы</h2>
    <div class="codebox"><?= $h($code) ?></div>
    <p class="card__hint" style="margin-top:12px;">Студент вводит его при регистрации и автоматически закрепляется за этой группой.</p>
    <div class="field" style="margin-top:12px;">
      <label for="regUrl">Ссылка для регистрации</label>
      <input type="text" id="regUrl" value="<?= $h($registerUrl) ?>" readonly onclick="this.select()">
    </div>
    <form method="post" action="<?= BASE_URL ?>index.php?route=school/groups/rotate" onsubmit="return confirm('Старый код перестанет работать. Продолжить?');">
      <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
      <button type="submit" class="btn-ghost btn-sm">Сменить код</button>
    </form>
  </div>

  <div class="card">
    <h2>Преподаватели группы</h2>
    <?php if (empty($teachers)): ?>
      <p class="card__hint">Преподавателей пока нет. <a href="<?= BASE_URL ?>index.php?route=school/people">Добавить</a></p>
    <?php else: ?>
      <form method="post" action="<?= BASE_URL ?>index.php?route=school/groups/teachers">
        <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
        <?php foreach ($teachers as $t): ?>
          <label class="check"><input type="checkbox" name="teachers[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $assigned, true) ? 'checked' : '' ?>> <?= $h($t['full_name']) ?></label>
        <?php endforeach; ?>
        <button type="submit" style="margin-top:12px;">Сохранить</button>
      </form>
      <p class="card__hint" style="margin-top:10px;">Занятия можно ставить в расписание только тем преподавателям, которым назначена группа.</p>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Студенты (<?= count($students) ?>)</h2>
  <?php if (empty($students)): ?>
    <div class="empty-state">Пока никто не зарегистрировался по инвайт-коду.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ФИО</th><th>Email</th><th>Перевести в группу</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><?= $h($s['full_name']) ?></td>
            <td><?= $h($s['email']) ?></td>
            <td>
              <form method="post" action="<?= BASE_URL ?>index.php?route=school/students/move" class="stack">
                <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <select name="group_id" aria-label="Группа">
                  <?php foreach ($allGroups as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === (int) $group['id'] ? 'selected' : '' ?>><?= $h($g['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-ghost btn-sm">Перевести</button>
              </form>
            </td>
            <td>
              <form method="post" action="<?= BASE_URL ?>index.php?route=school/students/remove" onsubmit="return confirm('Открепить студента от группы?');">
                <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                <input type="hidden" name="back" value="<?= $h($back) ?>">
                <button type="submit" class="btn-danger-ghost btn-sm">Открепить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
