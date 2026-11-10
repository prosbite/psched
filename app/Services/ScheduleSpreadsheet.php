<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Turns the grid produced by {@see ScheduleGrid} into an .xlsx workbook.
 *
 * The layout mirrors the on-screen grid: a grade banner row spans all columns,
 * and the time, subject, and uniform subject-teacher cells are merged across
 * their section rows to reproduce the page's rowspans. The document header
 * follows the school's official examination form.
 */
class ScheduleSpreadsheet
{
    private const COLUMNS = [
        'Time of Assessment',
        'Subject/Assessment',
        'Section',
        'Subject Teacher',
        'Proctor Assigned',
        'Room Assignment',
    ];

    /**
     * @param  array{title: string, description: string|null, exam_title: string, school_year: string|null, date_of_assessment: string|null, days: array<int, mixed>}  $grid
     */
    public function build(array $grid): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schedule');

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $row = 1;

        // Official letterhead: logos on the sides, school details centered.
        $sheet->getRowDimension(1)->setRowHeight(14);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(14);
        $sheet->getRowDimension(4)->setRowHeight(14);
        $sheet->getRowDimension(5)->setRowHeight(14);

        $sheet->setCellValue('B1', 'Republic of the Philippines');
        $sheet->setCellValue('B2', 'PHILIPPINE SCIENCE HIGH SCHOOL');
        $sheet->setCellValue('B3', 'CARAGA REGION CAMPUS IN BUTUAN CITY');
        $sheet->setCellValue('B4', 'DEPARTMENT OF SCIENCE AND TECHNOLOGY');
        $sheet->setCellValue('B5', 'OneDOST4U: Solutions and Opportunities for All');

