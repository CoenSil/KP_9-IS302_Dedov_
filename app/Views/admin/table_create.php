<div class="form-narrow">
<h1><?= htmlspecialchars($def['label']) ?> <span style="color: var(--ink-soft); font-weight:400; font-size:14px;">— новая запись</span></h1>
<p class="lede">
  Таблица <code><?= htmlspecialchars($def['table']) ?></code> ·
  <a href="<?= BASE_URL ?>index.php?route=admin/tables">← ко всем таблицам</a>
</p>

<?php if ($success): ?>
  <div class="banner banner--ok" role="status"><strong>Запись добавлена</strong></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error">
    <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 28px;">
  <form method="post" action="<?= BASE_URL ?>index.php?route=admin/tables/create&key=<?= urlencode($key) ?>">
    <?php foreach ($def['fields'] as $field): ?>
      <div class="field">
        <label for="f_<?= $field['name'] ?>">
          <?= htmlspecialchars($field['label']) ?><?= $field['required'] ? '' : ' (необязательно)' ?>
        </label>

        <?php $val = htmlspecialchars($values[$field['name']] ?? ''); ?>

        <?php if ($field['type'] === 'select'): ?>
          <select id="f_<?= $field['name'] ?>" name="<?= $field['name'] ?>" <?= $field['required'] ? 'required' : '' ?>
                  >
            <option value="">— выберите —</option>
            <?php foreach ($field['options'] as $optValue => $optLabel): ?>
              <option value="<?= htmlspecialchars($optValue) ?>" <?= (($values[$field['name']] ?? '') === $optValue) ? 'selected' : '' ?>>
                <?= htmlspecialchars($optLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($field['type'] === 'fk_select'): ?>
          <select id="f_<?= $field['name'] ?>" name="<?= $field['name'] ?>" <?= $field['required'] ? 'required' : '' ?>
                  >
            <option value="">— выберите —</option>
            <?php foreach ($field['choices'] as $choice): ?>
              <option value="<?= htmlspecialchars($choice['value']) ?>" <?= ((string) ($values[$field['name']] ?? '') === (string) $choice['value']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($choice['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($field['choices'])): ?>
            <p style="color: var(--danger); font-size: 13px; margin-top:6px;">
              В таблице <?= htmlspecialchars($field['fk_table']) ?> пока нет ни одной записи —
              сначала добавьте её там.
            </p>
          <?php endif; ?>

        <?php elseif ($field['type'] === 'password'): ?>
          <input type="text" id="f_<?= $field['name'] ?>" name="<?= $field['name'] ?>"
                 placeholder="Будет сохранён как хэш" <?= $field['required'] ? 'required' : '' ?>>

        <?php else: ?>
          <input type="<?= $field['type'] === 'datetime' ? 'datetime-local' : $field['type'] ?>"
                 id="f_<?= $field['name'] ?>" name="<?= $field['name'] ?>"
                 value="<?= $val ?>" <?= $field['required'] ? 'required' : '' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit">Добавить запись</button>
  </form>
</div>

</div>

<h2>Последние записи</h2>
<div class="card card--flush">
  <?php if (empty($latest)): ?>
    <div class="empty-state">Записей пока нет.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr><?php foreach (array_keys($latest[0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($latest as $row): ?>
          <tr>
            <?php foreach ($row as $colName => $colVal): ?>
              <td>
                <?php if ($colName === 'password_hash'): ?>
                  <span style="color: var(--ink-soft);">••••••••</span>
                <?php else: ?>
                  <?= htmlspecialchars((string) ($colVal ?? '—')) ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
