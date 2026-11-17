<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} — Teacher Load</title>
    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
        }

        @page {
            margin: 18px;
        }

        body {
            color: #111827;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.05em;
            padding: 5px 6px;
            text-align: left;
            text-transform: uppercase;
        }

        tbody td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }

        tbody.teacher-block {
            page-break-inside: avoid;
        }

        tr {
            page-break-inside: avoid;
        }

        .muted {
            color: #6b7280;
        }

        .nowrap {
            white-space: nowrap;
        }

        table.letterhead,
        table.letterhead tbody td {
            border: none;
            padding: 0;
        }

        table.letterhead {
            width: 100%;
            border-collapse: collapse;
            border-top: none;
            margin-bottom: 2px;
        }

        table.letterhead td {
            vertical-align: middle;
        }

        .logo-cell {
            white-space: nowrap;
        }

        .logo-cell.left {
            padding-right: 12px;
            width: 54px;
        }

        .logo-cell.right {
            padding-left: 12px;
            text-align: right;
            width: 62px;
        }

        .logo {
            height: 54px;
        }

        .school-cell {
            padding: 0;
            text-align: left;
        }

        .republic {
            font-size: 8px;
        }

        .school-name {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.02em;
        }

        .campus {
            font-size: 8px;
            font-weight: bold;
        }

        .department {
            font-size: 9px;
            font-weight: bold;
        }

        .tagline {
            color: #374151;
            font-size: 7px;
        }

        .report-title {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin-top: 10px;
            text-align: center;
        }

        .group-title {
            font-size: 10px;
            font-weight: bold;
            margin-top: 3px;
            text-align: center;
        }

        .meta {
            color: #4b5563;
            font-size: 8px;
            margin-top: 3px;
            text-align: center;
        }

        .summary {
            color: #374151;
            font-size: 8.5px;
            margin: 10px 0 6px;
        }

        .teacher-name {
            font-size: 10px;
            font-weight: bold;
        }

        .load-line {
            color: #4b5563;
            font-size: 7.5px;
            margin-top: 2px;
        }

        .exempt {
            background: #fef3c7;
            border-radius: 3px;
            color: #92400e;
            display: inline-block;
            font-size: 7px;
            margin-top: 3px;
            padding: 1px 4px;
        }

        .badge {
            border-radius: 3px;
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            padding: 1px 4px;
        }

        .badge-teacher {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-proctor {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-both {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .empty {
            color: #6b7280;
            padding: 18px 6px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $pshsLogo = public_path('images/pshs-logo.png');
        $bagongLogo = public_path('images/bagong-pilipinas.png');
        $breakdown = function (array $map, int $days): string {
            return implode(' · ', array_map(
                fn (int $day): string => 'D'.$day.' '.($map[$day] ?? 0),
                range(1, max(1, $days)),
            ));
        };
        $meta = array_filter([
            $report['school_year'] ? 'School Year: '.$report['school_year'] : null,
            $report['quarter'] ? 'Quarter: '.$report['quarter'] : null,
            $report['days'].' day(s) × '.$report['exams_per_day'].' exams/day',
            'Max proctorships/teacher/day: '.$report['max_proctors_per_teacher'],
            'Generated '.now()->format('F j, Y'),
        ]);
    @endphp

    <table class="letterhead">
        <tr>
            <td class="logo-cell left">
                @if (is_file($pshsLogo))
                    <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents($pshsLogo)) }}" alt="Philippine Science High School">
                @endif
            </td>
            <td class="school-cell">
                <div class="republic">Republic of the Philippines</div>
                <div class="school-name">PHILIPPINE SCIENCE HIGH SCHOOL</div>
                <div class="campus">CARAGA REGION CAMPUS IN BUTUAN CITY</div>
                <div class="department">DEPARTMENT OF SCIENCE AND TECHNOLOGY</div>
                <div class="tagline">OneDOST4U: Solutions and Opportunities for All</div>
            </td>
            <td class="logo-cell right">
                @if (is_file($bagongLogo))
                    <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents($bagongLogo)) }}" alt="Bagong Pilipinas">
                @endif
            </td>
        </tr>
    </table>

    <div class="report-title">TEACHER LOAD REPORT</div>
    <div class="group-title">{{ $report['title'] }}</div>

    @if ($meta !== [])
        <div class="meta">{{ implode(' · ', $meta) }}</div>
    @endif

    <div class="summary">
        Overall load: min {{ $report['summary']['min'] }},
        max {{ $report['summary']['max'] }},
        average {{ number_format($report['summary']['average'], 1) }},
        spread {{ $report['summary']['spread'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30%">Teacher</th>
                <th style="width: 25%">Day / Time</th>
                <th style="width: 28%">Subject</th>
                <th style="width: 17%">Type</th>
            </tr>
        </thead>

        @forelse ($report['teachers'] as $teacher)
            <tbody class="teacher-block">
                @foreach ($teacher['rows'] as $index => $row)
                    @php
                        $badgeClass = match ($row['type']) {
                            'Proctor' => 'badge-proctor',
                            'Subject Teacher & Proctor' => 'badge-both',
                            default => 'badge-teacher',
                        };
                    @endphp
                    <tr>
                        @if ($index === 0)
                            <td rowspan="{{ count($teacher['rows']) }}">
                                <div class="teacher-name">{{ $teacher['name'] }}</div>
                                <div class="load-line">T {{ $teacher['teaching'] }} ({{ $breakdown($teacher['teaching_by_day'], $report['days']) }})</div>
                                <div class="load-line">P {{ $teacher['proctor'] }} ({{ $breakdown($teacher['proctor_by_day'], $report['days']) }})</div>
                                <div class="load-line">O {{ $teacher['overall'] }} ({{ $breakdown($teacher['overall_by_day'], $report['days']) }})</div>

                                @if ($teacher['exempt_days'] !== [])
                                    <div class="exempt">Exempt on day(s) {{ implode(', ', $teacher['exempt_days']) }}</div>
                                @endif
                            </td>
                        @endif

                        <td class="nowrap">{{ $row['time'] }}</td>
                        <td>{{ $row['subject'] !== '' ? $row['subject'] : '—' }}</td>
                        <td class="nowrap">
                            <span class="badge {{ $badgeClass }}">{{ $row['type'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody>
                <tr>
                    <td colspan="4" class="empty">
                        No schedules generated yet. Generate the grid or add schedules to see teacher load details.
                    </td>
                </tr>
            </tbody>
        @endforelse
    </table>
</body>
</html>
