<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    groups: { type: Array, required: true },
});

const selectedGroup = ref('');
const selectedSection = ref('');
const activeDays = ref({});

const dayFor = (group) => activeDays.value[group.id] ?? 1;

const setDay = (group, day) => {
    activeDays.value = { ...activeDays.value, [group.id]: day };
};

const sections = computed(() => {
    const map = new Map();

    props.groups.forEach((group) => {
        (group.sections ?? []).forEach((section) => map.set(section.id, section));
    });

    return [...map.values()].sort((a, b) => a.section_name.localeCompare(b.section_name));
});

const visibleGroups = computed(() =>
    props.groups.filter((group) => !selectedGroup.value || group.id === Number(selectedGroup.value)),
);

const displayTime = (time) => {
    if (!time) {
        return '';
    }

    const [hours, minutes] = String(time).split(':');
    const hour = Number(hours);
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 === 0 ? 12 : hour % 12;

    return `${displayHour}:${minutes} ${period}`;
};
const teacherName = (teacher) => (teacher ? [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ') : '—');

const sectionsByGrade = (group) => {
    const map = new Map();
    const sections = [...(group.sections ?? [])]
        .filter((section) => !selectedSection.value || section.id === Number(selectedSection.value))
        .sort((a, b) => a.id - b.id);

    for (const section of sections) {
        const gradeId = Number(section.grade_level_id);
        if (!map.has(gradeId)) {
            map.set(gradeId, []);
        }
        map.get(gradeId).push(section);
    }

    return map;
};

const subjectsByGrade = (group) => {
    const map = new Map();
    const subjects = [...(group.subjects ?? [])].sort(
        (a, b) => Number(a.pivot?.sort_order ?? 0) - Number(b.pivot?.sort_order ?? 0) || a.id - b.id,
    );

    for (const subject of subjects) {
        const gradeId = Number(subject.grade_level_id);
        if (!map.has(gradeId)) {
            map.set(gradeId, []);
        }
        map.get(gradeId).push(subject);
    }

    return map;
};

const timeSlotsForGrade = (group, gradeId) => {
    const slots = group.time_preset?.slots ?? [];
    const specific = slots
        .filter((slot) => slot.grade_level_id !== null && Number(slot.grade_level_id) === Number(gradeId))
        .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));

    if (specific.length) {
        return specific;
    }

    return slots
        .filter((slot) => slot.grade_level_id === null)
        .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
};

const buildBlocks = (group, day) => {
    const examsPerDay = Math.max(1, Number(group.exams_per_day) || 3);
    const daySchedules = new Map();

    for (const schedule of group.schedules ?? []) {
        if (Number(schedule.day ?? 1) !== Number(day)) {
            continue;
        }
        daySchedules.set(`${schedule.section_id}-${schedule.subject_id}`, schedule);
    }

    const grades = sectionsByGrade(group);
    const subjectsByGradeMap = subjectsByGrade(group);
    const blocks = [];
    const gradeIds = [...grades.keys()].sort((a, b) => a - b);
    const enrolled = new Set((group.enrollments ?? []).map((pair) => `${pair.section_id}-${pair.subject_id}`));

    for (const gradeId of gradeIds) {
        const sections = grades.get(gradeId) ?? [];
        if (sections.length === 0) {
            continue;
        }

        const subjects = subjectsByGradeMap.get(gradeId) ?? [];
        const slots = timeSlotsForGrade(group, gradeId);
        const subjectGroups = [];

        for (let period = 0; period < examsPerDay; period++) {
            const subject = subjects[(Number(day) - 1) * examsPerDay + period];
            if (!subject) {
                continue;
            }

            const slot = slots[period];
            const timeLabel = slot ? `${displayTime(slot.start_time)}–${displayTime(slot.end_time)}` : '';
            const key = `${gradeId}-${period}`;

            const rows = sections
                .filter(
                    (section) =>
                        enrolled.has(`${section.id}-${subject.id}`) ||
                        daySchedules.has(`${section.id}-${subject.id}`),
                )
                .map((section) => ({
                    section,
                    schedule: daySchedules.get(`${section.id}-${subject.id}`) ?? null,
                }));

            if (subject.is_elective && rows.length === 0) {
                subjectGroups.push({ key, subject, timeLabel, elective: true, placeholder: true, rows: [] });
                continue;
            }

            subjectGroups.push({ key, subject, timeLabel, elective: subject.is_elective, placeholder: false, rows });
        }

        blocks.push({ gradeId, grade: sections[0].grade_level ?? null, groups: subjectGroups });
    }

    return blocks;
};

