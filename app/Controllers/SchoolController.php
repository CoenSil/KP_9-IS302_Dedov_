<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Password;
use App\Core\Validator;
use App\Models\Employee;
use App\Models\Company;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Position;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Verification;
use App\Services\LessonExporter;

class SchoolController extends Controller
{
    private function flash(string $message, string $type = 'ok'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    private function back(string $fallback): void
    {
        $back = (string) ($_POST['back'] ?? '');
        $this->redirect(preg_match('/^school\/(people|groups|group&id=\d+|lessons)$/', $back) ? $back : $fallback);
    }

    public function dashboard(): void
    {
        $this->requireSchoolAdmin();
        $companyId = $this->companyId();

        $collapseCount = 0;
        $collapseFeed = [];
        try {
            $collapseCount = Verification::collapseCount($companyId);
            $collapseFeed = Verification::collapseFeed($companyId, null, 10);
        } catch (\Throwable $e) {
            error_log('Collapse feed: ' . $e->getMessage());
        }

        $this->view('school/dashboard', [
            'stats' => School::stats($companyId),
            'today' => Lesson::todayForCompany($companyId),
            'collapseCount' => $collapseCount,
            'collapseFeed' => $collapseFeed,
            'flash' => $this->takeFlash(),
        ]);
    }

    public function groups(): void
    {
        $this->requireSchoolAdmin();
        $companyId = $this->companyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                $this->flash('Укажите название группы (до 255 символов).', 'error');
            } elseif (Group::nameExists($companyId, $name)) {
                $this->flash('Группа с таким названием уже есть.', 'error');
            } else {
                $id = Group::create($companyId, $name);
                $this->flash('Группа «' . $name . '» создана. Назначьте ей преподавателей и раздайте студентам инвайт-код.');
                $this->redirect('school/group&id=' . $id);
            }
            $this->redirect('school/groups');
        }

