<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Schema;
use App\Core\Validator;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Position;
use App\Models\GenericRecord;
use App\Models\DailyHours;
use App\Models\ScreenShare;
use App\Models\Notification;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\BreakTime;
use App\Models\Workplace;
use App\Models\ForceMajeure;
use App\Models\Verification;
use App\Services\AbsenceMonitor;
use App\Config\TableRegistry;
use App\Services\TimesheetExporter;

class AdminController extends Controller
{
    public function dashboard(): void
    {
        $this->requireAdmin();
        AbsenceMonitor::checkThrottled();
        $companyId = $this->companyId();
        $company   = Company::find($companyId);
        $this->view('admin/dashboard', [
            'employees'     => self::employeesWithStatus($companyId),
            'notifications' => self::safeAdminNotifications($companyId),
            'forceOpen'     => self::safeOpenForce($companyId),
            'collapseCount' => self::safeCollapseCount($companyId),
            'serverNow'     => date('Y-m-d H:i:s'),
            'subscription'  => Subscription::state($company),
        ]);
    }

    public function billing(): void
    {
        $this->requireAdmin();
        $companyId = $this->companyId();
        $company   = Company::find($companyId);

        $this->view('admin/billing', [
            'company'        => $company,
            'state'          => Subscription::state($company),
            'planLabel'      => Subscription::planLabel($company),
            'limitEmployees' => Subscription::limit($company, 'employees'),
            'limitStudents'  => Subscription::limit($company, 'students'),
            'employeesCount' => Company::countEmployees($companyId),
            'homeRoute'      => $this->homeRoute(),
        ]);
    }

    public function dashboardData(): void
    {
        $this->requireAdmin();
        AbsenceMonitor::checkThrottled();
        $companyId = $this->companyId();
        $this->json([
            'ok'            => true,
            'employees'     => self::employeesWithStatus($companyId),
            'notifications' => self::safeAdminNotifications($companyId),
            'force_open'    => self::safeOpenForce($companyId),
            'collapse_count' => self::safeCollapseCount($companyId),
            'server_now'    => date('Y-m-d H:i:s'),
        ]);
    }

    private static function safeOpenForce(int $companyId): array
    {
        try {
            return ForceMajeure::openForCompany($companyId);
        } catch (\Throwable $e) {
            error_log('Force majeure open: ' . $e->getMessage());
            return [];
        }
    }

    private static function safeCollapseCount(int $companyId): int
    {
        try {
            return Verification::collapseCount($companyId);
        } catch (\Throwable $e) {
            error_log('Collapse count: ' . $e->getMessage());
            return 0;
        }
    }

    private static function safeAdminNotifications(int $companyId): array
    {
        try {
            return Notification::forAdmin($companyId);
        } catch (\Throwable $e) {
            error_log('Notifications: ' . $e->getMessage());
            return [];
        }
    }

