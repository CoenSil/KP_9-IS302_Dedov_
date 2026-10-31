<h1>Таблицы БД</h1>
<p class="lede">Прямое добавление записей в любую из четырёх таблиц системы — для случаев, не покрытых обычными формами (например, новое подразделение или ручная правка табеля).</p>

<div class="screen-grid">
  <?php foreach ($tables as $key => $def): ?>
    <div class="screen-tile">
      <div class="screen-tile__name"><?= htmlspecialchars($def['label']) ?></div>
      <p style="color: var(--ink-soft); font-size: 13px; margin: 6px 0 14px;">
        Таблица <code><?= htmlspecialchars($def['table']) ?></code>,
        <?= count($def['fields']) ?> поле(й) в форме.
      </p>
      <a class="btn" 
         href="<?= BASE_URL ?>index.php?route=admin/tables/create&key=<?= urlencode($key) ?>">
        Добавить запись
      </a>
    </div>
  <?php endforeach; ?>
</div>

<p style="margin-top: 24px; color: var(--ink-soft); font-size: 13px;">
  Для сотрудников и должностей проще пользоваться отдельной формой
  <a href="<?= BASE_URL ?>index.php?route=admin/employees/create">«Добавить сотрудника»</a> —
  там сразу видна связь должности с подразделением.
</p>
