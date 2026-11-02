<?php
$cssVersion = @filemtime(dirname(__DIR__, 3) . '/public_html/assets/css/landing.css') ?: time();
$base = BASE_URL;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
<meta name="description" content="OpresSence — сервис учёта посещаемости и рабочего времени для онлайн-школ и учебных центров. Табель, группы, расписание, контроль присутствия.">
<link rel="icon" type="image/svg+xml" href="<?= $base ?>favicon.svg">
<link rel="icon" type="image/x-icon" href="<?= $base ?>favicon.ico" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/landing.css?v=<?= $cssVersion ?>">
</head>
<body class="lp">
<header class="lp-header">
  <div class="lp-wrap lp-header__inner">
    <a class="lp-brand" href="<?= $base ?>index.php?route=site/home">
      <svg viewBox="0 0 32 32" aria-hidden="true" width="30" height="30">
        <rect width="32" height="32" rx="8" fill="#2447C9"/>
        <path d="M7 21h18" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <path d="M9 21v-4M13 21v-6M17 21v-4M21 21v-6" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".55"/>
        <path d="M16 8v13" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
      </svg>
      <span>Opres<span class="lp-brand__sub">Sence</span></span>
    </a>
    <nav class="lp-nav" aria-label="Основная навигация">
      <a href="<?= $base ?>index.php?route=site/home#features">Возможности</a>
      <a href="<?= $base ?>index.php?route=site/home#pricing">Тарифы</a>
      <a href="<?= $base ?>index.php?route=site/contacts">Контакты</a>
      <a class="lp-btn lp-btn--ghost" href="<?= $base ?>index.php?route=auth/login">Войти</a>
    </nav>
  </div>
</header>
<main class="lp-main">
