<h1>Компании</h1>
<p class="lede">Каждая компания работает в изолированном пространстве: свои подразделения, должности, сотрудники, администраторы и табель.</p>

<?php if (!empty($flash)): ?>
  <div class="banner banner--ok" role="status"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<p><a class="btn" href="<?= BASE_URL ?>index.php?route=super/companies/create">Добавить компанию</a></p>

<div class="card" style="overflow-x:auto;">
  <table>
    <thead>
      <tr><th>Название</th><th>Тип</th><th>Код входа</th><th>Сотрудников</th><th>Админов</th><th>Статус</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($companies as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= ($c['type'] ?? 'company') === 'school' ? 'Учебное заведение' : 'Компания' ?></td>
        <td><code><?= htmlspecialchars($c['code']) ?></code></td>
        <td><?= (int) $c['employees_count'] ?></td>
        <td><?= (int) $c['admins_count'] ?></td>
        <td><?= (int) $c['is_active'] === 1 ? 'Активна' : 'Отключена' ?></td>
        <td>
          <form method="post" action="<?= BASE_URL ?>index.php?route=super/companies/toggle"
                onsubmit="return confirm('<?= (int) $c['is_active'] === 1 ? 'Отключить компанию? Все её пользователи потеряют доступ.' : 'Включить компанию?' ?>');">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="btn"><?= (int) $c['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($companies)): ?>
      <tr><td colspan="7">Компаний пока нет.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
