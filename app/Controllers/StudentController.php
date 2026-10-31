<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\BreakTime;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Verification;

class StudentController extends Controller
{
    private const MAX_CODE_FAILS = 5;
    private const LOCK_SECONDS = 60;

    public function dashboard(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];
        $group = Group::ofStudent($studentId);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->view('student/dashboard', [
            'group'       => $group,
            'openLessons' => $group ? Lesson::openForGroup((int) $group['id']) : [],
            'history'     => Lesson::historyForStudent($studentId, $group ? (int) $group['id'] : null, $group['joined_at'] ?? null, 40),
            'flash'       => $flash,
        ]);
    }

    private function failCode(string $message): void
    {
        $throttle = $_SESSION['checkin_fail'] ?? ['n' => 0, 'until' => 0];
        $throttle['n']++;
        if ($throttle['n'] >= self::MAX_CODE_FAILS) {
            $throttle = ['n' => 0, 'until' => time() + self::LOCK_SECONDS];
        }
        $_SESSION['checkin_fail'] = $throttle;
        $this->json(['ok' => false, 'error' => $message]);
    }

    public function checkin(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];

        $throttle = $_SESSION['checkin_fail'] ?? ['n' => 0, 'until' => 0];
        if ($throttle['until'] > time()) {
            $this->json(['ok' => false, 'error' => 'Слишком много попыток. Подождите ' . ($throttle['until'] - time()) . ' с.']);
        }

        $group = Group::ofStudent($studentId);
        if (!$group) {
            $this->json(['ok' => false, 'error' => 'Вы не прикреплены к группе. Обратитесь к администратору.']);
        }

        $code = Lesson::normalizeCode((string) ($_POST['code'] ?? ''));
        $lesson = strlen($code) === 6 ? Lesson::findOpenByCode($this->companyId(), $code) : null;
        if (!$lesson) {
            $this->failCode('Неверный код или занятие уже завершено.');
        }

        if (!in_array((int) $group['id'], Lesson::groupIds((int) $lesson['id']), true)) {
            $this->failCode('Вход запрещён: ваша группа не участвует в этом занятии.');
        }
        unset($_SESSION['checkin_fail']);

        $device = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['device_id'] ?? ''));
        $row = LessonAttendance::checkIn(
            $lesson, $studentId, (int) $group['id'], (string) $device, (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        $this->json(['ok' => true, 'status' => $row['status'], 'lesson_id' => (int) $lesson['id']]);
    }

    public function lesson(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];
        $lesson = Lesson::find((int) ($_GET['id'] ?? 0), $this->companyId());
        $row = $lesson ? LessonAttendance::find((int) $lesson['id'], $studentId) : null;
        if (!$lesson || !$row) {
            $this->redirect('student/dashboard');
        }

        $this->view('student/lesson', [
            'lesson' => [
                'id' => (int) $lesson['id'], 'title' => $lesson['title'], 'kind' => $lesson['kind'], 'room' => $lesson['room'],
                'teacher_name' => $lesson['teacher_name'], 'scheduled_start' => $lesson['scheduled_start'],
                'scheduled_end' => $lesson['scheduled_end'],
            ],
            'open' => Lesson::isOpen($lesson),
            'row'  => $row,
            'break' => $this->breakState($studentId),
        ]);
    }

    public function heartbeat(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];
        $lesson = Lesson::find((int) ($_POST['lesson_id'] ?? 0), $this->companyId());

        if (!$lesson) {
            $this->json(['ok' => false]);
        }
        if (!Lesson::isOpen($lesson)) {
            $this->json(['ok' => true, 'open' => false]);
        }

        $onBreak = false;
        try {
            $onBreak = (bool) BreakTime::state($studentId)['active'];
        } catch (\Throwable $e) {
            error_log('Student break state: ' . $e->getMessage());
        }

        $row = LessonAttendance::heartbeat(
            $lesson, $studentId,
            ($_POST['visible'] ?? '1') === '1',
            ($_POST['interacted'] ?? '0') === '1',
            $onBreak
        );
        if (!$row) {
            $this->json(['ok' => false, 'error' => 'not_checked_in']);
        }

        $this->json([
            'ok'     => true,
            'open'   => true,
            'left'   => max(0, strtotime($lesson['scheduled_end']) - time()),
            'active' => (int) $row['active_seconds'],
            'hidden' => (int) $row['hidden_seconds'],
            'away'   => (int) $row['away_count'],
            'on_break' => $onBreak,
        ]);
    }

    private function breakState(int $studentId): array
    {
        try {
            return BreakTime::state($studentId);
        } catch (\Throwable $e) {
            error_log('Student break state: ' . $e->getMessage());
            return [
                'active' => false, 'until' => null, 'used' => 0, 'remaining' => 0,
                'max' => 0, 'count' => 0, 'count_max' => 0, 'mode' => 'none',
                'windows' => [], 'next' => null, 'segments' => [], 'ranges' => [],
            ];
        }
    }

    public function breakStart(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];

        $result = ['ok' => false, 'error' => 'Неверный запрос.'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = BreakTime::start($studentId);
            } catch (\Throwable $e) {
                error_log('Student break start: ' . $e->getMessage());
                $result = ['ok' => false, 'error' => 'Не удалось начать перерыв. Попробуйте ещё раз.'];
            }
        }
        $this->json($result + ['break' => $this->breakState($studentId)]);
    }

    public function breakEnd(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                BreakTime::end($studentId);
            } catch (\Throwable $e) {
                error_log('Student break end: ' . $e->getMessage());
            }
        }
        $this->json(['ok' => true, 'break' => $this->breakState($studentId)]);
    }

    /**
     * Отметка о слишком маленьком окне браузера. Приходит не чаще
     * одного раза в 5 минут, чтобы не засорять журнал.
     */
    public function attention(): void
    {
        $session = $this->requireStudent();
        $studentId = (int) $session['employee_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $last = (int) ($_SESSION['attention_last'] ?? 0);
            if (time() - $last >= 300) {
                $_SESSION['attention_last'] = time();
                try {
                    Verification::mark(
                        $studentId,
                        $this->companyId(),
                        'app_collapsed',
                        'Окно занятия уменьшено больше чем наполовину'
                    );
                } catch (\Throwable $e) {
                    error_log('Student attention: ' . $e->getMessage());
                }
            }
        }
        $this->json(['ok' => true]);
    }
}
