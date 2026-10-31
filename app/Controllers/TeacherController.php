<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Services\LessonExporter;

class TeacherController extends Controller
{
    private function flash(string $message, string $type = 'ok'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }

    private function ownLesson(array $session, int $id): ?array
    {
        return Lesson::findForTeacher($id, (int) $session['employee_id'], $this->companyId());
    }

    public function dashboard(): void
    {
        $session = $this->requireTeacher();
        $teacherId = (int) $session['employee_id'];
        $companyId = $this->companyId();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->view('teacher/dashboard', [
            'upcoming' => Lesson::listForTeacher($teacherId, $companyId, 'upcoming', 50),
            'past'     => Lesson::listForTeacher($teacherId, $companyId, 'past', 15),
            'groups'   => Group::forTeacher($teacherId, $companyId),
            'flash'    => $flash,
        ]);
    }

    public function lesson(): void
    {
        $session = $this->requireTeacher();
        $lesson = $this->ownLesson($session, (int) ($_GET['id'] ?? 0));
        if (!$lesson) {
            $this->redirect('teacher/dashboard');
        }
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->view('teacher/lesson', [
            'lesson'   => $lesson,
            'status'   => Lesson::status($lesson),
            'canStart' => Lesson::canStart($lesson),
            'flash'    => $flash,
        ]);
    }

    public function lessonStart(): void
    {
        $session = $this->requireTeacher();
        $id = (int) ($_POST['id'] ?? 0);
        $lesson = $this->ownLesson($session, $id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lesson) {
            if (Lesson::canStart($lesson)) {
                Lesson::start($id, $this->companyId());
            } elseif (Lesson::status($lesson) === 'scheduled') {
                $this->flash('Начать занятие можно не раньше чем за ' . Lesson::START_EARLY_MINUTES . ' минут до начала.', 'error');
            }
        }
        $this->redirect('teacher/lesson&id=' . $id);
    }

    public function lessonStatus(): void
    {
        $session = $this->requireTeacher();
        $lesson = $this->ownLesson($session, (int) ($_GET['id'] ?? 0));
        if (!$lesson) {
            $this->json(['ok' => false]);
        }

        $now = time();
        $roster = LessonAttendance::roster($lesson, $now);

        $this->json([
            'ok'       => true,
            'status'   => Lesson::status($lesson, $now),
            'left'     => max(0, strtotime($lesson['scheduled_end']) - $now),
            'total'    => count($roster),
            'present'  => count(array_filter($roster, fn ($r) => $r['status'] !== 'absent')),
            'students' => $roster,
        ]);
    }

    public function lessonClose(): void
    {
        $session = $this->requireTeacher();
        $id = (int) ($_POST['id'] ?? 0);
        $lesson = $this->ownLesson($session, $id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lesson && Lesson::isOpen($lesson)) {
            Lesson::close($id, $this->companyId());
            $this->flash('Занятие завершено.');
        }
        $this->redirect('teacher/lesson&id=' . $id);
    }

    public function lessonMark(): void
    {
        $session = $this->requireTeacher();
        $lessonId = (int) ($_POST['lesson_id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $lesson = $this->ownLesson($session, $lessonId);
        $done = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lesson && !empty($lesson['started_at'])) {
            $groupId = null;
            foreach (LessonAttendance::roster($lesson, time()) as $r) {
                if ($r['student_id'] === $studentId) {
                    $group = Group::ofStudent($studentId);
                    $groupId = $group ? (int) $group['id'] : null;
                    $done = true;
                }
            }
            if ($done) {
                if (($_POST['do'] ?? '') === 'remove') {
                    LessonAttendance::remove($lessonId, $studentId);
                } else {
                    LessonAttendance::markManual($lessonId, $studentId, $groupId);
                }
            }
        }
        $this->json(['ok' => $done]);
    }

    public function lessonExport(): void
    {
        $session = $this->requireTeacher();
        $lesson = $this->ownLesson($session, (int) ($_GET['id'] ?? 0));
        if (!$lesson) {
            $this->redirect('teacher/dashboard');
        }
        LessonExporter::send($lesson);
    }
}
