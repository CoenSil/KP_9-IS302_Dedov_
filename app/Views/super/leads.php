<h1>Заявки с сайта</h1>
<p class="lede">Заявки с лендинга. Если таблица ещё не создана, выполните миграцию из папки sql.</p>

<p><a class="btn" href="<?= BASE_URL ?>index.php?route=super/companies">К компаниям</a></p>

<div class="card" style="overflow-x:auto;">
  <table>
    <thead>
      <tr><th>Дата</th><th>Имя</th><th>Школа</th><th>Контакт</th><th>Сообщение</th></tr>
    </thead>
    <tbody>
    <?php foreach ($leads as $lead): ?>
      <tr>
        <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string) $lead['created_at']))) ?></td>
        <td><?= htmlspecialchars((string) $lead['name']) ?></td>
        <td><?= htmlspecialchars((string) ($lead['school'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string) $lead['contact']) ?></td>
        <td><?= nl2br(htmlspecialchars((string) ($lead['message'] ?? ''))) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($leads)): ?>
      <tr><td colspan="5">Заявок пока нет.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