        $this->view('school/groups', ['groups' => Group::forCompany($companyId), 'flash' => $this->takeFlash()]);
    }

    public function group(): void
    {
        $this->requireSchoolAdmin();
        $companyId = $this->companyId();
        $group = Group::find((int) ($_GET['id'] ?? 0), $companyId);
        if (!$group) {
            $this->redirect('school/groups');
        }

        $this->view('school/group', [
            'group'       => $group,
            'students'    => Group::students((int) $group['id']),
            'teachers'    => Employee::listByRole($companyId, 'teacher'),
            'assigned'    => Group::teacherIds((int) $group['id']),
            'allGroups'   => Group::forCompany($companyId),
            'registerUrl' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . 'index.php?route=auth/register-student&code=' . $group['invite_code'],
            'flash'       => $this->takeFlash(),
        ]);
    }

    public function groupRotate(): void
    {
        $this->requireSchoolAdmin();
        $id = (int) ($_POST['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Group::find($id, $this->companyId())) {
            Group::rotateCode($id, $this->companyId());
            $this->flash('Инвайт-код обновлён. Старый код больше не действует.');
        }
        $this->redirect('school/group&id=' . $id);
    }

    public function groupTeachers(): void
    {
        $this->requireSchoolAdmin();
        $id = (int) ($_POST['group_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && Group::find($id, $this->companyId())) {
            $ids = isset($_POST['teachers']) && is_array($_POST['teachers']) ? $_POST['teachers'] : [];
            Group::setTeachers($id, $this->companyId(), $ids);
            $this->flash('Преподаватели группы обновлены.');
        }
        $this->redirect('school/group&id=' . $id);
    }

    public function studentMove(): void
    {
        $this->requireSchoolAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ok = Group::moveStudent((int) ($_POST['student_id'] ?? 0), (int) ($_POST['group_id'] ?? 0), $this->companyId());
            $this->flash($ok ? 'Студент переведён в группу.' : 'Не удалось перевести студента.', $ok ? 'ok' : 'error');
        }
        $this->back('school/people');
    }

    public function studentRemove(): void
    {
        $this->requireSchoolAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Group::removeStudent((int) ($_POST['student_id'] ?? 0), $this->companyId());
            $this->flash('Студент откреплён от группы. Пока он не прикреплён к группе, отметиться на занятиях не сможет.');
        }
        $this->back('school/people');
    }

    public function people(): void
    {
        $session = $this->requireSchoolAdmin();
        $companyId = $this->companyId();
        $errors = [];
        $created = null;
        $v = ['role' => 'teacher', 'full_name' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $v['role']      = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'teacher';
            $v['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
            $v['email']     = Validator::normalizeEmail((string) ($_POST['email'] ?? ''));
            $password       = trim((string) ($_POST['password'] ?? ''));

            if ($v['full_name'] === '') {
                $errors[] = 'Укажите ФИО.';
            }
            $emailError = Validator::emailError($v['email']);
            if ($emailError !== null) {
                $errors[] = $emailError;
            } elseif (Employee::emailExists($v['email'], $companyId)) {
                $errors[] = 'Пользователь с таким email уже есть.';
            }
            if ($password !== '' && mb_strlen($password) < 6) {
                $errors[] = 'Пароль должен быть не короче 6 символов.';
            }
            $positionId = $v['role'] === 'admin'
                ? (Position::idFor($companyId, 'admin', 'Администратор') ?? Position::idFor($companyId, 'admin'))
                : Position::idFor($companyId, 'teacher');
            if (!$positionId) {
                $errors[] = 'Не найдена должность для выбранной роли.';
            }

            $limit = Subscription::limit(Company::find($companyId), 'employees');
            if ($limit > 0 && Company::countEmployees($companyId) >= $limit) {
                $errors[] = 'Достигнут лимит тарифа: ' . $limit . ' человек. Чтобы добавить больше, напишите нам — расширим тариф.';
            }

            if (empty($errors)) {
                $plain = $password !== '' ? $password : Password::generate();
                Employee::create($companyId, $v['full_name'], $positionId, $v['email'], $plain);
                $created = [
                    'name' => $v['full_name'], 'email' => $v['email'], 'role' => $v['role'],
                    'password' => $password === '' ? $plain : null,
                ];
                $v = ['role' => $v['role'], 'full_name' => '', 'email' => ''];
            }
        }

        $this->view('school/people', [
            'admins'    => Employee::listByRole($companyId, 'admin'),
            'teachers'  => Employee::listByRole($companyId, 'teacher'),
            'students'  => Employee::listByRole($companyId, 'student'),
            'groups'    => Group::forCompany($companyId),
            'teacherGroups' => Group::teacherMap($companyId),
            'errors'    => $errors,
            'created'   => $created,
            'v'         => $v,
            'selfId'    => (int) $session['employee_id'],
            'flash'     => $this->takeFlash(),
        ]);
    }

    public function lessons(): void
    {
        $session = $this->requireSchoolAdmin();
        $companyId = $this->companyId();
        $errors = [];
        $v = ['title' => '', 'kind' => 'lecture', 'room' => '', 'date' => date('Y-m-d'), 'start' => '09:00', 'end' => '10:30',
              'late' => '10', 'teacher_id' => '', 'groups' => []];

        $teachers = Employee::listByRole($companyId, 'teacher');
        $groups   = Group::forCompany($companyId);
        $teacherMap = Group::teacherMap($companyId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $v['title']      = trim((string) ($_POST['title'] ?? ''));
            $v['kind']       = isset(Lesson::KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'lecture';
            $v['room']       = trim((string) ($_POST['room'] ?? ''));
            $v['date']       = trim((string) ($_POST['date'] ?? ''));
            $v['start']      = trim((string) ($_POST['start'] ?? ''));
            $v['end']        = trim((string) ($_POST['end'] ?? ''));
            $v['late']       = trim((string) ($_POST['late'] ?? '10'));
            $v['teacher_id'] = (string) (int) ($_POST['teacher_id'] ?? 0);
            $v['groups']     = array_values(array_unique(array_map('intval', is_array($_POST['groups'] ?? null) ? $_POST['groups'] : [])));

            $teacherId = (int) $v['teacher_id'];
            $teacherOk = in_array($teacherId, array_map('intval', array_column($teachers, 'id')), true);
            $companyGroupIds = array_map('intval', array_column($groups, 'id'));

            if ($v['title'] === '' || mb_strlen($v['title']) > 255) {
                $errors[] = 'Укажите название занятия (до 255 символов).';
            }
            if ($v['room'] === '' || mb_strlen($v['room']) > 64) {
                $errors[] = 'Укажите аудиторию (до 64 символов).';
            }
            if (!$teacherOk) {
                $errors[] = 'Выберите преподавателя.';
            }
            if (empty($v['groups'])) {
                $errors[] = 'Выберите хотя бы одну группу.';
            } elseif (array_diff($v['groups'], $companyGroupIds)) {
                $errors[] = 'Выбрана неизвестная группа.';
            } elseif ($teacherOk && array_diff($v['groups'], $teacherMap[$teacherId] ?? [])) {
                $errors[] = 'Все выбранные группы должны быть назначены этому преподавателю (на странице группы).';
            }

            $start = \DateTime::createFromFormat('Y-m-d H:i', $v['date'] . ' ' . $v['start']);
            $end   = \DateTime::createFromFormat('Y-m-d H:i', $v['date'] . ' ' . $v['end']);
            if (!$start || !$end) {
                $errors[] = 'Укажите корректные дату и время.';
            } elseif ($end <= $start) {
                $errors[] = 'Время окончания должно быть позже начала.';
            } elseif ($end->getTimestamp() - $start->getTimestamp() > 12 * 3600) {
                $errors[] = 'Занятие не может длиться больше 12 часов.';
            } elseif ($end->getTimestamp() <= time()) {
                $errors[] = 'Занятие должно заканчиваться в будущем.';
            }
            $late = (int) $v['late'];
            if ($late < 0 || $late > 120) {
                $errors[] = 'Опоздание: от 0 до 120 минут.';
            }

            if (empty($errors)) {
                $conflicts = Lesson::conflicts($companyId, $teacherId, $v['room'], $v['groups'], $start->getTimestamp(), $end->getTimestamp());
                $errors = array_merge($errors, $conflicts);
            }

            if (empty($errors)) {
                Lesson::create(
                    $companyId, (int) $session['employee_id'], $teacherId, $v['title'], $v['kind'], $v['room'],
                    $start->getTimestamp(), $end->getTimestamp(), $late, $v['groups']
                );
                $this->flash('Занятие добавлено в расписание.');
                $this->redirect('school/lessons');
            }
        }

        $this->view('school/lessons', [
            'upcoming'   => Lesson::listForCompany($companyId, 'upcoming', 100),
            'past'       => Lesson::listForCompany($companyId, 'past', 30),
            'teachers'   => $teachers,
            'groups'     => $groups,
            'teacherMap' => $teacherMap,
            'errors'     => $errors,
            'v'          => $v,
            'flash'      => $this->takeFlash(),
        ]);
    }

    public function lessonDelete(): void
    {
        $this->requireSchoolAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ok = Lesson::deleteIfNotStarted((int) ($_POST['id'] ?? 0), $this->companyId());
            $this->flash($ok ? 'Занятие удалено из расписания.' : 'Начатое занятие удалить нельзя.', $ok ? 'ok' : 'error');
        }
        $this->redirect('school/lessons');
    }

    public function lesson(): void
    {
        $this->requireSchoolAdmin();
        $lesson = Lesson::find((int) ($_GET['id'] ?? 0), $this->companyId());
        if (!$lesson) {
            $this->redirect('school/lessons');
        }
        $this->view('school/lesson', [
            'lesson' => $lesson,
            'roster' => LessonAttendance::roster($lesson, time()),
            'status' => Lesson::status($lesson),
        ]);
    }

    public function lessonExport(): void
    {
        $this->requireSchoolAdmin();
        $lesson = Lesson::find((int) ($_GET['id'] ?? 0), $this->companyId());
        if (!$lesson) {
            $this->redirect('school/lessons');
        }
        LessonExporter::send($lesson);
    }
}