    public function notificationRead(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Notification::markReadByAdmin((int) ($_POST['id'] ?? 0), $this->companyId());
        }
        $this->json(['ok' => true]);
    }

    public function notificationCancel(): void
    {
        $session = $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Notification::cancel((int) ($_POST['id'] ?? 0), (int) $session['employee_id'], $this->companyId());
        }
        $this->json(['ok' => true]);
    }

    private static function employeesWithStatus(int $companyId): array
    {
        $workplaces = [];
        $forceOpen = [];
        try {
            Workplace::finalizeOverdue($companyId);
        } catch (\Throwable $e) {
            error_log('Workplace overdue: ' . $e->getMessage());
        }
        try {
            $workplaces = Workplace::currentMap($companyId);
        } catch (\Throwable $e) {
            error_log('Workplace map: ' . $e->getMessage());
        }
        try {
            $forceOpen = ForceMajeure::openMap($companyId);
        } catch (\Throwable $e) {
            error_log('Force majeure map: ' . $e->getMessage());
        }

        $employees = Employee::allWithDepartment($companyId);
        foreach ($employees as &$e) {
            $id = (int) $e['id'];
            $e['today'] = Attendance::todayStatusFor($id, $e['planned_login'], $e['planned_logout']);
            $place = $workplaces[$id] ?? null;
            $prefix = '';
            if (($place['status'] ?? '') === Workplace::STATUS_MOVING) {
                $prefix = '→ ';
            } elseif (($place['status'] ?? '') === Workplace::STATUS_OVERDUE) {
                $prefix = '⚠ ';
            }
            $e['workplace'] = $place && !empty($place['name']) ? $prefix . $place['name'] : null;
            $e['force_open'] = $forceOpen[$id] ?? null;
        }
        unset($e);
        return $employees;
    }

    public function allowReentry(): void
    {
        $this->requireAdmin();
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id && Employee::findById($id, $this->companyId())) {
            Attendance::allowReentry($id);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
            $this->json(['ok' => true]);
            return;
        }
        $this->redirect('admin/dashboard');
    }

    public function employeeCreate(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $positions = Position::allEmployeePositions($companyId);
        $errors = [];
        $fullName = '';
        $email = '';
        $positionId = null;
        $generatedPassword = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName   = trim($_POST['full_name'] ?? '');
            $email      = Validator::normalizeEmail((string) ($_POST['email'] ?? ''));
            $positionId = (int) ($_POST['position_id'] ?? 0);
            $password   = trim($_POST['password'] ?? '');

            if ($fullName === '') {
                $errors[] = 'Укажите ФИО сотрудника.';
            }
            $emailError = Validator::emailError($email);
            if ($emailError !== null) {
                $errors[] = $emailError;
            } elseif (Employee::emailExists($email, $companyId)) {
                $errors[] = 'Сотрудник с таким email уже зарегистрирован.';
            }
            if (!$positionId || !Position::find($positionId, $companyId)) {
                $errors[] = 'Выберите должность из списка.';
            }
            if ($password !== '' && mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов.';
            }

            $limit = Subscription::limit(Company::find($companyId), 'employees');
            if ($limit > 0 && Company::countEmployees($companyId) >= $limit) {
                $errors[] = 'Достигнут лимит тарифа: ' . $limit . ' человек. Чтобы добавить больше, напишите нам — расширим тариф.';
            }

            if (empty($errors)) {
                $plainPassword = $password !== '' ? $password : self::generateTempPassword();

                $newId = Employee::create($companyId, $fullName, $positionId, $email, $plainPassword);

                if ($newId) {
                    $generatedPassword = $password === '' ? $plainPassword : null;
                    $fullName = '';
                    $email = '';
                    $positionId = null;

                    $this->view('admin/employee_create', [
                        'positions' => $positions,
                        'errors' => [],
                        'success' => true,
                        'generatedPassword' => $generatedPassword,
                        'fullName' => $fullName,
                        'email' => $email,
                        'positionId' => $positionId,
                    ]);
                    return;
                }
            }
        }

        $this->view('admin/employee_create', [
            'positions' => $positions,
            'errors' => $errors,
            'success' => false,
            'generatedPassword' => null,
            'fullName' => $fullName,
            'email' => $email,
            'positionId' => $positionId,
        ]);
    }

    public function employeeSchedule(): void
    {
        $this->requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $employee = $id ? Employee::findById($id, $this->companyId()) : null;

        if (!$employee) {
            http_response_code(404);
            die('Сотрудник не найден.');
        }

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $login  = trim($_POST['custom_planned_login'] ?? '');
            $logout = trim($_POST['custom_planned_logout'] ?? '');

            $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if ($login !== '' && !preg_match($timePattern, $login)) {
                $errors[] = 'Неверный формат времени прихода (нужно ЧЧ:ММ).';
            }
            if ($logout !== '' && !preg_match($timePattern, $logout)) {
                $errors[] = 'Неверный формат времени ухода (нужно ЧЧ:ММ).';
            }
            if (empty($errors) && $login !== '' && $logout !== '' && $login >= $logout) {
                $errors[] = 'Время ухода должно быть позже времени прихода.';
            }

            if (empty($errors)) {
                Employee::updateSchedule($id, $this->companyId(), $login, $logout);
                $employee = Employee::findById($id, $this->companyId());
                $success = true;
            }
        }

        $this->view('admin/employee_schedule', [
            'employee' => $employee,
            'errors'   => $errors,
            'success'  => $success,
        ]);
    }

    public function tables(): void
    {
        $this->requireAdmin();
        $this->view('admin/tables', ['tables' => TableRegistry::definitions()]);
    }

    public function tableCreate(): void
    {
        $this->requireAdmin();

        $companyId = $this->companyId();
        $key = $_GET['key'] ?? '';
        $def = TableRegistry::find($key);

        if (!$def) {
            http_response_code(404);
            die('Неизвестная таблица.');
        }

        foreach ($def['fields'] as &$field) {
            if ($field['type'] === 'fk_select') {
                $field['choices'] = GenericRecord::options($field['fk_table'], $field['fk_value'], $field['fk_label'], $companyId);
            }
        }
        unset($field);

        $errors = [];
        $success = false;
        $values = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $columns = [];
            $bind = [];

            foreach ($def['fields'] as $field) {
                $raw = trim($_POST[$field['name']] ?? '');
                $values[$field['name']] = $raw;

                if ($field['required'] && $raw === '') {
                    $errors[] = "Поле «{$field['label']}» обязательно для заполнения.";
                    continue;
                }
                if ($raw === '' && !$field['required']) {
                    continue;
                }

                switch ($field['type']) {
                    case 'email':
                        $raw = Validator::normalizeEmail($raw);
                        $emailError = Validator::emailError($raw, (string) $field['label']);
                        if ($emailError !== null) {
                            $errors[] = $emailError;
                            continue 2;
                        }
                        break;
                    case 'fk_select':
                        $validIds = array_column($field['choices'], 'value');
                        if (!in_array($raw, array_map('strval', $validIds), true)) {
                            $errors[] = "Некорректное значение для «{$field['label']}».";
                            continue 2;
                        }
                        break;
                    case 'select':
                        if (!array_key_exists($raw, $field['options'])) {
                            $errors[] = "Некорректное значение для «{$field['label']}».";
                            continue 2;
                        }
                        break;
                    case 'password':
                        if (mb_strlen($raw) < 6) {
                            $errors[] = 'Пароль должен быть не короче 6 символов.';
                            continue 2;
                        }
                        $raw = password_hash($raw, PASSWORD_DEFAULT);
                        break;
                    case 'number':
                        if (!ctype_digit($raw) || (int) $raw > 2147483647) {
                            $errors[] = "«{$field['label']}»: нужно целое число секунд от 0 до 2147483647.";
                            continue 2;
                        }
                        break;
                    case 'datetime':
                        $raw = str_replace('T', ' ', $raw);
                        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
                            $raw .= ':00';
                        }
                        break;
                }

                $column = $field['db_column'] ?? $field['name'];
                $columns[] = $column;
                $bind[$column] = $raw;
            }

            if ($key === 'employees' && empty($errors) && Employee::emailExists($values['email'] ?? '', $companyId)) {
                $errors[] = 'Сотрудник с таким email уже существует.';
            }

            if (in_array($def['table'], GenericRecord::DIRECT_TABLES, true) && empty($errors)) {
                $columns[] = 'company_id';
                $bind['company_id'] = $companyId;
            }
            if ($key === 'departments' && empty($errors)) {
                $columns[] = 'created_at';
                $bind['created_at'] = Schema::dateValue('departments', 'created_at');
            }
            if ($key === 'attendance' && empty($errors)) {
                $columns[] = 'created_by';
                $bind['created_by'] = 'admin';
                $columns[] = 'updated_at';
                $bind['updated_at'] = Schema::dateValue('attendance', 'updated_at');
            }
            if ($key === 'daily_hours' && empty($errors)) {
                $columns[] = 'updated_at';
                $bind['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($key === 'attendance' && empty($errors)) {
                $in  = strtotime($bind['last_login_at'] ?? '');
                $out = isset($bind['last_logout_at']) ? strtotime($bind['last_logout_at']) : null;
                if ($out !== null && $out !== false && $in !== false && $out < $in) {
                    $errors[] = 'Время выхода не может быть раньше времени входа.';
                } elseif (Attendance::existsFor((int) $bind['employee_id'], $bind['work_date'])) {
                    $errors[] = 'На этого сотрудника и эту дату запись посещаемости уже есть.';
                }
            }

            if (empty($errors)) {
                try {
                    if ($key === 'daily_hours') {
                        DailyHours::setSeconds((int) $bind['employee_id'], $bind['work_date'], (int) $bind['total_seconds']);
                    } else {
                        GenericRecord::insert($def['table'], $columns, $bind);

                        if ($key === 'attendance' && !empty($bind['last_logout_at'])) {
                            DailyHours::addSeconds(
                                (int) $bind['employee_id'],
                                $bind['work_date'],
                                max(0, strtotime($bind['last_logout_at']) - strtotime($bind['last_login_at']))
                            );
                        }
                    }
                    $success = true;
                    $values = [];
                } catch (\PDOException $e) {
                    error_log('Admin table insert failed (' . $def['table'] . '): ' . $e->getMessage());
                    $errors[] = self::describeDbError($e);
                }
            }
        }

        $this->view('admin/table_create', [
            'key'      => $key,
            'def'      => $def,
            'errors'   => $errors,
            'success'  => $success,
            'values'   => $values,
            'latest'   => GenericRecord::latest($def['table'], $companyId, 10),
        ]);
    }

    private static function describeDbError(\PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $hint = match (true) {
            $code === 1062 => 'Такая запись уже существует (нарушена уникальность).',
            in_array($code, [1054, 1146], true) => 'В БД нет нужной таблицы или колонки — выполните SQL-миграции из папки sql/ (migration_stage5.sql).',
            $code === 1364 => 'Для обязательной колонки БД не передано значение.',
            in_array($code, [1265, 1292, 1366, 1264], true) => 'Значение не подходит по формату или размеру для колонки БД.',
            in_array($code, [1452, 1451], true) => 'Нарушена связь с другой таблицей (сотрудник/должность/подразделение).',
            default => 'Не удалось сохранить запись в базу данных.',
        };
        return $hint . ' Детали: ' . $e->getMessage();
    }

    private static function generateTempPassword(int $length = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $password;
    }

    public function monitoring(): void
    {
        $this->requireAdmin();
        $employees = Employee::allWithDepartment($this->companyId());
        foreach ($employees as &$e) {
            $e['today'] = Attendance::todayStatusFor((int) $e['id'], $e['planned_login'], $e['planned_logout']);
            $e['screen_live'] = ScreenShare::isLive((int) $e['id']);
        }
        unset($e);

        $this->view('admin/monitoring', ['employees' => $employees]);
    }

    public function monitoringStatus(): void
    {
        $this->requireAdmin();
        $employees = Employee::allWithDepartment($this->companyId());
        $result = [];
        foreach ($employees as $e) {
            $result[(int) $e['id']] = ScreenShare::isLive((int) $e['id']);
        }
        $this->json(['ok' => true, 'live' => $result]);
    }

    public function monitoringFrame(): void
    {
        $this->requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $path = Employee::findById($id, $this->companyId()) ? ScreenShare::livePath($id) : null;

        header('Cache-Control: no-store, max-age=0');

        if (!$path) {
            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200">'
               . '<rect width="100%" height="100%" fill="#ECEAE4"/>'
               . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" '
               . 'font-family="sans-serif" font-size="14" fill="#4B5563">нет сигнала</text>'
               . '</svg>';
            exit;
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function monitoringView(): void
    {
        $this->requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $employee = Employee::findById($id, $this->companyId());

        if (!$employee) {
            http_response_code(404);
            die('Сотрудник не найден.');
        }

        $this->view('admin/monitoring_view', ['employee' => $employee, 'id' => $id]);
    }

    public function timesheet(): void
    {
        $this->requireAdmin();

        $departments = GenericRecord::options('departments', 'id', 'name', $this->companyId());

        $now = new \DateTime();
        $defaultWeek = $now->format('o') . '-W' . $now->format('W');

        $this->view('admin/timesheet', [
            'departments' => $departments,
            'defaultWeek' => $defaultWeek,
        ]);
    }

    public function timesheetExport(): void
    {
        $this->requireAdmin();

        $week = $_GET['week'] ?? '';
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
            http_response_code(400);
            die('Не указана или некорректна неделя. Вернитесь назад и выберите неделю в форме.');
        }

        $monday = new \DateTime();
        $monday->setISODate((int) $m[1], (int) $m[2], 1);
        $sunday = (clone $monday)->modify('+6 days');

        $companyId = $this->companyId();
        $departmentId = (int) ($_GET['department_id'] ?? 0) ?: null;
        $orgName = $_SESSION['company_name'] ?? (Company::find($companyId)['name'] ?? null);

        try {
            $bytes = TimesheetExporter::buildWeek(
                $companyId,
                $monday->format('Y-m-d'),
                $sunday->format('Y-m-d'),
                $departmentId,
                $orgName
            );
        } catch (\RuntimeException $e) {
            http_response_code(500);
            die(htmlspecialchars($e->getMessage()));
        }

        $filename = 'tabel_T13_' . ($_SESSION['company_code'] ?? 'c' . $companyId) . '_' . $week . ($departmentId ? '_dep' . $departmentId : '') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, max-age=0');
        echo $bytes;
        exit;
    }

    private function flash(string $message, string $type = 'ok'): void
    {
        $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);
        return $flash;
    }

    public function org(): void
    {
        $this->requireAdmin();
        $companyId = $this->companyId();

        if (($_SESSION['company_type'] ?? 'company') === 'school') {
            $this->redirect('school/dashboard');
        }

        $schedules = [];
        $workplaces = [];
        $recentMoves = [];
        $forceOpen = [];
        $forceHistory = [];
        try {
            $schedules = BreakTime::schedulesForCompany($companyId);
        } catch (\Throwable $e) {
            error_log('Break schedules: ' . $e->getMessage());
        }
        try {
            Workplace::finalizeOverdue($companyId);
            $workplaces = Workplace::allForCompany($companyId);
            $recentMoves = Workplace::recentForCompany($companyId, null, 20);
        } catch (\Throwable $e) {
            error_log('Workplaces: ' . $e->getMessage());
        }
        try {
            $forceOpen = ForceMajeure::openForCompany($companyId);
            $forceHistory = ForceMajeure::historyForCompany($companyId);
        } catch (\Throwable $e) {
            error_log('Force majeure list: ' . $e->getMessage());
        }

        $this->view('admin/org', [
            'schedules'    => $schedules,
            'workplaces'   => $workplaces,
            'recentMoves'  => $recentMoves,
            'forceOpen'    => $forceOpen,
            'forceHistory' => $forceHistory,
            'days'         => BreakTime::DAYS,
            'breakCap'     => max(1, (int) floor(\App\Core\Settings::breakTotal() / 60)),
            'breakMax'     => \App\Core\Settings::breakMaxCount(),
            'moveSeconds'  => \App\Core\Settings::workplaceMoveSeconds($companyId),
            'moveDefault'  => \App\Core\Settings::workplaceMoveSeconds(null),
            'flash'        => $this->takeFlash(),
        ]);
    }

    public function breakScheduleSave(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/org');
        }

        $companyId = $this->companyId();
        $action = (string) ($_POST['action'] ?? 'create');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id > 0) {
                BreakTime::deleteSchedule($id, $companyId);
                $this->flash('Окно перерыва удалено.');
            }
            $this->redirect('admin/org');
        }

        if ($action === 'toggle') {
            if ($id > 0) {
                BreakTime::setScheduleActive($id, $companyId, (string) ($_POST['active'] ?? '0') === '1');
                $this->flash('Статус окна перерыва изменён.');
            }
            $this->redirect('admin/org');
        }

        $label   = trim((string) ($_POST['label'] ?? ''));
        $start   = trim((string) ($_POST['start'] ?? ''));
        $end     = trim((string) ($_POST['end'] ?? ''));
        $minutes = (int) ($_POST['minutes'] ?? 10);
        $days    = $_POST['days'] ?? [];
        $mask    = 0;
        if (is_array($days)) {
            foreach ($days as $day) {
                $day = (int) $day;
                if ($day >= 1 && $day <= 7) {
                    $mask |= 1 << ($day - 1);
                }
            }
        }
        if ($mask === 0) {
            $mask = (int) ($_POST['days_mask'] ?? 0);
        }

        $errors = BreakTime::validateSchedule($companyId, $label, $start, $end, $minutes, $mask);
        if ($errors) {
            $this->flash(implode(' ', $errors), 'error');
            $this->redirect('admin/org');
        }

        BreakTime::createSchedule($companyId, $label, $start, $end, $minutes, $mask);
        $this->flash('Окно регламентированного перерыва добавлено.');
        $this->redirect('admin/org');
    }

    public function workplaceSave(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/org');
        }

        $companyId = $this->companyId();
        $action = (string) ($_POST['action'] ?? 'create');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'toggle') {
            if ($id > 0) {
                Workplace::setActive($id, $companyId, (string) ($_POST['active'] ?? '0') === '1');
                $this->flash('Рабочее место обновлено.');
            }
            $this->redirect('admin/org');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $this->flash('Название рабочего места: от 1 до 100 символов.', 'error');
        } elseif (Workplace::nameExists($companyId, $name)) {
            $this->flash('Рабочее место с таким названием уже есть.', 'error');
        } else {
            Workplace::create($companyId, $name);
            $this->flash('Рабочее место «' . $name . '» добавлено.');
        }
        $this->redirect('admin/org');
    }

    public function workplaceMoveTimeSave(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/org');
        }

        $companyId = $this->companyId();
        $minutes = (int) ($_POST['minutes'] ?? 0);
        if ($minutes < 1 || $minutes > 60) {
            $this->flash('Время на переход: от 1 до 60 минут.', 'error');
            $this->redirect('admin/org');
        }

        \App\Models\CompanySetting::setWorkplaceMoveSeconds($companyId, $minutes * 60);
        $this->flash('Время на переход сохранено: ' . $minutes . ' мин.');
        $this->redirect('admin/org');
    }

    public function forceAcknowledge(): void
    {
        $session = $this->requireAdmin();
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $done = false;
        if ($id > 0) {
            $done = ForceMajeure::acknowledge($id, (int) $session['employee_id'], $this->companyId());
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
            $this->json(['ok' => $done]);
        }
        $this->flash($done ? 'Сообщение о форс-мажоре принято.' : 'Сообщение уже обработано.');
        $this->redirect('admin/org');
    }
}
