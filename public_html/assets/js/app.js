/* Общие улучшения интерфейса: стабильная высота на мобильных, email, вкладки,
   проверка присутствия. Файл подключается на всех страницах. */
(function () {
  'use strict';

  /* ---------- Стабильная высота приложения ---------- */
  var root = document.documentElement;
  var lockedHeight = 0;
  var lastAngle = null;

  function orientationAngle() {
    if (screen.orientation && typeof screen.orientation.angle === 'number') {
      return screen.orientation.angle;
    }
    if (typeof window.orientation === 'number') {
      return window.orientation;
    }
    return 0;
  }

  function isMobile() {
    return window.matchMedia('(pointer: coarse)').matches || window.innerWidth <= 900;
  }

  function lockAppHeight(force) {
    var height = Math.round(window.innerHeight || 0);
    if (height <= 0) return;

    var angle = orientationAngle();
    var rotated = lastAngle !== null && angle !== lastAngle;
    var grows = height > lockedHeight;

    if (force || lockedHeight === 0 || rotated || grows || !isMobile()) {
      lockedHeight = height;
      lastAngle = angle;
      root.style.setProperty('--app-height', height + 'px');
    } else {
      lastAngle = angle;
    }
  }

  lockAppHeight(true);
  window.addEventListener('resize', function () { lockAppHeight(false); }, { passive: true });
  window.addEventListener('orientationchange', function () {
    setTimeout(function () { lockAppHeight(true); }, 150);
  });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', function () { lockAppHeight(false); }, { passive: true });
  }

  /* ---------- Email: атрибуты и проверка ---------- */
  var EMAIL_RE = /^[^\s@]{1,64}@[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i;
  var TLD_RE = /\.[a-z]{2,24}$/i;

  function emailMessage(value) {
    if (!value) return 'Укажите email.';
    if (value.length > 254) return 'Email слишком длинный (максимум 254 символа).';
    if (/\s/.test(value) || value.indexOf('..') !== -1) {
      return 'Email указан некорректно. Пример: name@company.ru';
    }
    if (!EMAIL_RE.test(value)) return 'Email указан некорректно. Пример: name@company.ru';
    var parts = value.split('@');
    if (parts.length !== 2) return 'Email указан некорректно. Пример: name@company.ru';
    if (!TLD_RE.test(parts[1])) return 'Проверьте домен: он должен заканчиваться на .ru, .com и т. п.';
    return '';
  }

  function prepareEmail(input) {
    input.setAttribute('maxlength', '254');
    input.setAttribute('inputmode', 'email');
    input.setAttribute('autocapitalize', 'none');
    input.setAttribute('autocorrect', 'off');
    input.setAttribute('spellcheck', 'false');
    if (!input.getAttribute('autocomplete')) input.setAttribute('autocomplete', 'email');

    function validate() {
      var value = String(input.value || '').trim().toLowerCase();
      if (value === '' && !input.required) {
        input.setCustomValidity('');
        input.removeAttribute('aria-invalid');
        return true;
      }
      var message = emailMessage(value);
      input.setCustomValidity(message);
      if (message) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
      return !message;
    }

    input.addEventListener('blur', validate);
    input.addEventListener('input', function () {
      if (input.getAttribute('aria-invalid') === 'true') validate();
    });
    input.__validateEmail = validate;
  }

  Array.prototype.forEach.call(document.querySelectorAll('input[type="email"]'), prepareEmail);

  Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
    form.addEventListener('submit', function (ev) {
      var firstInvalid = null;
      Array.prototype.forEach.call(form.querySelectorAll('input[type="email"]'), function (input) {
        var check = input.__validateEmail ? input.__validateEmail() : true;
        if (!check && !firstInvalid) firstInvalid = input;
      });
      if (firstInvalid) {
        ev.preventDefault();
        firstInvalid.focus();
        if (typeof firstInvalid.reportValidity === 'function') firstInvalid.reportValidity();
      }
    });
  });

  /* ---------- Вкладки ---------- */
  Array.prototype.forEach.call(document.querySelectorAll('[data-tabs]'), function (nav) {
    var buttons = Array.prototype.slice.call(nav.querySelectorAll('[data-tab]'));
    if (!buttons.length) return;
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));

    function activate(key, focus) {
      buttons.forEach(function (btn) {
        var active = btn.getAttribute('data-tab') === key;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
        btn.tabIndex = active ? 0 : -1;
        if (active && focus) btn.focus();
      });
      panels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-tab-panel') !== key;
      });
    }

    buttons.forEach(function (btn, index) {
      btn.addEventListener('click', function () {
        activate(btn.getAttribute('data-tab'), false);
      });
      btn.addEventListener('keydown', function (ev) {
        if (ev.key !== 'ArrowRight' && ev.key !== 'ArrowLeft') return;
        ev.preventDefault();
        var next = (index + (ev.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
        activate(buttons[next].getAttribute('data-tab'), true);
      });
    });

    activate(buttons[0].getAttribute('data-tab'), false);
  });

  /* ---------- Проверка присутствия ---------- */
  var config = window.OPRES_VERIFY;
  if (!config) return;

  var modal = document.getElementById('verifyModal');
  if (!modal) return;

  var codeEl = document.getElementById('verifyCode');
  var optionsEl = document.getElementById('verifyOptions');
  var errorEl = document.getElementById('verifyError');
  var timerEl = document.getElementById('verifyTimer');
  var modeEl = document.getElementById('verifyMode');

  var challenge = config.challenge || null;
  var expiresAtMs = 0;
  var timerId = null;
  var expiring = false;
  var lastFocused = null;

  function readJson(response) {
    var type = response.headers.get('content-type') || '';
    if (response.redirected || type.indexOf('application/json') === -1) {
      window.location.href = config.loginUrl;
      return Promise.reject(new Error('login'));
    }
    return response.json();
  }

  if (modeEl && config.mode === 'mark') {
    modeEl.textContent = 'Если не ответить, в журнале появится отметка о сворачивании приложения.';
  }

  function serverOffsetMs(c) {
    var serverNow = Number(c && c.server_now ? c.server_now : 0) * 1000;
    return serverNow > 0 ? serverNow - Date.now() : 0;
  }

  function formatLeft(ms) {
    var total = Math.max(0, Math.floor(ms / 1000));
    var m = Math.floor(total / 60);
    var s = total % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  function setButtonsDisabled(disabled) {
    Array.prototype.forEach.call(optionsEl.querySelectorAll('button'), function (btn) {
      btn.disabled = disabled;
    });
  }

  function hideModal() {
    modal.hidden = true;
    document.body.classList.remove('verify-open');
    if (timerId) {
      clearInterval(timerId);
      timerId = null;
    }
    if (lastFocused && typeof lastFocused.focus === 'function') {
      try { lastFocused.focus(); } catch (e) { /* ignore */ }
    }
  }

  function showToast(text) {
    var toast = document.getElementById('appToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'appToast';
      toast.className = 'app-toast';
      toast.setAttribute('role', 'status');
      document.body.appendChild(toast);
    }
    toast.textContent = text;
    toast.classList.add('is-visible');
    setTimeout(function () { toast.classList.remove('is-visible'); }, 5000);
  }

  function handleFailure(data) {
    if (!data) return;
    if (data.redirect) {
      window.location.href = data.redirect;
      return;
    }
    if (data.logout) {
      window.location.href = config.loginUrl;
      return;
    }
    hideModal();
    if (data.marked) {
      showToast('Проверка пропущена. В журнале сделана отметка о сворачивании приложения.');
    }
  }

  function tick() {
    if (!challenge) return;
    var left = expiresAtMs - Date.now();
    timerEl.textContent = formatLeft(left);
    timerEl.classList.toggle('is-low', left <= 60000);
    if (left <= 0) {
      setButtonsDisabled(true);
      if (expiring) return;
      expiring = true;
      fetch(config.expireUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'token=' + encodeURIComponent(challenge.token || '') })
        .then(readJson)
        .then(function (data) {
          expiring = false;
          if (data && data.ok === false && data.error && !data.expired) {
            pollChallenge();
            return;
          }
          handleFailure(data || {});
        })
        .catch(function () {
          expiring = false;
          errorEl.textContent = 'Нет связи с сервером. Пробуем ещё раз…';
          setTimeout(tick, 5000);
        });
    }
  }

  function showChallenge(next) {
    challenge = next;
    expiresAtMs = Number(next.expires_at || 0) * 1000 - serverOffsetMs(next);
    expiring = false;
    errorEl.textContent = '';
    codeEl.textContent = String(next.code || '--');
    optionsEl.innerHTML = '';
    (next.options || []).forEach(function (option) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'verify__option';
      btn.textContent = String(option);
      btn.addEventListener('click', function () { answer(String(option)); });
      optionsEl.appendChild(btn);
    });
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('verify-open');
    if (timerId) clearInterval(timerId);
    timerId = setInterval(tick, 500);
    tick();
    var first = optionsEl.querySelector('button');
    if (first) first.focus();
  }

  function answer(value) {
    if (!challenge) return;
    setButtonsDisabled(true);
    errorEl.textContent = '';
    fetch(config.answerUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'token=' + encodeURIComponent(challenge.token || '') + '&answer=' + encodeURIComponent(value),
    })
      .then(readJson)
      .then(function (data) {
        if (data.refresh) {
          challenge = null;
          hideModal();
          pollChallenge();
          return;
        }
        if (data.expired || data.logout || data.marked || data.redirect) {
          challenge = null;
          handleFailure(data);
          return;
        }
        if (data.ok) {
          hideModal();
          challenge = null;
          return;
        }
        var left = Number(data.attempts_left || 0);
        errorEl.textContent = (data.error || 'Неверное число.') + (left > 0 ? ' Осталось попыток: ' + left + '.' : '');
        setButtonsDisabled(false);
      })
      .catch(function () {
        errorEl.textContent = 'Нет связи с сервером. Проверьте подключение.';
        setButtonsDisabled(false);
      });
  }

  function pollChallenge() {
    if (challenge && !modal.hidden) return;
    fetch(config.challengeUrl, { headers: { 'Accept': 'application/json' } })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.logout || data.marked || data.redirect) {
          handleFailure(data);
          return;
        }
        if (data.ok === false) return;
        if (data.pending && data.verify && data.verify.token) {
          showChallenge(data.verify);
        }
      })
      .catch(function () { /* тихо: повторим при следующем опросе */ });
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) pollChallenge();
  });

  if (challenge && challenge.token) {
    showChallenge(challenge);
  } else {
    setTimeout(pollChallenge, 1500);
  }
  setInterval(pollChallenge, Math.max(20000, Number(config.pollMs) || 45000));
})();
