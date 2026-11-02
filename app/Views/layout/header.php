<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>favicon.svg">
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>apple-touch-icon.png">
<?php
$cssPath = dirname(__DIR__, 3) . '/public_html/assets/css/style.css';
$cssVersion = @filemtime($cssPath) ?: time();

$isAuthPage = in_array(($view ?? ''), ['auth/access', 'super/login', 'auth/forgot', 'auth/reset'], true);
$isSuper = !empty($_SESSION['super_id']);
$brandRoute = match ($_SESSION['role'] ?? '') {
    'student' => 'student/dashboard',
    'teacher' => 'teacher/dashboard',
    'admin'   => (($_SESSION['company_type'] ?? '') === 'school') ? 'school/dashboard' : 'admin/dashboard',
    default   => 'employee/dashboard',
};

$currentRoute = $_GET['route'] ?? '';
$navItems = [
    ['admin/dashboard',        'Сотрудники',          ['admin/dashboard', 'admin/employees/schedule']],
    ['admin/org',              'Организация',         ['admin/org']],
    ['admin/employees/create', 'Добавить сотрудника', ['admin/employees/create']],
    ['admin/tables',           'Таблицы БД',          ['admin/tables']],
    ['admin/monitoring',       'Мониторинг',          ['admin/monitoring']],
    ['admin/timesheet',        'Табель Т-13',         ['admin/timesheet']],
];
$fullName = $_SESSION['full_name'] ?? '';
$initials = '';
foreach (array_slice(preg_split('/\s+/u', trim($fullName)) ?: [], 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= $cssVersion ?>">
<script>
window.OPRES_CSRF = <?= json_encode(\App\Core\Csrf::token(), JSON_UNESCAPED_UNICODE) ?>;
/* Все POST-запросы из JS получают заголовок с CSRF-токеном:
   иначе сервер отклонит их как поддельные. */
(function () {
  var nativeFetch = window.fetch;
  if (!nativeFetch) return;
  window.fetch = function (input, init) {
    init = init || {};
    var method = String(init.method || (input && input.method) || 'GET').toUpperCase();
    if (method === 'POST') {
      var token = window.OPRES_CSRF || '';
      if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
        init.headers = new Headers(init.headers);
        init.headers.set('X-CSRF-Token', token);
      } else {
        var merged = {};
        var src = init.headers || {};
        for (var k in src) { if (Object.prototype.hasOwnProperty.call(src, k)) merged[k] = src[k]; }
        merged['X-CSRF-Token'] = token;
        init.headers = merged;
      }
    }
    return nativeFetch.call(this, input, init);
  };
})();
/* Размер окна: общая проверка для страниц сотрудника и студента.
   Если браузер сжат меньше половины экрана, присутствие не засчитывается.
   На телефонах проверка отключена. */
window.OPRES_WINDOW = (function () {
  function fill() {
    return {
      w: window.innerWidth || 0,
      h: window.innerHeight || 0,
      aw: screen.availWidth || 0,
      ah: screen.availHeight || 0
    };
  }
  function tooSmall() {
    var f = fill();
    if (!f.aw || !f.ah || !f.w || !f.h) return false;
    if (f.aw <= 900) return false;
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return false;
    var area = (f.w * f.h) / (f.aw * f.ah);
    return area < 0.5 || f.w < f.aw * 0.5 || f.h < f.ah * 0.5;
  }
  return { fill: fill, tooSmall: tooSmall };
})();
</script>
</head>
<body class="<?= $isAuthPage ? 'auth-shell' : 'app-shell' ?>">
<?php if (!$isAuthPage): ?>
<header class="topbar">
  <a class="brand" href="<?= BASE_URL ?>index.php?route=<?= $isSuper ? 'super/companies' : $brandRoute ?>">
    <svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true">
      <rect width="32" height="32" rx="8" fill="#2447C9"/>
      <path d="M7 21h18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 21v-4M13 21v-6M17 21v-4M21 21v-6" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".55"/>
      <path d="M16 8v13" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
    </svg>
    <span>Opres<span class="brand__sub">Sence</span></span>
  </a>
  <?php if ($isSuper): ?>
  <nav class="topbar__nav" aria-label="Разделы">
    <a href="<?= BASE_URL ?>index.php?route=super/companies" class="is-active" aria-current="page">Компании</a>
    <a href="<?= BASE_URL ?>index.php?route=super/leads" class="<?= (($_GET['route'] ?? '') === 'super/leads') ? 'is-active' : '' ?>">Заявки</a>
  </nav>
  <div class="topbar__user">
    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
    <span class="topbar__who">
      <strong><?= htmlspecialchars($fullName) ?></strong>
      <span>Владелец платформы</span>
    </span>
    <a href="<?= BASE_URL ?>index.php?route=super/logout" class="topbar__logout">Выйти</a>
  </div>
  <?php elseif (!empty($_SESSION['employee_id'])): ?>
  <nav class="topbar__nav" aria-label="Разделы">
    <?php if ($_SESSION['role'] === 'student'): ?>
      <a href="<?= BASE_URL ?>index.php?route=student/dashboard" class="is-active" aria-current="page">Моя посещаемость</a>
    <?php elseif ($_SESSION['role'] === 'teacher'): ?>
      <a href="<?= BASE_URL ?>index.php?route=teacher/dashboard" class="is-active" aria-current="page">Мои занятия</a>
    <?php elseif ($_SESSION['role'] === 'admin' && ($_SESSION['company_type'] ?? '') === 'school'): ?>
      <?php foreach ([['school/dashboard', 'Обзор'], ['school/groups', 'Группы'], ['school/people', 'Люди'], ['school/lessons', 'Расписание']] as [$r, $label]): ?>
        <a href="<?= BASE_URL ?>index.php?route=<?= $r ?>" class="<?= ($currentRoute === $r || ($r === 'school/groups' && $currentRoute === 'school/group') || ($r === 'school/lessons' && $currentRoute === 'school/lesson')) ? 'is-active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    <?php elseif ($_SESSION['role'] === 'admin'): ?>
      <?php foreach ($navItems as [$route, $label, $matches]): ?>
        <a href="<?= BASE_URL ?>index.php?route=<?= $route ?>"
           class="<?= in_array($currentRoute, $matches, true) || (str_starts_with($currentRoute, 'admin/tables') && $route === 'admin/tables') || (str_starts_with($currentRoute, 'admin/monitoring') && $route === 'admin/monitoring') ? 'is-active' : '' ?>"
           <?= (in_array($currentRoute, $matches, true)) ? 'aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    <?php else: ?>
      <a href="<?= BASE_URL ?>index.php?route=employee/dashboard" class="is-active" aria-current="page">Мой день</a>
    <?php endif; ?>
  </nav>
  <div class="topbar__user">
    <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
    <span class="topbar__who">
      <strong><?= htmlspecialchars($fullName) ?></strong>
      <span><?= $_SESSION['role'] === 'student' ? 'Студент' : ($_SESSION['role'] === 'teacher' ? 'Преподаватель' : (($_SESSION['role'] === 'admin') ? ((($_SESSION['company_type'] ?? '') === 'school') ? htmlspecialchars($_SESSION['position'] ?? 'Администратор') : 'Администратор') : 'Сотрудник')) ?><?= !empty($_SESSION['company_name']) ? ' · ' . htmlspecialchars($_SESSION['company_name']) : '' ?></span>
    </span>
    <a href="<?= BASE_URL ?>index.php?route=auth/password" class="topbar__logout" title="Сменить пароль">Пароль</a>
    <a href="<?= BASE_URL ?>index.php?route=auth/logout" class="topbar__logout">Выйти</a>
  </div>
  <?php endif; ?>
</header>
<?php endif; ?>
<main class="page<?= $isAuthPage ? ' page--auth' : '' ?>">
