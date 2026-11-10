<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $grid['title'] }}</title>
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

        h1 {
            font-size: 17px;
            margin: 0 0 3px;
        }

        .subtitle {
            color: #4b5563;
            font-size: 9px;
            margin: 0 0 6px;
        }

        h2 {
            font-size: 13px;
            margin: 12px 0 6px;
            padding: 4px 6px;
            background: #f3f4f6;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #e5e7eb;
            margin-bottom: 4px;
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

        tr.grade-row td {
            background: #f3f4f6;
            color: #4b5563;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 0.05em;
            padding: 4px 6px;
            text-transform: uppercase;
        }

        td.divider {
            border-right: 1px solid #f3f4f6;
        }

        td.span-cell {
            vertical-align: middle;
        }

        .time {
            font-weight: bold;
            white-space: nowrap;
        }

        .badge {
            border-radius: 3px;
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            margin-left: 3px;
            padding: 1px 4px;
            text-transform: none;
            letter-spacing: 0;
        }

        .badge-unplaced {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge-elective {
            background: #fef3c7;
            color: #92400e;
        }

        .muted {
            color: #6b7280;
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

        .exam-title {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin-top: 10px;
            text-align: center;
        }

        .school-year {
            font-size: 10px;
            text-align: center;
        }

        .assessment-date {
            font-size: 11px;
            font-weight: bold;
            margin-top: 8px;
        }

        tr {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @php
        $pshsLogo = public_path('images/pshs-logo.png');
        $bagongLogo = public_path('images/bagong-pilipinas.png');
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

    <div class="exam-title">{{ $grid['exam_title'] }}</div>

    @if ($grid['school_year'])
        <div class="school-year">School Year {{ $grid['school_year'] }}</div>
    @endif

    @if ($grid['date_of_assessment'])
        <div class="assessment-date">DATE OF ASSESSMENT: {{ $grid['date_of_assessment'] }}</div>
    @endif

    @forelse ($grid['days'] as $day)
        @if (count($grid['days']) > 1)
            <h2>Day {{ $day['day'] }}</h2>
        @endif

        @if (count($day['grades']) === 0)
            <p class="muted">No subjects are scheduled for this day.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 14%">Time of Assessment</th>
                        <th style="width: 20%">Subject/<br>Assessment</th>
                        <th style="width: 14%">Section</th>
                        <th style="width: 19%">Subject Teacher</th>
                        <th style="width: 19%">Proctor Assigned</th>
                        <th style="width: 14%">Room<br>Assignment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($day['grades'] as $grade)
                        <tr class="grade-row">
                            <td colspan="6">Grade {{ $grade['grade'] }}</td>
                        </tr>

                        @foreach ($grade['subjects'] as $group)
                            @if ($group['elective'] && ($group['placeholder'] ?? false))
                                <tr>
                                    <td class="divider time">{{ $group['time'] ?: '—' }}</td>
                                    <td>
                                        {{ $group['subject'] }}
                                        <span class="badge badge-elective">Elective</span>
                                    </td>
                                    <td>NA</td>
                                    <td>Subject Teachers</td>
                                    <td>Subject Teachers</td>
                                    <td>Classroom Assignment</td>
                                </tr>
                            @else
                                @foreach ($group['rows'] as $index => $row)
                                    <tr>
                                        @if ($index === 0)
                                            <td class="divider span-cell time" rowspan="{{ count($group['rows']) }}">{{ $group['time'] ?: '—' }}</td>
                                            <td class="divider span-cell" rowspan="{{ count($group['rows']) }}">
                                                {{ $group['subject'] }}
                                                @if ($group['elective'])
                                                    <span class="badge badge-elective">Elective</span>
                                                @endif
                                            </td>
                                        @endif

                                        <td>
                                            {{ $row['section'] }}
                                            @if ($row['unplaced'])
                                                <span class="badge badge-unplaced">Unplaced</span>
                                            @endif
                                        </td>

                                        @if ($index === 0 && $group['uniform_teacher'])
                                            <td class="divider span-cell" rowspan="{{ count($group['rows']) }}">{{ $group['uniform_teacher'] }}</td>
                                        @elseif (! $group['uniform_teacher'])
                                            <td>{{ $row['subject_teacher'] ?: '—' }}</td>
                                        @endif

                                        <td>{{ $row['proctor'] ?: '—' }}</td>
                                        <td>{{ $row['room'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <p class="muted">No days are configured for this schedule.</p>
    @endforelse
</body>
</html>
