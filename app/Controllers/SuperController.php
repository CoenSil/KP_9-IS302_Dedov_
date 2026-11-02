<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\Subscription;

class SuperController extends Controller
{
    public function login(): void
    {
        if (!empty($_SESSION['super_id'])) {
            $this->redirect('super/companies');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $gate     = \App\Core\RateLimit::hit('super:login:' . \App\Core\Security::clientIp(), 10, 600);

            if (!$gate['allowed']) {
                $error = 'Слишком много попыток входа. Попробуйте '
                    . \App\Core\RateLimit::retryText($gate['retry']) . '.';
            } else {
                $admin = PlatformAdmin::findByEmail($email);

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION = [
                        'super_id'  => (int) $admin['id'],
                        'full_name' => $admin['full_name'],
                        'role'      => 'superadmin',
                    ];
                    $this->redirect('super/companies');
                }
                $error = 'Неверный email или пароль.';
            }
        }

        $this->view('super/login', ['error' => $error]);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect('super/login');
    }

    public function companies(): void
    {
        $this->requireSuper();
        $this->view('super/companies', [
            'companies' => Company::allWithStats(),
            'flash'     => $_SESSION['flash'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function companyCreate(): void
    {
        $this->requireSuper();

        $errors = [];
        $v = ['type' => 'company', 'name' => '', 'code' => '', 'admin_name' => '', 'admin_email' => '', 'password' => ''];
        $created = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($v as $k => $_) {
                $v[$k] = trim((string) ($_POST[$k] ?? ''));
            }
            $v['code'] = Company::normalizeCode($v['code']);
            $v['type'] = Company::normalizeType($v['type']);
            $v['admin_email'] = Validator::normalizeEmail($v['admin_email']);

            if ($v['name'] === '') {
                $errors[] = 'Укажите название компании.';
            }
            if (!Company::isValidCode($v['code'])) {
                $errors[] = 'Код компании: 2–64 символа, только латиница, цифры и дефис (например, romashka).';
            } elseif (Company::codeExists($v['code'])) {
                $errors[] = 'Компания с таким кодом уже существует.';
            }
            if ($v['admin_name'] === '') {
                $errors[] = 'Укажите ФИО администратора.';
            }
            $emailError = Validator::emailError($v['admin_email'], 'Email администратора');
            if ($emailError !== null) {
                $errors[] = $emailError;
            }
            if ($v['password'] !== '' && mb_strlen($v['password']) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов.';
            }

            if (empty($errors)) {
                $plain = $v['password'] !== '' ? $v['password'] : self::generatePassword();
                try {
                    $id = Company::createWithAdmin($v['name'], $v['code'], $v['admin_name'], $v['admin_email'], $plain, $v['type']);
                    $created = [
                        'id'       => $id,
                        'name'     => $v['name'],
                        'code'     => $v['code'],
                        'email'    => $v['admin_email'],
                        'password' => $v['password'] === '' ? $plain : null,
                    ];
                    $v = array_map(fn () => '', $v);
                    $v['type'] = 'company';
                } catch (\Throwable $e) {
                    error_log('Company create failed: ' . $e->getMessage());
                    $errors[] = 'Не удалось создать организацию. Убедитесь, что выполнены миграции из папки sql.';
                }
            }
        }

        $this->view('super/company_create', ['errors' => $errors, 'v' => $v, 'created' => $created]);
    }

    public function companyPlan(): void
    {
        $this->requireSuper();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id      = (int) ($_POST['id'] ?? 0);
            $company = $id > 0 ? Company::find($id) : null;

            if ($company) {
                $plan = (string) ($_POST['plan'] ?? 'trial');
                if (!array_key_exists($plan, Subscription::PLANS)) {
                    $plan = 'trial';
                }

                $paid  = $this->postDate($_POST['paid_until'] ?? '');
                $trial = $this->postDate($_POST['trial_ends_at'] ?? '');
                $maxE  = max(0, (int) ($_POST['max_employees'] ?? 0));
                $maxS  = max(0, (int) ($_POST['max_students'] ?? 0));

                try {
                    Company::setSubscription($id, $plan, $paid, $trial, $maxE, $maxS);
                    $_SESSION['flash'] = 'Тариф компании «' . $company['name'] . '» обновлён.';
                } catch (\Throwable $e) {
                    error_log('Company plan update: ' . $e->getMessage());
                    $_SESSION['flash'] = 'Не удалось обновить тариф. Проверьте, что выполнена sql/migration_saas.sql.';
                }
            }
        }

        $this->redirect('super/companies');
    }

    public function leads(): void
    {
        $this->requireSuper();

        $leads = [];
        try {
            $leads = Database::connect()
                ->query('SELECT * FROM leads ORDER BY id DESC LIMIT 200')
                ->fetchAll();
        } catch (\Throwable $e) {
            error_log('Leads list: ' . $e->getMessage());
        }

        $this->view('super/leads', ['leads' => $leads]);
    }

    private function postDate($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 23:59:59' : null;
    }

    public function companyToggle(): void
    {
        $this->requireSuper();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int) ($_POST['id'] ?? 0);
            $company = $id ? Company::find($id) : null;
            if ($company) {
                $newState = (int) $company['is_active'] !== 1;
                Company::setActive($id, $newState);
                $_SESSION['flash'] = 'Компания «' . $company['name'] . '» ' . ($newState ? 'включена.' : 'отключена: вход закрыт, данные сохранены.');
            }
        }
        $this->redirect('super/companies');
    }

    private static function generatePassword(int $length = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
