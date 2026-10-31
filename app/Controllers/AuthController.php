<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\Company;
use App\Models\Group;
use App\Models\Position;
use App\Models\Verification;

class AuthController extends Controller
{
    private function mode(): array
    {
        $saved = explode('.', (string) ($_COOKIE['auth_mode'] ?? ''));
        $org = (string) ($_POST['org'] ?? $_GET['org'] ?? ($saved[0] ?? ''));
        $who = (string) ($_POST['who'] ?? $_GET['who'] ?? ($saved[1] ?? ''));
        $org = $org === 'school' ? 'school' : 'company';
        $who = ($org === 'school' && $who === 'student') ? 'student' : 'staff';
        return [$org, $who];
    }

    private function registrationOpen(): bool
    {
        return !defined('REGISTRATION_ENABLED') || REGISTRATION_ENABLED;
    }

    private function access(string $org, string $who, string $act, array $data = []): void
    {
        $this->view('auth/access', $data + [
            'org' => $org, 'who' => $who, 'act' => $act,
            'registrationOpen' => $this->registrationOpen(),
            'error' => null, 'errors' => [], 'v' => [], 'companyCode' => '',
        ]);
    }

    public function login(): void
    {
        if (!empty($_SESSION['employee_id'])) {
            $this->redirect($this->homeRoute());
        }

        [$org, $who] = $this->mode();
        $error = null;
        $companyCode = Company::normalizeCode((string) ($_POST['company'] ?? $_GET['c'] ?? $_COOKIE['company_code'] ?? ''));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = Validator::normalizeEmail((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $emailError = Validator::emailError($email);

            $company  = $companyCode !== '' ? Company::findByCode($companyCode) : null;
            $company  = ($company && (int) $company['is_active'] === 1) ? $company : null;
            $employee = ($company && $emailError === null) ? Employee::findByEmail($email, (int) $company['id']) : null;

            if ($emailError !== null) {
                $error = 'Введите корректный email.';
            } elseif ($employee && password_verify($password, $employee['password_hash'])) {
                if ($employee['role'] === 'employee' && Attendance::isBlockedToday((int) $employee['id'])) {
                    Attendance::markReentryRequested((int) $employee['id']);
                    $error = 'На сегодня смена уже закрыта. Запрос на повторный вход отправлен администратору.';
                } else {
                    $this->openSession($employee, $company);
                    $this->redirect($this->homeRoute());
                }
            } else {
                $error = 'Неверный код организации, email или пароль.';
            }
        }

        $this->access($org, $who, 'login', ['error' => $error, 'companyCode' => $companyCode]);
    }

    public function register(): void
    {
        if (!empty($_SESSION['employee_id'])) {
            $this->redirect($this->homeRoute());
        }
        if (!$this->registrationOpen()) {
            $this->redirect('auth/login');
        }

        [$org] = $this->mode();
        $errors = [];
        $v = ['name' => '', 'code' => '', 'admin_name' => '', 'admin_email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($v as $k => $_) {
                $v[$k] = trim((string) ($_POST[$k] ?? ''));
            }
            $v['code'] = Company::normalizeCode($v['code']);
            $v['admin_email'] = Validator::normalizeEmail($v['admin_email']);
            $password  = (string) ($_POST['password'] ?? '');
            $password2 = (string) ($_POST['password2'] ?? '');
            $school    = $org === 'school';

            if (trim((string) ($_POST['website'] ?? '')) !== '') {
                $this->redirect('auth/login');
            }
            if ($v['name'] === '') {
                $errors[] = $school ? 'Укажите название учебного заведения.' : 'Укажите название компании.';
            }
            if (!Company::isValidCode($v['code'])) {
                $errors[] = 'Код: 2–64 символа, только латиница, цифры и дефис (например, romashka).';
            } elseif (Company::codeExists($v['code'])) {
                $errors[] = 'Организация с таким кодом уже зарегистрирована.';
            }
            if ($v['admin_name'] === '') {
                $errors[] = $school ? 'Укажите ФИО директора.' : 'Укажите ваше ФИО.';
            }
            $emailError = Validator::emailError($v['admin_email']);
            if ($emailError !== null) {
                $errors[] = $emailError;
            }
            if (mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов.';
            } elseif ($password !== $password2) {
                $errors[] = 'Пароли не совпадают.';
            }

            if (empty($errors)) {
                try {
                    $companyId = Company::createWithAdmin(
                        $v['name'], $v['code'], $v['admin_name'], $v['admin_email'], $password,
                        $school ? Company::TYPE_SCHOOL : Company::TYPE_COMPANY
                    );
                    $company  = Company::find($companyId);
                    $employee = Employee::findByEmail($v['admin_email'], $companyId);
                    if ($company && $employee) {
                        $this->openSession($employee, $company);
                        $this->redirect($this->homeRoute());
                    }
                    $this->redirect('auth/login');
                } catch (\PDOException $e) {
                    error_log('Registration failed: ' . $e->getMessage());
                    $errors[] = ((int) ($e->errorInfo[1] ?? 0) === 1062)
                        ? 'Организация с таким кодом уже зарегистрирована.'
                        : 'Не удалось зарегистрироваться. Попробуйте позже.';
                }
            }
        }

        $this->access($org, 'staff', 'register', ['errors' => $errors, 'v' => $v]);
    }

