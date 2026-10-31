<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonAttendance;

class LessonExporter
{
    public static function send(array $lesson): void
    {
        $labels = ['present' => 'Присутствовал', 'late' => 'Опоздал', 'manual' => 'Отмечен преподавателем', 'absent' => 'Отсутствовал'];
        $now = time();
        $filename = 'lesson_' . $lesson['id'] . '_' . date('Y-m-d', strtotime($lesson['scheduled_start'])) . '.csv';

        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Занятие', $lesson['title'], 'Тип', Lesson::KINDS[$lesson['kind']] ?? $lesson['kind'],
            'Аудитория', $lesson['room'], 'Преподаватель', $lesson['teacher_name'], 'Начало по расписанию', $lesson['scheduled_start'],
        ], ';');
        fputcsv($out, ['ФИО', 'Группа', 'Статус', 'Время отметки', 'Активность, %', 'Активных минут', 'Сворачиваний/потерь связи', 'Общее устройство'], ';');
        foreach (LessonAttendance::roster($lesson, $now) as $r) {
            fputcsv($out, [
                $r['full_name'],
                $r['group_name'],
                $labels[$r['status']] ?? $r['status'],
                $r['checked_in_at'] ?? '',
                $r['active_percent'] ?? '',
                $r['status'] === 'absent' ? '' : round($r['active_seconds'] / 60, 1),
                $r['status'] === 'absent' ? '' : $r['away_count'],
                $r['shared_device'] ? 'да' : '',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
