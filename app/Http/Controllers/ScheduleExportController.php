<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleGroupStatus;
use App\Models\ScheduleGroup;
use App\Services\ScheduleGrid;
use App\Services\ScheduleSpreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleExportController extends Controller
{
    /**
     * Download the group's schedule grid as a PDF.
     */
    public function pdf(ScheduleGroup $scheduleGroup, ScheduleGrid $grid): Response
    {
        $this->ensureDownloadable($scheduleGroup);

        return Pdf::loadView('exports.schedule-pdf', ['grid' => $grid->forGroup($scheduleGroup)])
            ->setPaper('a4', 'landscape')
            ->download($this->filename($scheduleGroup, 'pdf'));
    }

    /**
     * Download the group's schedule grid as an Excel workbook.
     */
    public function excel(
        ScheduleGroup $scheduleGroup,
        ScheduleGrid $grid,
        ScheduleSpreadsheet $spreadsheet,
    ): StreamedResponse {
        $this->ensureDownloadable($scheduleGroup);

        $workbook = $spreadsheet->build($grid->forGroup($scheduleGroup));

        return response()->streamDownload(function () use ($workbook): void {
            (new Xlsx($workbook))->save('php://output');
            $workbook->disconnectWorksheets();
        }, $this->filename($scheduleGroup, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Drafts are only downloadable from the admin pages, not the public route.
     */
    private function ensureDownloadable(ScheduleGroup $scheduleGroup): void
    {
        if (request()->routeIs('public.*') && $scheduleGroup->status !== ScheduleGroupStatus::Active) {
            abort(404);
        }
    }

    private function filename(ScheduleGroup $scheduleGroup, string $extension): string
    {
        return Str::slug($scheduleGroup->title).'.'.$extension;
    }
}