    public function registerStudent(): void
    {
        if (!empty($_SESSION['employee_id'])) {
            $this->redirect($this->homeRoute());
        }

        $errors = [];
        $v = ['invite' => Group::normalizeCode((string) ($_GET['code'] ?? '')), 'full_name' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $v['invite']    = Group::normalizeCode((string) ($_POST['invite'] ?? ''));
            $v['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
            $v['email']     = Validator::normalizeEmail((string) ($_POST['email'] ?? ''));
            $password       = (string) ($_POST['password'] ?? '');
            $password2      = (string) ($_POST['password2'] ?? '');

            if (trim((string) ($_POST['website'] ?? '')) !== '') {
                $this->redirect('auth/login');
            }

            $group = Group::findByInvite($v['invite']);
            if (!$group) {
                $errors[] = 'Инвайт-код группы не найден. Уточните его в деканате или у администратора.';
            }
            if ($v['full_name'] === '') {
                $errors[] = 'Укажите ФИО.';
            }
            $emailError = Validator::emailError($v['email']);
            if ($emailError !== null) {
                $errors[] = $emailError;
            }
            if (mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов.';
            } elseif ($password !== $password2) {
                $errors[] = 'Пароли не совпадают.';
            }

            if (empty($errors) && $group) {
                $companyId = (int) $group['company_id'];
                $positionId = Position::idFor($companyId, 'student');
                if (!$positionId) {
                    $errors[] = 'В учебном заведении не настроена роль студента. Обратитесь к администратору.';
                } elseif (Employee::emailExists($v['email'], $companyId)) {
                    $errors[] = 'Профиль с таким email уже есть. Войдите, указав код учебного заведения: ' . $group['company_code'] . '.';
                } else {
                    try {
                        $studentId = Employee::create($companyId, $v['full_name'], $positionId, $v['email'], $password);
                        Group::assignStudent((int) $group['id'], $studentId);
                        $company  = Company::find($companyId);
                        $employee = Employee::findById($studentId);
                        if ($company && $employee) {
                            $this->openSession($employee, $company);
                            $this->redirect('student/dashboard');
                        }
                        $this->redirect('auth/login');
                    } catch (\PDOException $e) {
                        error_log('Student registration failed: ' . $e->getMessage());
                        $errors[] = 'Не удалось зарегистрироваться. Попробуйте позже.';
                    }
                }
            }
        }

        $this->access('school', 'student', 'register', ['errors' => $errors, 'v' => $v]);
    }

    private function openSession(array $employee, array $company): void
    {
        $type = $company['type'] ?? 'company';
        session_regenerate_id(true);
        $_SESSION['company_id']    = (int) $company['id'];
        $_SESSION['company_name']  = $company['name'];
        $_SESSION['company_code']  = $company['code'];
        $_SESSION['company_type']  = $type;
        $_SESSION['employee_id']   = $employee['id'];
        $_SESSION['full_name']     = $employee['full_name'];
        $_SESSION['role']          = $employee['role'];
        $_SESSION['position']      = $employee['position_name'];
        $_SESSION['department']    = $employee['department_name'];
        $_SESSION['last_activity'] = time();

        unset($_SESSION['verify']);
        if (($employee['role'] ?? '') !== 'admin') {
            try {
                $_SESSION['verify'] = Verification::issue((int) $employee['id'], (int) $company['id']);
                $_SESSION['verify_next_at'] = time() + Settings::verifyInterval();
            } catch (\Throwable $e) {
                error_log('Verification issue on login: ' . $e->getMessage());
            }
        }

        if ($type !== 'school') {
            Attendance::clockIn((int) $employee['id'], 'auto');
        }

        if ($employee['role'] === 'admin' || $type === 'school') {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + 30 * 24 * 60 * 60,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $options = ['expires' => time() + 365 * 24 * 60 * 60, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'];
        setcookie('company_code', $company['code'], $options);
        setcookie(
            'auth_mode',
            $type === 'school' ? ('school.' . ($employee['role'] === 'student' ? 'student' : 'staff')) : 'company.staff',
            $options
        );
    }

    public function logout(): void
    {
        if (!empty($_SESSION['employee_id']) && ($_SESSION['company_type'] ?? 'company') !== 'school') {
            try {
                BreakTime::end((int) $_SESSION['employee_id']);
            } catch (\Throwable $e) {
                error_log('Break end on logout: ' . $e->getMessage());
            }
            Attendance::clockOut((int) $_SESSION['employee_id'], 'auto');
        }
        $_SESSION = [];
        session_destroy();
        $this->redirect('auth/login');
    }

    public function verify(): void
    {
        $this->requireAuth(false, false);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Неверный запрос.']);
        }

        $challenge = $_SESSION['verify'] ?? null;
        $token = (string) ($_POST['token'] ?? '');
        $answer = trim((string) ($_POST['answer'] ?? ''));

        if (!$challenge || !hash_equals((string) ($challenge['token'] ?? ''), $token)) {
            $this->json(['ok' => false, 'error' => 'Проверка обновилась. Сейчас покажем актуальную.', 'refresh' => true]);
        }

        try {
            $result = Verification::answer($challenge, (int) $_SESSION['employee_id'], $answer);
        } catch (\Throwable $e) {
            error_log('Verification answer: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'Не удалось проверить код. Попробуйте ещё раз.']);
        }

        if ($result['status'] === 'ok') {
            unset($_SESSION['verify']);
            $_SESSION['verify_next_at'] = time() + Settings::verifyInterval();
            $this->json(['ok' => true]);
        }

        if ($result['status'] === 'wrong') {
            $this->json([
                'ok'            => false,
                'error'         => 'Неверное число. Попробуйте ещё раз.',
                'attempts_left' => $result['attempts_left'],
            ]);
        }

        $reason = $result['status'] === 'expired'
            ? 'Проверка присутствия не пройдена за 5 минут'
            : 'Три неверных ответа подряд при проверке присутствия';
        $failure = $this->failVerification($reason);
        $this->json(array_merge(['ok' => false, 'expired' => true], $failure));
    }

    public function verifyChallenge(): void
    {
        $this->requireAuth(false, false);

        $challenge = $_SESSION['verify'] ?? null;
        if ($challenge) {
            try {
                $expired = Verification::isExpired($challenge, (int) $_SESSION['employee_id']);
            } catch (\Throwable $e) {
                $expired = false;
            }
            if ($expired) {
                $failure = $this->failVerification('Проверка присутствия не пройдена за 5 минут');
                $this->json(array_merge(['ok' => false, 'expired' => true, 'pending' => false], $failure));
            }
            $this->json(['ok' => true, 'pending' => true, 'verify' => $this->publicChallenge($challenge)]);
        }

        $this->maybeIssueVerification();
        $challenge = $_SESSION['verify'] ?? null;
        if (!$challenge) {
            $this->json(['ok' => true, 'pending' => false]);
        }

        $this->json(['ok' => true, 'pending' => true, 'verify' => $this->publicChallenge($challenge)]);
    }

    public function verifyExpire(): void
    {
        $this->requireAuth(false, false);

        $challenge = $_SESSION['verify'] ?? null;
        if (!$challenge) {
            $this->json(['ok' => true, 'pending' => false]);
        }

        try {
            Verification::expire($challenge, (int) $_SESSION['employee_id']);
        } catch (\Throwable $e) {
            error_log('Verification expire: ' . $e->getMessage());
        }

        $failure = $this->failVerification('Проверка присутствия не пройдена за 5 минут');
        $this->json(array_merge(['ok' => false, 'expired' => true, 'pending' => false], $failure));
    }

    private function publicChallenge(array $challenge): array
    {
        return [
            'token'      => (string) ($challenge['token'] ?? ''),
            'code'       => (int) ($challenge['code'] ?? 0),
            'options'    => array_values(array_map('intval', (array) ($challenge['options'] ?? []))),
            'expires_at' => (int) ($challenge['expires_at'] ?? 0),
            'attempts'   => (int) ($challenge['attempts'] ?? 0),
            'server_now' => time(),
        ];
    }
}
