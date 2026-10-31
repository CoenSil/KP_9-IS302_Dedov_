<?php $h = fn ($s) => htmlspecialchars((string) $s); $groupName = array_column($groups, 'name', 'id'); ?>
<div class="pagehead"><div><h1>Люди</h1><p class="pagehead__sub">Администраторы, преподаватели и студенты учебного заведения.</p></div></div>

<?php include __DIR__ . '/_flash.php'; ?>

<?php if (!empty($created)): ?>
  <div class="banner banner--ok" role="status">
    <h2 style="color: var(--accent);"><?= $created['role'] === 'admin' ? 'Администратор' : 'Преподаватель' ?> «<?= $h($created['name']) ?>» добавлен</h2>
    <p style="color: var(--ink-soft);">Вход: код <strong><?= $h($_SESSION['company_code'] ?? '') ?></strong>, email <?= $h($created['email']) ?></p>
    <?php if ($created['password']): ?>
      <p style="color: var(--ink-soft);">Временный пароль (показывается один раз):</p>
      <p class="clock" style="font-size: 26px;"><?= $h($created['password']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
  <div class="error"><?php foreach ($errors as $e): ?><div><?= $h($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <h2>Добавить сотрудника</h2>
  <form method="post" action="<?= BASE_URL ?>index.php?route=school/people">
    <div class="stack">
      <div class="field">
        <label for="role">Роль</label>
        <select id="role" name="role">
          <option value="teacher" <?= $v['role'] === 'teacher' ? 'selected' : '' ?>>Преподаватель (проводит занятия)</option>
          <option value="admin" <?= $v['role'] === 'admin' ? 'selected' : '' ?>>Администратор (управляет, занятия не проводит)</option>
        </select>
      </div>
      <div class="field">
        <label for="full_name">ФИО</label>
        <input type="text" id="full_name" name="full_name" value="<?= $h($v['full_name']) ?>" required>
      </div>
      <div class="field">
        <label for="email">Email (для входа)</label>
        <input type="email" id="email" name="email" value="<?= $h($v['email']) ?>" maxlength="254" inputmode="email" autocapitalize="none" spellcheck="false" required>
      </div>
      <div class="field">
        <label for="password">Пароль (пусто — сгенерировать)</label>
        <input type="text" id="password" name="password" autocomplete="off">
      </div>
    </div>
    <button type="submit" style="margin-top:14px;">Добавить</button>
  </form>
</div>

<div class="card">
  <h2>Администраторы (<?= count($admins) ?>)</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>ФИО</th><th>Email</th><th>Должность</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
      <tr><td><?= $h($a['full_name']) ?><?= (int) $a['id'] === $selfId ? ' <span class="tag tag--idle">это вы</span>' : '' ?></td><td><?= $h($a['email']) ?></td><td><?= $h($a['position_name']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Преподаватели (<?= count($teachers) ?>)</h2>
  <?php if (empty($teachers)): ?>
    <div class="empty-state">Преподавателей пока нет.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>ФИО</th><th>Email</th><th>Группы</th></tr></thead>
      <tbody>
      <?php foreach ($teachers as $t): ?>
        <?php $names = array_map(fn ($gid) => $groupName[$gid] ?? '', $teacherGroups[(int) $t['id']] ?? []); sort($names); ?>
        <tr><td><?= $h($t['full_name']) ?></td><td><?= $h($t['email']) ?></td>
            <td><?= $names ? $h(implode(', ', $names)) : '<span class="card__hint">не назначены (назначаются на странице группы)</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Студенты (<?= count($students) ?>)</h2>
  <?php if (empty($students)): ?>
    <div class="empty-state">Студенты регистрируются сами по инвайт-коду группы.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>ФИО</th><th>Email</th><th>Группа</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td><?= $h($s['full_name']) ?></td>
          <td><?= $h($s['email']) ?></td>
          <td>
            <form method="post" action="<?= BASE_URL ?>index.php?route=school/students/move" class="stack">
              <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="back" value="school/people">
              <select name="group_id" aria-label="Группа">
                <?php if (!$s['group_id']): ?><option value="">— без группы —</option><?php endif; ?>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === (int) $s['group_id'] ? 'selected' : '' ?>><?= $h($g['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-ghost btn-sm">Сохранить</button>
            </form>
          </td>
          <td>
            <?php if ($s['group_id']): ?>
            <form method="post" action="<?= BASE_URL ?>index.php?route=school/students/remove" onsubmit="return confirm('Открепить студента от группы?');">
              <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="back" value="school/people">
              <button type="submit" class="btn-danger-ghost btn-sm">Открепить</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
