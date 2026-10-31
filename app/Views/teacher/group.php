<?php $h = fn ($s) => htmlspecialchars((string) $s); $code = \App\Models\Group::formatCode($group['invite_code']); ?>
<div class="pagehead">
  <div>
    <h1><?= $h($group['name']) ?></h1>
    <p class="pagehead__sub"><a href="<?= BASE_URL ?>index.php?route=teacher/dashboard">← Все группы</a></p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="<?= $flash['type'] === 'error' ? 'error' : 'banner banner--ok' ?>" role="status"><?= $h($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-2">
  <div class="card">
    <h2>Инвайт-код группы</h2>
    <div class="codebox" id="inviteCode"><?= $h($code) ?></div>
    <p class="card__hint" style="margin-top:12px;">Студент вводит его при регистрации или в своём профиле. Код постоянный: он нужен только чтобы вступить в группу.</p>
    <div class="field" style="margin-top:12px;">
      <label for="regUrl">Ссылка для регистрации</label>
      <input type="text" id="regUrl" value="<?= $h($registerUrl) ?>" readonly onclick="this.select()">
    </div>
    <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/groups/rotate" onsubmit="return confirm('Старый код перестанет работать. Продолжить?');">
      <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
      <button type="submit" class="btn-ghost btn-sm">Сменить код</button>
    </form>
  </div>

  <div class="card">
    <h2>Начать занятие</h2>
    <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/lessons/start">
      <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
      <div class="field">
        <label for="title">Тема (необязательно)</label>
        <input type="text" id="title" name="title" maxlength="255" placeholder="Например, Лекция 5">
      </div>
      <div class="field">
        <label for="minutes">Длительность, минут</label>
        <input type="number" id="minutes" name="minutes" value="90" min="5" max="480" required>
      </div>
      <div class="field">
        <label for="late">Опоздание после, минут</label>
        <input type="number" id="late" name="late" value="10" min="0" max="120" required>
      </div>
      <button type="submit">Начать и показать код занятия</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Студенты (<?= count($students) ?>)</h2>
  <?php if (empty($students)): ?>
    <div class="empty-state">Пока никто не присоединился.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ФИО</th><th>Email</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><?= $h($s['full_name']) ?></td>
            <td><?= $h($s['email']) ?></td>
            <td>
              <form method="post" action="<?= BASE_URL ?>index.php?route=teacher/groups/remove-student" onsubmit="return confirm('Удалить студента из группы?');">
                <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn-danger-ghost btn-sm">Убрать</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($lessons)): ?>
<div class="card">
  <h2>Занятия группы</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Дата</th><th>Занятие</th><th>Пришли</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lessons as $l): ?>
        <tr>
          <td><?= $h(date('d.m.Y H:i', strtotime($l['started_at']))) ?></td>
          <td><?= $h($l['title']) ?></td>
          <td><?= (int) $l['present_count'] ?> из <?= (int) $l['students_count'] ?></td>
          <td><a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>index.php?route=teacher/lesson&id=<?= (int) $l['id'] ?>">Открыть</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
