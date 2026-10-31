<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\Employee;
use App\Models\ForceMajeure;
use App\Models\ScreenShare;
use App\Models\Notification;
use App\Models\Lunch;
use App\Models\Workplace;
use App\Core\Settings;
use App\Services\AbsenceMonitor;

class EmployeeController extends Controller
{
    public function dashboard(): void
    {
        $session = $this->requireAuth();
        $employeeId = (int) $session['employee_id'];

        $employee = Employee::findById($employeeId);
        $status = Attendance::todayStatusFor(
            $employeeId,
            $employee['planned_login'] ?? null,
            $employee['planned_logout'] ?? null
        );

        AbsenceMonitor::checkThrottled();

        $companyId = (int) $session['company_id'];
        $workplace = ['current' => null, 'list' => [], 'moves' => [], 'state' => null];
        try {
            Workplace::finalizeOverdue($companyId);
            $workplace = [
                'current' => Workplace::currentFor($employeeId),
                'list'    => Workplace::allForCompany($companyId, true),
                'moves'   => Workplace::movesFor($employeeId),
                'state'   => Workplace::stateFor($employeeId, $companyId),
            ];
        } catch (\Throwable $e) {
            error_log('Workplace state: ' . $e->getMessage());
        }

        $forceHistory = [];
        try {
            $forceHistory = ForceMajeure::historyFor($employeeId);
        } catch (\Throwable $e) {
            error_log('Force majeure history: ' . $e->getMessage());
        }

        $this->view('employee/dashboard', [
            'notifications' => self::safeNotifications($employeeId),
            'status' => $status,
            'session' => $session,
            'plannedLogin' => $employee['planned_login'] ?? null,
            'plannedLogout' => $employee['planned_logout'] ?? null,
            'workplace' => $workplace,
            'forceReasons' => ForceMajeure::REASONS,
            'forceHistory' => $forceHistory,
        ]);
    }

    public function status(): void
    {
        $this->requireAuth(false);
        $employeeId = (int) $_SESSION['employee_id'];

        $employee = Employee::findById($employeeId);
        $status = Attendance::todayStatusFor(
            $employeeId,
            $employee['planned_login'] ?? null,
            $employee['planned_logout'] ?? null
        );

        $this->json([
            'ok' => true,
            'status' => $status,
            'plannedLogin' => $employee['planned_login'] ? substr($employee['planned_login'], 0, 5) : null,
            'plannedLogout' => $employee['planned_logout'] ? substr($employee['planned_logout'], 0, 5) : null,
        ]);
    }

    private static function safeNotifications(int $employeeId): array
    {
        try {
            return Notification::forEmployee($employeeId);
        } catch (\Throwable $e) {
            error_log('Notifications: ' . $e->getMessage());
            return [];
        }
    }

    public function notifications(): void
    {
        $this->requireAuth(false);
        AbsenceMonitor::checkThrottled();
        $this->json(['ok' => true, 'notifications' => self::safeNotifications((int) $_SESSION['employee_id'])]);
    }

