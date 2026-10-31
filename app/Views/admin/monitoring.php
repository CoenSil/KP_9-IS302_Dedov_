<h1>Мониторинг экрана</h1>
<p class="lede">
  Живой просмотр экрана сотрудников, включивших трансляцию у себя в кабинете.
  Обновление превью — раз в 2 секунды, статус — раз в 3 секунды.
</p>

<div class="screen-grid" id="screenGrid">
  <?php foreach ($employees as $e): ?>
    <div class="screen-tile" data-employee="<?= (int) $e['id'] ?>">
      <a href="<?= BASE_URL ?>index.php?route=admin/monitoring/view&id=<?= (int) $e['id'] ?>"
         style="text-decoration:none; color:inherit;">
        <div class="screen-tile__preview" style="padding:0; overflow:hidden;">
          <img class="screen-frame" loading="lazy"
               src="<?= BASE_URL ?>index.php?route=admin/monitoring/frame&id=<?= (int) $e['id'] ?>&t=0"
               style="width:100%; height:100%; object-fit:cover;" alt="">
        </div>
      </a>
      <div class="screen-tile__name"><?= htmlspecialchars($e['full_name']) ?></div>
      <span class="status-pill status-<?= $e['screen_live'] ? 'working' : 'not_working' ?> live-pill" style="margin-top:6px;">
        <?= $e['screen_live'] ? 'трансляция идёт' : 'не транслирует' ?>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (empty($employees)): ?>
    <div class="empty-state">Сотрудники не найдены.</div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h2>Как это работает</h2>
  <p style="color:var(--ink-soft); margin:0;">
    Сотрудник сам включает трансляцию кнопкой на своём дашборде — браузер
    честно спрашивает у него разрешение и какой именно экран/окно показывать
    (это системный диалог, обойти его нельзя). Пока трансляция включена,
    браузер сотрудника раз в ~2 секунды шлёт сюда JPEG-кадр. Если кадра нет
    дольше 10 секунд — считаем, что трансляция выключена (вкладка закрыта,
    сотрудник сам остановил, либо нажал «Stop sharing» в диалоге браузера).
    Плитка при этом не «зависает» в старом состоянии — превью само переключится
    на «нет сигнала», как только трансляция прервётся, и обратно на картинку,
    как только сотрудник включит её снова, без перезагрузки страницы.
  </p>
</div>

<script>
const STATUS_URL = '<?= BASE_URL ?>index.php?route=admin/monitoring/status';

setInterval(() => {
  document.querySelectorAll('.screen-frame').forEach(img => {
    const base = img.src.split('&t=')[0];
    img.src = base + '&t=' + Date.now();
  });
}, 2000);

async function refreshStatus() {
  try {
    const res = await fetch(STATUS_URL);
    const data = await res.json();
    if (!data.ok) return;
    document.querySelectorAll('#screenGrid .screen-tile').forEach(tile => {
      const id = tile.dataset.employee;
      const live = !!data.live[id];
      const pill = tile.querySelector('.live-pill');
      pill.textContent = live ? 'трансляция идёт' : 'не транслирует';
      pill.className = 'status-pill live-pill ' + (live ? 'status-working' : 'status-not_working');
    });
  } catch (e) {

  }
}
setInterval(refreshStatus, 3000);
</script>
