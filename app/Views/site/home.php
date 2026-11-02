<?php $b = BASE_URL; ?>
<section class="lp-hero">
  <div class="lp-wrap lp-hero__inner">
    <div class="lp-hero__text">
      <span class="lp-eyebrow">Для онлайн-школ и учебных центров</span>
      <h1>Посещаемость и рабочее время —<br>без ручных таблиц</h1>
      <p class="lp-lead">
        Преподаватели отмечаются на смене, студенты — на занятиях. Сервис сам считает
        часы, собирает табель и показывает, кто отсутствует прямо сейчас.
      </p>
      <div class="lp-hero__actions">
        <a class="lp-btn" href="<?= $b ?>index.php?route=site/contacts">Попробовать 14 дней</a>
        <a class="lp-btn lp-btn--ghost" href="#features">Что внутри</a>
      </div>
      <p class="lp-note">Без установки, без карты. Настройка занимает около 15 минут.</p>
    </div>
    <div class="lp-hero__card" aria-hidden="true">
      <div class="lp-mock__row lp-mock__row--head">
        <span>Сегодня, 09:12</span>
        <span class="lp-pill lp-pill--ok">12 на смене</span>
      </div>
      <div class="lp-mock__row"><span>Анна К.</span><span class="lp-pill lp-pill--ok">Работает</span></div>
      <div class="lp-mock__row"><span>Игорь П.</span><span class="lp-pill lp-pill--ok">Работает</span></div>
      <div class="lp-mock__row"><span>Мария С.</span><span class="lp-pill lp-pill--warn">Перерыв</span></div>
      <div class="lp-mock__row"><span>Олег В.</span><span class="lp-pill lp-pill--muted">Не вышел</span></div>
      <div class="lp-mock__row lp-mock__row--foot"><span>Табель Т-13</span><span>Excel</span></div>
    </div>
  </div>
</section>

<section class="lp-strip">
  <div class="lp-wrap lp-strip__inner">
    <div><strong>5 минут</strong><span>интервал проверки присутствия</span></div>
    <div><strong>Т-13</strong><span>табель в привычной форме</span></div>
    <div><strong>4 роли</strong><span>директор, админ, преподаватель, студент</span></div>
    <div><strong>0 установок</strong><span>работает в браузере</span></div>
  </div>
</section>

<section class="lp-section" id="features">
  <div class="lp-wrap">
    <h2>Что уже работает</h2>
    <p class="lp-section__lead">Не прототип: сервис используют для ежедневного учёта. Ниже — то, что включено в подписку.</p>

    <div class="lp-grid">
      <article class="lp-card">
        <h3>Смены и перерывы</h3>
        <p>Отметка прихода и ухода, обед, короткие перерывы с лимитом. Забытые смены закрываются автоматически.</p>
      </article>
      <article class="lp-card">
        <h3>Проверка присутствия</h3>
        <p>Раз в 20 минут сервис просит подтвердить, что человек у экрана. Прогул не спишется, если его не было.</p>
      </article>
      <article class="lp-card">
        <h3>Табель Т-13</h3>
        <p>Готовый табель за месяц по каждому сотруднику и подразделению. Выгрузка в Excel одной кнопкой.</p>
      </article>
      <article class="lp-card">
        <h3>Группы и расписание</h3>
        <p>Группы с инвайт-кодами, перевод студентов, занятия и журнал посещаемости по каждому уроку.</p>
      </article>
      <article class="lp-card">
        <h3>Контроль в реальном времени</h3>
        <p>Кто на смене, кто опоздал, кто не вышел. Уведомления администратору о длительном отсутствии.</p>
      </article>
      <article class="lp-card">
        <h3>Изоляция данных</h3>
        <p>У каждой школы своё пространство: сотрудники, группы и отчёты не пересекаются с другими клиентами.</p>
      </article>
    </div>
  </div>
</section>