    public function notificationRead(): void
    {
        $this->requireAuth(false);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Notification::markReadByEmployee((int) ($_POST['id'] ?? 0), (int) $_SESSION['employee_id']);
        }
        $this->json(['ok' => true]);
    }

    public function heartbeat(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false]);
        }

        $_SESSION['last_activity'] = time();
        Attendance::touch((int) $_SESSION['employee_id']);

        $mode = $_POST['mode'] ?? 'fg';
        $_SESSION['activity_limit'] = in_array($mode, ['bg', 'idle'], true)
            ? Settings::backgroundInactivity()
            : Settings::inactivity();

        $this->json(['ok' => true, 'server_time' => time()]);
    }

    public function lunchStart(): void
    {
        $this->requireAuth();
        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = Lunch::start((int) $_SESSION['employee_id']);
                if ($result['ok']) {
                    Attendance::touch((int) $_SESSION['employee_id']);
                }
            } catch (\Throwable $e) {
                error_log('Lunch start: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось начать обед. Попробуйте ещё раз.'];
            }
        }
        $this->json($result + ['status' => $this->currentStatus()]);
    }

    public function lunchEnd(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Lunch::end((int) $_SESSION['employee_id']);
                $_SESSION['last_activity'] = time();
                Attendance::touch((int) $_SESSION['employee_id']);
            } catch (\Throwable $e) {
                error_log('Lunch end: ' . $e->getMessage());
            }
        }
        $this->json(['ok' => true, 'status' => $this->currentStatus()]);
    }

    public function breakStart(): void
    {
        $this->requireAuth();
        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = BreakTime::start((int) $_SESSION['employee_id']);
                if ($result['ok']) {
                    Attendance::touch((int) $_SESSION['employee_id']);
                }
            } catch (\Throwable $e) {
                error_log('Break start: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось начать перерыв. Попробуйте ещё раз.'];
            }
        }
        $this->json($result + ['status' => $this->currentStatus()]);
    }

    public function breakEnd(): void
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                BreakTime::end((int) $_SESSION['employee_id']);
                $_SESSION['last_activity'] = time();
                Attendance::touch((int) $_SESSION['employee_id']);
            } catch (\Throwable $e) {
                error_log('Break end: ' . $e->getMessage());
            }
        }
        $this->json(['ok' => true, 'status' => $this->currentStatus()]);
    }

    private function currentStatus(): array
    {
        $employeeId = (int) $_SESSION['employee_id'];
        $employee = Employee::findById($employeeId);
        return Attendance::todayStatusFor(
            $employeeId,
            $employee['planned_login'] ?? null,
            $employee['planned_logout'] ?? null
        );
    }

    public function workplaceMove(): void
    {
        $session = $this->requireAuth();
        $employeeId = (int) $session['employee_id'];
        $companyId  = (int) $session['company_id'];

        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($session['company_type'] ?? 'company') === 'school') {
                $this->json(['ok' => false, 'error' => 'Перемещение между рабочими местами доступно только в компаниях.']);
            }
            try {
                $result = Workplace::move($employeeId, $companyId, (int) ($_POST['workplace_id'] ?? 0));
                if (!empty($result['ok'])) {
                    Attendance::touch($employeeId);
                }
            } catch (\Throwable $e) {
                error_log('Workplace move: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось сохранить перемещение. Попробуйте ещё раз.'];
            }
        }

        $current = null;
        $moves = [];
        $state = null;
        try {
            $current = Workplace::currentFor($employeeId);
            $moves = Workplace::movesFor($employeeId);
            $state = Workplace::stateFor($employeeId, $companyId);
        } catch (\Throwable $e) {
            error_log('Workplace state: ' . $e->getMessage());
        }

        $this->json($result + ['current' => $current, 'moves' => $moves, 'state' => $state]);
    }

    public function workplaceState(): void
    {
        $session = $this->requireAuth(false);
        $employeeId = (int) $session['employee_id'];
        $companyId = (int) $session['company_id'];

        if (($session['company_type'] ?? 'company') === 'school') {
            $this->json(['ok' => false, 'error' => 'Перемещение между рабочими местами доступно только в компаниях.']);
        }

        $state = null;
        try {
            $state = Workplace::stateFor($employeeId, $companyId);
        } catch (\Throwable $e) {
            error_log('Workplace state: ' . $e->getMessage());
        }

        $this->json(['ok' => true, 'state' => $state]);
    }

    public function workplaceArrive(): void
    {
        $session = $this->requireAuth();
        $employeeId = (int) $session['employee_id'];
        $companyId = (int) $session['company_id'];

        if (($session['company_type'] ?? 'company') === 'school') {
            $this->json(['ok' => false, 'error' => 'Перемещение между рабочими местами доступно только в компаниях.']);
        }

        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = Workplace::arrive($employeeId, $companyId);
                if (!empty($result['ok'])) {
                    Attendance::touch($employeeId);
                }
            } catch (\Throwable $e) {
                error_log('Workplace arrive: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось подтвердить прибытие. Попробуйте ещё раз.'];
            }
        }

        $state = null;
        try {
            $state = Workplace::stateFor($employeeId, $companyId);
        } catch (\Throwable $e) {
            error_log('Workplace state: ' . $e->getMessage());
        }

        $this->json($result + ['state' => $state]);
    }

    public function forceMajeureSubmit(): void
    {
        $session = $this->requireAuth();
        $employeeId = (int) $session['employee_id'];
        $companyId  = (int) $session['company_id'];

        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($session['company_type'] ?? 'company') === 'school') {
                $this->json(['ok' => false, 'error' => 'Форс-мажор доступен только в компаниях.']);
            }
            try {
                $result = ForceMajeure::submit(
                    $employeeId,
                    $companyId,
                    (string) ($_POST['reason'] ?? ''),
                    (string) ($_POST['comment'] ?? '')
                );
            } catch (\Throwable $e) {
                error_log('Force majeure submit: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось отправить сообщение. Попробуйте ещё раз.'];
            }
        }

        $this->json($result + ['history' => $this->safeForceHistory($employeeId)]);
    }

    private function safeForceHistory(int $employeeId): array
    {
        try {
            return ForceMajeure::historyFor($employeeId);
        } catch (\Throwable $e) {
            error_log('Force majeure history: ' . $e->getMessage());
            return [];
        }
    }

    public function autoLogout(): void
    {
        $this->requireAuth();
        $employeeId = (int) $_SESSION['employee_id'];

        try {
            if (Lunch::state($employeeId)['active']) {
                $this->json(['ok' => false, 'error' => 'on_lunch']);
            }
        } catch (\Throwable $e) {
            error_log('Lunch check: ' . $e->getMessage());
        }

        try {
            if (BreakTime::state($employeeId)['active']) {
                $this->json(['ok' => false, 'error' => 'on_break']);
            }
        } catch (\Throwable $e) {
            error_log('Break check: ' . $e->getMessage());
        }

        $idleMs = max(0, (int) ($_POST['idle_ms'] ?? 0));
        $limitMs = min(Settings::backgroundInactivity() * 1000, max(0, (int) ($_POST['limit_ms'] ?? 0)));
        $closeAt = ($idleMs > 0 && $limitMs > 0)
            ? time() - intdiv($idleMs, 1000) + intdiv($limitMs, 1000)
            : null;

        Attendance::clockOut($employeeId, 'auto', $closeAt);
        $_SESSION = [];
        session_destroy();

        $this->json(['ok' => true]);
    }

    public function screenFrame(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false]);
        }

        $saved = ScreenShare::save((int) $_SESSION['employee_id'], $_POST['frame'] ?? '');
        $this->json(['ok' => $saved]);
    }

    public function screenStop(): void
    {
        $this->requireAuth();
        ScreenShare::stop((int) $_SESSION['employee_id']);
        $this->json(['ok' => true]);
    }
}