const uniformTeacher = (group) => {
    const rows = group.rows.filter((row) => row.schedule);
    if (rows.length === 0) {
        return null;
    }

    const first = rows[0].schedule;
    const uniform = rows.every(
        (row) => Number(row.schedule.subject_teacher_id ?? 0) === Number(first.subject_teacher_id ?? 0),
    );

    return uniform ? first.subject_teacher : null;
};
</script>

<template>
    <Head title="Exam Schedules" />

    <div class="min-h-screen bg-gray-100">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Exam Schedules</h1>
                    <p class="text-sm text-gray-500">Currently active examination days.</p>
                </div>
                <Link
                    :href="route('login')"
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Log in
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap gap-4">
                <div>
                    <label for="filter_group" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Exam</label>
                    <select
                        id="filter_group"
                        v-model="selectedGroup"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All exams</option>
                        <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.title }}</option>
                    </select>
                </div>
                <div>
                    <label for="filter_section" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Section</label>
                    <select
                        id="filter_section"
                        v-model="selectedSection"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All sections</option>
                        <option v-for="section in sections" :key="section.id" :value="section.id">{{ section.section_name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="visibleGroups.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                No active schedules are available right now.
            </div>

            <div v-for="group in visibleGroups" :key="group.id" class="mb-6 overflow-hidden rounded-lg bg-white shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ group.title }}</h2>
                        <p v-if="group.description" class="mt-1 text-sm text-gray-500">{{ group.description }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            :href="route('public.schedules.export.pdf', group.id)"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            PDF
                        </a>
                        <a
                            :href="route('public.schedules.export.excel', group.id)"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Excel
                        </a>
                        <button
                            v-for="day in (Number(group.days) || 1)"
                            :key="day"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-sm font-medium"
                            :class="dayFor(group) === day ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            @click="setDay(group, day)"
                        >
                            Day {{ day }}
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Section</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Proctor</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Room</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template v-for="block in buildBlocks(group, dayFor(group))" :key="`grade-${block.gradeId}`">
                                <tr class="bg-gray-100">
                                    <td colspan="6" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Grade {{ block.grade?.grade_level ?? block.gradeId }}
                                    </td>
                                </tr>
                                <template v-for="subjectGroup in block.groups" :key="subjectGroup.key">
                                    <tr v-if="subjectGroup.elective && subjectGroup.placeholder">
                                        <td class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-top text-sm font-medium text-gray-900">
                                            {{ subjectGroup.timeLabel || '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ subjectGroup.subject.subject_name }}
                                            <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Elective</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">NA</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">Subject Teachers</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">Subject Teachers</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">Classroom Assignment</td>
                                    </tr>
                                    <template v-else>
                                        <tr v-for="(row, rowIndex) in subjectGroup.rows" :key="`${subjectGroup.key}-${row.section.id}`">
                                            <td
                                                v-if="rowIndex === 0"
                                                :rowspan="subjectGroup.rows.length"
                                                class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-middle text-sm font-medium text-gray-900"
                                            >
                                                {{ subjectGroup.timeLabel || '—' }}
                                            </td>
                                            <td
                                                v-if="rowIndex === 0"
                                                :rowspan="subjectGroup.rows.length"
                                                class="border-r border-gray-100 px-4 py-3 align-middle text-sm text-gray-700"
                                            >
                                                {{ subjectGroup.subject.subject_name }}
                                                <span v-if="subjectGroup.elective" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Elective</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.section.section_name }}</td>
                                            <td
                                                v-if="rowIndex === 0 && uniformTeacher(subjectGroup)"
                                                :rowspan="subjectGroup.rows.length"
                                                class="border-r border-gray-100 px-4 py-3 align-middle text-sm text-gray-700"
                                            >
                                                {{ teacherName(uniformTeacher(subjectGroup)) }}
                                            </td>
                                            <td v-else-if="!uniformTeacher(subjectGroup)" class="px-4 py-3 text-sm text-gray-700">
                                                {{ teacherName(row.schedule?.subject_teacher) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ teacherName(row.schedule?.proctor) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ row.section.room?.room_name ?? '—' }}</td>
                                        </tr>
                                    </template>
                                </template>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>
