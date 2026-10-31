<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyHours;
use App\Models\Employee;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TimesheetExporter
{
    private const FILL_HEADER   = 'FFE4E1D6';
    private const FILL_LATE     = 'FFF6EAD5';
    private const FILL_OVERTIME = 'FFF5E1DB';

    public static function buildWeek(int $companyId, string $weekStart, string $weekEnd, ?int $departmentId, ?string $orgName): string
    {
        self::requireLibrary();

        $employees = Employee::allWithDepartment($companyId, $departmentId);
        $attendance = Attendance::forRange($weekStart, $weekEnd, $companyId);
        $hoursByKey = DailyHours::forRange($weekStart, $weekEnd);

        $byKey = [];
        foreach ($attendance as $row) {
            $byKey[$row['employee_id'] . '|' . $row['work_date']] = $row;
        }

        $days = self::daysBetween($weekStart, $weekEnd);
        $dayCount = count($days);
        $lastCol = 3 + $dayCount + 2;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Табель');

        $r = 1;

        $sheet->setCellValue('A' . $r, $orgName ?: 'Организация не указана');
        $sheet->mergeCells('A' . $r . ':' . self::colLetter($lastCol) . $r);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
        $r++;

        $sheet->setCellValue('A' . $r, 'Табель учёта рабочего времени');
        $sheet->mergeCells('A' . $r . ':' . self::colLetter($lastCol) . $r);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
        $r++;

        $periodLabel = 'За период: ' . self::ru($weekStart) . ' — ' . self::ru($weekEnd)
            . ($departmentId ? '' : ' (все подразделения)');
        $sheet->setCellValue('A' . $r, $periodLabel);
        $sheet->mergeCells('A' . $r . ':' . self::colLetter($lastCol) . $r);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true);
        $r += 2;

        $headerRow = $r;
        $sheet->setCellValue('A' . $r, '№');
        $sheet->setCellValue('B' . $r, 'ФИО');
        $sheet->setCellValue('C' . $r, 'Таб. №');
        $col = 4;
        foreach ($days as $day) {
            $sheet->setCellValue(self::colLetter($col) . $r, self::ru($day, true));
            $col++;
        }
        $sheet->setCellValue(self::colLetter($col) . $r, 'Отработано дней');
        $col++;
        $sheet->setCellValue(self::colLetter($col) . $r, 'Отработано часов');

        $headerRange = 'A' . $r . ':' . self::colLetter($lastCol) . $r;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FILL_HEADER]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(30);
        $r++;

        $num = 1;
        foreach ($employees as $emp) {
            $codeRow = $r;
            $hourRow = $r + 1;
            $daysWorked = 0;

            $sheet->setCellValue('A' . $codeRow, $num);
            $sheet->setCellValue('B' . $codeRow, $emp['full_name']);
            $sheet->setCellValue('C' . $codeRow, $emp['id']);
            $sheet->mergeCells('A' . $codeRow . ':A' . $hourRow);
            $sheet->mergeCells('B' . $codeRow . ':B' . $hourRow);
            $sheet->mergeCells('C' . $codeRow . ':C' . $hourRow);
            $sheet->getStyle('B' . $codeRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

            $col = 4;
            foreach ($days as $day) {
                $att = $byKey[$emp['id'] . '|' . $day] ?? null;
                $codeCellRef = self::colLetter($col) . $codeRow;
                $hourCellRef = self::colLetter($col) . $hourRow;

                if (!$att || !$att['last_login_at']) {
                    $sheet->setCellValue($codeCellRef, 'Н');
                    $col++;
                    continue;
                }

                $daysWorked++;

                $plannedLogin  = $att['planned_login'];
                $plannedLogout = $att['planned_logout'];

                $sheet->setCellValue($codeCellRef, 'Я');
                if ($plannedLogin && strtotime($att['last_login_at']) > strtotime($day . ' ' . $plannedLogin)) {
                    $sheet->getStyle($codeCellRef)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::FILL_LATE);
                }

                $daySeconds = $hoursByKey[$emp['id'] . '|' . $day] ?? 0;

                if ($daySeconds > 0) {
                    $sheet->setCellValue($hourCellRef, round($daySeconds / 3600, 1));
                } elseif (!$att['last_logout_at']) {
                    $sheet->setCellValue($hourCellRef, '—');
                }

                if ($att['last_logout_at'] && $plannedLogout
                    && strtotime($att['last_logout_at']) > strtotime($day . ' ' . $plannedLogout) + 3600) {
                    $sheet->getStyle($hourCellRef)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::FILL_OVERTIME);
                }

                $col++;
            }

            $totalSeconds = 0;
            foreach ($days as $day) {
                $totalSeconds += $hoursByKey[$emp['id'] . '|' . $day] ?? 0;
            }

            $daysTotalCell = self::colLetter($col) . $codeRow;
            $sheet->setCellValue($daysTotalCell, $daysWorked);
            $sheet->getStyle($daysTotalCell)->getFont()->setBold(true);
            $col++;
            $hoursTotalCell = self::colLetter($col) . $hourRow;
            $sheet->setCellValue($hoursTotalCell, round($totalSeconds / 3600, 1));
            $sheet->getStyle($hoursTotalCell)->getFont()->setBold(true);

            $dataRange = 'A' . $codeRow . ':' . self::colLetter($lastCol) . $hourRow;
            $sheet->getStyle($dataRange)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $r += 2;
            $num++;
        }

        if (empty($employees)) {
            $sheet->setCellValue('A' . $r, 'Сотрудники не найдены.');
            $r++;
        }

        $r++;
        $sheet->setCellValue('A' . $r, 'Я — явка, Н — неявка (нет записи о входе). Часы — честная сумма по сессиям, не "вход-выход" за весь день.');
        $sheet->getStyle('A' . $r)->getFont()->setSize(9);
        $sheet->mergeCells('A' . $r . ':' . self::colLetter($lastCol) . $r);
        $r++;

        $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::FILL_LATE);
        $sheet->setCellValue('B' . $r, 'отмечено опоздание');
        $sheet->getStyle('B' . $r)->getFont()->setSize(9);
        $r++;

        $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::FILL_OVERTIME);
        $sheet->setCellValue('B' . $r, 'переработка больше часа');
        $sheet->getStyle('B' . $r)->getFont()->setSize(9);

        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(9);
        for ($c = 4; $c <= 3 + $dayCount; $c++) {
            $sheet->getColumnDimension(self::colLetter($c))->setWidth(7);
        }
        $sheet->getColumnDimension(self::colLetter(4 + $dayCount))->setWidth(13);
        $sheet->getColumnDimension(self::colLetter(5 + $dayCount))->setWidth(13);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    private static function daysBetween(string $from, string $to): array
    {
        $days = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $days[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }
        return $days;
    }

    private static function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $n = intdiv($n - 1, 26);
        }
        return $letter;
    }

    private static function ru(string $ymd, bool $short = false): string
    {
        $ts = strtotime($ymd);
        $days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
        $dow = $days[(int) date('w', $ts)];
        return $short ? (date('d.m', $ts) . "\n" . $dow) : (date('d.m.Y', $ts) . ' (' . $dow . ')');
    }

    private static function requireLibrary(): void
    {
        if (class_exists(Spreadsheet::class)) {
            return;
        }
        $autoload = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(Spreadsheet::class)) {
            throw new \RuntimeException(
                'Библиотека PhpSpreadsheet не установлена. На сервере, в корне проекта ' .
                '(там же, где composer.json), выполните через SSH: composer install'
            );
        }
    }
}
