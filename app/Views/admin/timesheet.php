<div class="form-narrow">
<h1>Табель Т-13</h1>
<p class="lede">Экспорт посещаемости за выбранную неделю в Excel — по мотивам унифицированной формы Т-13.</p>

<div class="card">
  <h2>Формирование за период</h2>
  <form method="get" action="<?= BASE_URL ?>index.php">
    <input type="hidden" name="route" value="admin/timesheet/export">
    <div class="field">
      <label for="week">Неделя</label>
      <input type="week" id="week" name="week" value="<?= htmlspecialchars($defaultWeek) ?>" required>
    </div>
    <div class="field">
      <label for="department_id">Подразделение</label>
      <select id="department_id" name="department_id"
              >
        <option value="">Все подразделения</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['value'] ?>"><?= htmlspecialchars($d['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit">Скачать .xlsx</button>
  </form>
</div>

<div class="card" style="margin-top:20px;">
  <h2>Что в отчёте</h2>
  <p style="color:var(--ink-soft); margin:0;">
    На сотрудника — две строки: код явки (Я — явка, Н — неявка, если за
    день нет записи о входе) и отработанные часы. Опоздания подсвечены
    жёлтым, переработка больше часа сверх планового времени ухода —
    розовым. Итоги по дням и часам — в последних двух колонках.
  </p>
</div>
</div>
