<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Security;

/**
 * Публичная часть: лендинг, документы, форма заявки.
 *
 * Эти страницы не требуют входа и доступны на бесплатном тарифе.
 */
class SiteController extends Controller
{
    public function home(): void
    {
        $this->render('home', [
            'pageTitle' => 'OpresSence — контроль посещаемости для онлайн-школ',
            'contacts'  => $this->contactInfo(),
        ]);
    }

    public function offer(): void
    {
        $this->render('offer', [
            'pageTitle' => 'Публичная оферта — OpresSence',
            'contacts'  => $this->contactInfo(),
        ]);
    }

    public function privacy(): void
    {
        $this->render('privacy', [
            'pageTitle' => 'Политика обработки персональных данных — OpresSence',
            'contacts'  => $this->contactInfo(),
        ]);
    }

    public function contacts(): void
    {
        $sent  = isset($_GET['sent']);
        $error = $_SESSION['lead_error'] ?? null;
        unset($_SESSION['lead_error']);

        $this->render('contacts', [
            'pageTitle' => 'Связаться с нами — OpresSence',
            'contacts'  => $this->contactInfo(),
            'sent'      => $sent,
            'error'     => $error,
        ]);
    }

    public function lead(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('site/contacts');
        }

        $name    = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 190);
        $school  = mb_substr(trim((string) ($_POST['school'] ?? '')), 0, 190);
        $contact = mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 190);
        $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 2000);
        $consent = !empty($_POST['consent']);

        $error = null;
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $this->redirect('site/contacts&sent=1');
        }
        if (mb_strlen($name) < 2) {
            $error = 'Укажите, как к вам обращаться.';
        } elseif (mb_strlen($contact) < 5) {
            $error = 'Оставьте email или телефон для ответа.';
        } elseif (!$consent) {
            $error = 'Нужно согласие на обработку персональных данных.';
        }

        if ($error !== null) {
            $_SESSION['lead_error'] = $error;
            $this->redirect('site/contacts');
        }

        $gate = RateLimit::hit('lead:ip:' . Security::clientIp(), 10, 3600);
        if (!$gate['allowed']) {
            $_SESSION['lead_error'] = 'Слишком много заявок с этого адреса. Попробуйте '
                . RateLimit::retryText($gate['retry']) . '.';
            $this->redirect('site/contacts');
        }

        try {
            Database::connect()->prepare(
                'INSERT INTO leads (name, school, contact, message, status, ip, created_at)
                 VALUES (:n, :s, :c, :m, :st, :ip, :now)'
            )->execute([
                'n'   => $name,
                's'   => $school !== '' ? $school : null,
                'c'   => $contact,
                'm'   => $message !== '' ? $message : null,
                'st'  => 'new',
                'ip'  => Security::clientIp(),
                'now' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Таблицы может не быть до миграции — заявку всё равно отправим письмом.
            error_log('Lead insert: ' . $e->getMessage());
        }

        $cfg  = $this->contactInfo();
        $body = "Новая заявка с сайта\n\n"
            . "Имя: {$name}\n"
            . "Школа: " . ($school !== '' ? $school : '—') . "\n"
            . "Контакт: {$contact}\n"
            . "Сообщение: " . ($message !== '' ? $message : '—') . "\n"
            . "Время: " . date('d.m.Y H:i') . "\n";

        Mailer::send($cfg['lead_email'], 'Заявка с сайта OpresSence', $body);

        $this->redirect('site/contacts&sent=1');
    }

    protected function render(string $view, array $data = []): void
    {
        $data['contacts'] = $data['contacts'] ?? $this->contactInfo();
        extract($data);
        require __DIR__ . '/../Views/site/layout_top.php';
        require __DIR__ . '/../Views/site/' . $view . '.php';
        require __DIR__ . '/../Views/site/layout_bottom.php';
    }

    /**
     * @return array{name:string, inn:string, email:string, phone:string, lead_email:string}
     */
    private function contactInfo(): array
    {
        return [
            'name'       => defined('LEGAL_NAME') ? (string) LEGAL_NAME : 'ИП Дедов Артём',
            'inn'        => defined('LEGAL_INN') ? (string) LEGAL_INN : '',
            'email'      => defined('LEGAL_EMAIL') ? (string) LEGAL_EMAIL : 'hello@opressence.ru',
            'phone'      => defined('LEGAL_PHONE') ? (string) LEGAL_PHONE : '',
            'lead_email' => defined('LEAD_EMAIL') ? (string) LEAD_EMAIL : (defined('LEGAL_EMAIL') ? (string) LEGAL_EMAIL : 'hello@opressence.ru'),
        ];
    }
}
