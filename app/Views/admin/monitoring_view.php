<h1>Экран сотрудника — <?= htmlspecialchars($employee['full_name']) ?></h1>
<p class="lede"><a href="<?= BASE_URL ?>index.php?route=admin/monitoring">← ко всем сотрудникам</a></p>

<div class="card" style="padding:0; overflow:hidden;">
  <img id="liveFrame"
       src="<?= BASE_URL ?>index.php?route=admin/monitoring/frame&id=<?= (int) $id ?>&t=0"
       style="width:100%; display:block;" alt="">
</div>

<script>

setInterval(() => {
  const img = document.getElementById('liveFrame');
  const base = img.src.split('&t=')[0];
  img.src = base + '&t=' + Date.now();
}, 1500);
</script>
