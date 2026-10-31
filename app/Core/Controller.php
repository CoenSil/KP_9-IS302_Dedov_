<?php

namespace App\Core;

use App\Models\Attendance;
use App\Models\Lunch;
use App\Models\BreakTime;
use App\Models\Company;
use App\Models\Verification;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        $this->maybeIssueVerification();
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            die("Шаблон не найден: {$view}");
        }
        require __DIR__ . '/../Views/layout/header.php';
        require $viewFile;
        require __DIR__ . '/../Views/layout/footer.php';
    }

    protected function redirect(string $route): void
    {
        header('Location: ' . BASE_URL . 'index.php?route=' . $route);
        exit;
    }

    protected function json(array $data): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireAuth(bool $touchActivity = true, bool $checkVerify = true): array
    {
        if (empty($_SESSION['employee_id'])) {
            $this->redirect('auth/login');
        }

        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId === 0 || !Company::isActive($companyId)) {
            $_SESSION = [];
            session_destroy();
            $this->redirect('auth/login');
        }

        if ($checkVerify) {
            $this->enforceVerification();
        }

        $role  = (string) ($_SESSION['role'] ?? '');
        $type  = (string) ($_SESSION['company_type'] ?? 'company');
        $route = (string) ($_GET['route'] ?? '');
        $area  = null;
        if ($role === 'student') {
            $area = 'student/';
        } elseif ($role === 'teacher') {
            $area = 'teacher/';
        } elseif ($role === 'admin' && $type === 'school') {
            $area = 'school/';
        }
        if ($area !== null && !str_starts_with($route, $area) && !str_starts_with($route, 'auth/')) {
            $this->redirect($this->homeRoute());
        }

        if ($role === 'employee') {
            $employeeId = (int) $_SESSION['employee_id'];
            $limit = (int) ($_SESSION['activity_limit'] ?? Settings::inactivity());
            $lastActivity = $_SESSION['last_activity'] ?? time();

            $onPause = false;
            $pauseEnd = 0;
            try {
                $lunch = Lunch::state($employeeId);
                $onPause = $lunch['active'];
                $pauseEnd = max($pauseEnd, (int) ($lunch['last_end'] ?? 0));
            } catch (\Throwable $e) {
                error_log('Lunch check: ' . $e->getMessage());
            }
            try {
                $break = BreakTime::state($employeeId);
                $onPause = $onPause || $break['active'];
                $pauseEnd = max($pauseEnd, (int) ($break['last_end'] ?? 0));
            } catch (\Throwable $e) {
                error_log('Break check: ' . $e->getMessage());
            }

            if (!$onPause && $pauseEnd > $lastActivity) {
                $lastActivity = $pauseEnd;
            }

            if (!$onPause && time() - $lastActivity > $limit) {
                Attendance::clockOut($employeeId, 'auto', $lastActivity + $limit);
                $_SESSION = [];
                session_destroy();
                $this->redirect('auth/login');
            }
            if ($touchActivity) {
                $_SESSION['last_activity'] = time();
            }
        } elseif ($role === 'admin' && $type !== 'school') {
            Attendance::ensureOpen((int) $_SESSION['employee_id']);
        }

        return $_SESSION;
    }

    protected function homeRoute(): string
    {
        $role = (string) ($_SESSION['role'] ?? '');
        if ($role === 'admin') {
            return (($_SESSION['company_type'] ?? 'company') === 'school') ? 'school/dashboard' : 'admin/dashboard';
        }
        if ($role === 'teacher') {
            return 'teacher/dashboard';
        }
        return $role === 'student' ? 'student/dashboard' : 'employee/dashboard';
    }

    protected function requireSchoolAdmin(): array
    {
        $session = $this->requireAuth();
        if (($session['role'] ?? '') !== 'admin' || ($session['company_type'] ?? 'company') !== 'school') {
            $this->redirect($this->homeRoute());
        }
        return $session;
    }

    protected function requireTeacher(): array
    {
        $session = $this->requireAuth();
        if (($session['role'] ?? '') !== 'teacher') {
            $this->redirect($this->homeRoute());
        }
        return $session;
    }

    protected function requireStudent(): array
    {
        $session = $this->requireAuth();
        if (($session['role'] ?? '') !== 'student') {
            $this->redirect($this->homeRoute());
        }
        return $session;
    }

    protected function companyId(): int
    {
        return (int) ($_SESSION['company_id'] ?? 0);
    }

    protected function requireSuper(): array
    {
        if (empty($_SESSION['super_id'])) {
            $this->redirect('super/login');
        }
        return $_SESSION;
    }

    protected function requireAdmin(): array
    {
        $session = $this->requireAuth();
        if (($session['role'] ?? '') !== 'admin') {
            http_response_code(403);
            die('Доступ запрещён: требуются права администратора.');
        }
        return $session;
    }

    protected function verificationMode(): string
    {
        return ($_SESSION['company_type'] ?? 'company') === 'school' ? 'mark' : 'logout';
    }

    /**
     * Выдаёт новый код проверки, если подошёл интервал. Вызывается только
     * при отрисовке страницы, чтобы пятиминутный отсчёт не начинался
     * из фоновых запросов.
     */
    protected function maybeIssueVerification(): void
    {
        if (empty($_SESSION['employee_id']) || !empty($_SESSION['super_id'])) {
            return;
        }
        if (($_SESSION['role'] ?? '') === 'admin') {
            return;
        }
        if (!empty($_SESSION['verify'])) {
            return;
        }
        if ((int) ($_SESSION['verify_next_at'] ?? 0) > time()) {
            return;
        }

        try {
            $_SESSION['verify'] = Verification::issue(
                (int) $_SESSION['employee_id'],
                (int) ($_SESSION['company_id'] ?? 0)
            );
            $_SESSION['verify_next_at'] = time() + Settings::verifyInterval();
        } catch (\Throwable $e) {
            error_log('Verification issue: ' . $e->getMessage());
        }
    }

    protected function enforceVerification(): void
    {
        $challenge = $_SESSION['verify'] ?? null;
        if (empty($challenge)) {
            return;
        }

        try {
            $expired = Verification::isExpired($challenge, (int) $_SESSION['employee_id']);
        } catch (\Throwable $e) {
            error_log('Verification check: ' . $e->getMessage());
            return;
        }
        if (!$expired) {
            return;
        }

        $result = $this->failVerification();
        if (!empty($result['logout'])) {
            $this->redirect('auth/login');
        }
    }

    /**
     * Провал проверки: компания — разлогин, учебное заведение — отметка
     * о сворачивании приложения и продолжение работы.
     */
    protected function failVerification(string $reason = 'Проверка присутствия не пройдена за 5 минут'): array
    {
        $employeeId = (int) ($_SESSION['employee_id'] ?? 0);
        $companyId  = (int) ($_SESSION['company_id'] ?? 0);

        unset($_SESSION['verify']);
        $_SESSION['verify_next_at'] = time() + Settings::verifyInterval();

        if ($this->verificationMode() === 'mark') {
            try {
                Verification::markCollapse($employeeId, $companyId, $reason);
            } catch (\Throwable $e) {
                error_log('Verification mark: ' . $e->getMessage());
            }
            $_SESSION['verify_marked_at'] = time();
            return ['logout' => false, 'marked' => true];
        }

        try {
            Attendance::clockOut($employeeId, 'verify');
        } catch (\Throwable $e) {
            error_log('Verification clock out: ' . $e->getMessage());
        }
        $_SESSION = [];
        session_destroy();

        return [
            'logout'   => true,
            'redirect' => BASE_URL . 'index.php?route=auth/login',
        ];
    }
}