<section class="lp-section lp-section--alt">
  <div class="lp-wrap">
    <h2>Как это устроено</h2>
    <ol class="lp-steps">
      <li>
        <span class="lp-steps__num">1</span>
        <div><h3>Регистрируете школу</h3><p>Название, код для входа и email директора. Через минуту можно добавлять людей.</p></div>
      </li>
      <li>
        <span class="lp-steps__num">2</span>
        <div><h3>Добавляете преподавателей и группы</h3><p>Создаёте группы, выдаёте инвайт-коды студентам, задаёте график и рабочие места.</p></div>
      </li>
      <li>
        <span class="lp-steps__num">3</span>
        <div><h3>Получаете табель</h3><p>Часы считаются сами. В конце месяца — выгрузка в Excel и отчёт по посещаемости занятий.</p></div>
      </li>
    </ol>
  </div>
</section>

<section class="lp-section" id="pricing">
  <div class="lp-wrap">
    <h2>Тарифы</h2>
    <p class="lp-section__lead">14 дней бесплатно на любом тарифе. Оплата по счёту, без автосписаний.</p>

    <div class="lp-grid lp-grid--pricing">
      <article class="lp-price">
        <h3>Старт</h3>
        <p class="lp-price__sum">3 900 ₽<span>/мес</span></p>
        <ul>
          <li>до 15 сотрудников</li>
          <li>до 100 студентов</li>
          <li>табель и выгрузка в Excel</li>
          <li>поддержка по почте</li>
        </ul>
        <a class="lp-btn lp-btn--ghost" href="<?= $b ?>index.php?route=site/contacts">Запросить доступ</a>
      </article>

      <article class="lp-price lp-price--featured">
        <span class="lp-price__badge">Для большинства школ</span>
        <h3>Школа</h3>
        <p class="lp-price__sum">8 900 ₽<span>/мес</span></p>
        <ul>
          <li>до 50 сотрудников</li>
          <li>до 500 студентов</li>
          <li>группы, расписание, журнал</li>
          <li>проверка присутствия</li>
          <li>поддержка в рабочие часы</li>
        </ul>
        <a class="lp-btn" href="<?= $b ?>index.php?route=site/contacts">Запросить доступ</a>
      </article>

      <article class="lp-price">
        <h3>Сеть</h3>
        <p class="lp-price__sum">19 900 ₽<span>/мес</span></p>
        <ul>
          <li>без ограничений по людям</li>
          <li>несколько филиалов</li>
          <li>расширенные отчёты</li>
          <li>приоритетная поддержка</li>
          <li>помощь с переносом данных</li>
        </ul>
        <a class="lp-btn lp-btn--ghost" href="<?= $b ?>index.php?route=site/contacts">Обсудить условия</a>
      </article>
    </div>

    <p class="lp-note">Нужен сервер внутри школы? Есть self-hosted: от 120 000 ₽ в год.</p>
  </div>
</section>

<section class="lp-section lp-section--alt">
  <div class="lp-wrap">
    <h2>Частые вопросы</h2>
    <div class="lp-faq">
      <details>
        <summary>Нужно ли что-то устанавливать?</summary>
        <p>Нет. Сервис работает в браузере на компьютере и телефоне. Студенты отмечаются по ссылке от преподавателя.</p>
      </details>
      <details>
        <summary>Что будет, если сотрудник закроет браузер?</summary>
        <p>Смена закроется автоматически по времени последней активности. Часы не «накрутятся», а администратор увидит, когда человек ушёл.</p>
      </details>
      <details>
        <summary>Можно перенести существующие данные?</summary>
        <p>Да. Список сотрудников и студентов загружается из таблицы, на тарифе «Сеть» перенос делаем вместе с вами.</p>
      </details>
      <details>
        <summary>Где хранятся данные?</summary>
        <p>На серверах в России. Доступ к данным есть только у вашей школы и администратора платформы.</p>
      </details>
    </div>
  </div>
</section>

<section class="lp-cta">
  <div class="lp-wrap lp-cta__inner">
    <div>
      <h2>Покажем на ваших данных</h2>
      <p>15 минут онлайн: настроим группы, добавим пару сотрудников и посчитаем первый табель.</p>
    </div>
    <a class="lp-btn" href="<?= $b ?>index.php?route=site/contacts">Оставить заявку</a>
  </div>
</section>