        foreach ([1 => 8, 2 => 12, 3 => 8, 4 => 9, 5 => 7] as $line => $size) {
            $sheet->mergeCells("B{$line}:E{$line}");
            $sheet->getStyle('B'.$line)->getFont()->setSize($size);
            $sheet->getStyle('B'.$line)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getStyle('B2')->getFont()->setBold(true);
        $sheet->getStyle('B3')->getFont()->setBold(true);
        $sheet->getStyle('B4')->getFont()->setBold(true);
        $sheet->getStyle('B5')->getFont()->setItalic(true);

        $this->addLogo($sheet, public_path('images/pshs-logo.png'), 'B1', 54, -66);
        $this->addLogo($sheet, public_path('images/bagong-pilipinas.png'), 'F1', 54);

        $row = 7;

        $sheet->setCellValue('A'.$row, $grid['exam_title']);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        if (! empty($grid['school_year'])) {
            $sheet->setCellValue('A'.$row, 'School Year '.$grid['school_year']);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle('A'.$row)->getFont()->setSize(10);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        if (! empty($grid['date_of_assessment'])) {
            $sheet->setCellValue('A'.$row, 'DATE OF ASSESSMENT: '.$grid['date_of_assessment']);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(10);
            $sheet->getRowDimension($row)->setRowHeight(16);
            $row++;
        }

        foreach ($grid['days'] as $day) {
            $row++;

            if (count($grid['days']) > 1) {
                $sheet->setCellValue('A'.$row, 'DAY '.$day['day']);
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
                $this->fill($sheet, "A{$row}:{$lastColumn}{$row}", 'FFE5E7EB');
                $row++;
            }

            foreach (self::COLUMNS as $index => $column) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
                $sheet->setCellValueExplicit($cell, strtoupper($column), DataType::TYPE_STRING);
            }

            $headerRange = "A{$row}:{$lastColumn}{$row}";
            $this->fill($sheet, $headerRange, 'FFF9FAFB');
            $this->bottomBorder($sheet, $headerRange);
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(8);
            $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FF6B7280');
            $row++;

            foreach ($day['grades'] as $grade) {
                $sheet->setCellValue('A'.$row, 'GRADE '.$grade['grade']);
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->getColor()->setARGB('FF4B5563');
                $this->fill($sheet, "A{$row}:{$lastColumn}{$row}", 'FFF3F4F6');
                $this->bottomBorder($sheet, "A{$row}:{$lastColumn}{$row}");
                $row++;

                foreach ($grade['subjects'] as $group) {
                    if ($group['elective'] && ($group['placeholder'] ?? false)) {
                        $values = [
                            $group['time'],
                            $group['subject'].' (Elective)',
                            'NA',
                            'Subject Teachers',
                            'Subject Teachers',
                            'Classroom Assignment',
                        ];

                        $this->writeSingleRow($sheet, $row, $values, $lastColumn);
                        $row++;

                        continue;
                    }

                    $row = $this->writeSubjectGroup($sheet, $row, $group, $lastColumn);
                }

                $row++;
            }
        }

        $widths = [18, 26, 18, 26, 26, 18];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function writeSubjectGroup($sheet, int $row, array $group, string $lastColumn): int
    {
        $count = count($group['rows']);
        $start = $row;
        $end = $row + $count - 1;

        // Style the block before merging so every cell (including the ones
        // that later become part of a merge) receives the shared styling.
        $sheet->getStyle("A{$start}:{$lastColumn}{$end}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        for ($line = $start; $line <= $end; $line++) {
            $this->bottomBorder($sheet, "A{$line}:{$lastColumn}{$line}");
        }

        $this->rightBorder($sheet, "A{$start}:A{$end}");
        $this->rightBorder($sheet, "B{$start}:B{$end}");

        if ($group['uniform_teacher']) {
            $this->rightBorder($sheet, "D{$start}:D{$end}");
        }

        // Rowspan cells show their text vertically centered, like the page.
        foreach (['A', 'B'] as $column) {
            $sheet->getStyle("{$column}{$start}:{$column}{$end}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        if ($group['uniform_teacher']) {
            $sheet->getStyle("D{$start}:D{$end}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        foreach ($group['rows'] as $index => $dataRow) {
            if ($index === 0) {
                $subjectText = $group['subject'].(! empty($group['elective']) ? ' (Elective)' : '');

                $sheet->setCellValueExplicit('A'.$row, (string) $group['time'], DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$row, $subjectText, DataType::TYPE_STRING);

                if ($count > 1) {
                    $sheet->mergeCells("A{$start}:A{$end}");
                    $sheet->mergeCells("B{$start}:B{$end}");
                }
            }

            $section = $dataRow['section'].($dataRow['unplaced'] ? ' (Unplaced)' : '');
            $sheet->setCellValueExplicit('C'.$row, $section, DataType::TYPE_STRING);

            if ($dataRow['unplaced']) {
                $sheet->getStyle('C'.$row)->getFont()->getColor()->setARGB('FFB91C1C');
            }

            if ($index === 0 && $group['uniform_teacher']) {
                $sheet->setCellValueExplicit('D'.$row, (string) $group['uniform_teacher'], DataType::TYPE_STRING);

                if ($count > 1) {
                    $sheet->mergeCells("D{$start}:D{$end}");
                }
            } elseif (! $group['uniform_teacher']) {
                $sheet->setCellValueExplicit('D'.$row, (string) $dataRow['subject_teacher'], DataType::TYPE_STRING);
            }

            $sheet->setCellValueExplicit('E'.$row, (string) $dataRow['proctor'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$row, (string) $dataRow['room'], DataType::TYPE_STRING);

            $row++;
        }

        return $row;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function writeSingleRow($sheet, int $row, array $values, string $lastColumn): void
    {
        foreach ($values as $index => $value) {
            $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        }

        $range = "A{$row}:{$lastColumn}{$row}";
        $this->bottomBorder($sheet, $range);
        $sheet->getStyle($range)->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
    }

    private function addLogo($sheet, string $path, string $coordinates, int $height, int $offsetX = 0): void
    {
        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setPath($path);
        $drawing->setHeight($height);
        $drawing->setCoordinates($coordinates);
        $drawing->setOffsetX($offsetX);
        $drawing->setWorksheet($sheet);
    }

    private function fill($sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB($argb);
    }

    private function bottomBorder($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()
            ->getBottom()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    private function rightBorder($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()
            ->getRight()
            ->setBorderStyle(Border::BORDER_THIN);
    }
}
