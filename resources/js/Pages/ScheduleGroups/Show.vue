<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import Modal from '@/Components/Modal.vue';
import TeacherDetailsModal from '@/Components/TeacherDetailsModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    group: { type: Object, required: true },
    subjectPlan: { type: Object, default: () => ({}) },
    allSections: { type: Array, required: true },
    allSubjects: { type: Array, required: true },
    allPresets: { type: Array, required: true },
    teachers: { type: Array, required: true },
    teachingLoads: { type: Object, default: () => ({}) },
    proctorLoads: { type: Object, default: () => ({}) },
    overallLoads: { type: Object, default: () => ({}) },
    teachingLoadsByDay: { type: Object, default: () => ({}) },
    proctorLoadsByDay: { type: Object, default: () => ({}) },
    overallLoadsByDay: { type: Object, default: () => ({}) },
    subjectTeacherLoads: { type: Object, default: () => ({}) },
    subjectTeacherLoadsByDay: { type: Object, default: () => ({}) },
    assignments: { type: Array, default: () => [] },
    enrollments: { type: Array, default: () => [] },
});

const page = usePage();
const report = computed(() => page.props.flash?.generationReport ?? null);

const isDraft = computed(() => props.group.status === 'draft');
const isActive = computed(() => props.group.status === 'active');

const subjectTeacherLoad = (teacher) => Number(props.subjectTeacherLoads?.[teacher?.id] ?? 0);
const subjectTeacherLoadOnDay = (teacher, day) => Number(props.subjectTeacherLoadsByDay?.[teacher?.id]?.[day] ?? 0);
const exemptDays = (teacher) => dayTabs.value.filter((day) => subjectTeacherLoadOnDay(teacher, day) >= 2);
const isSubjectTeacherExempt = (teacher) => exemptDays(teacher).length > 0;

const teachingLoad = (teacher) => Number(props.teachingLoads?.[teacher?.id] ?? 0);
const proctorLoad = (teacher) => Number(props.proctorLoads?.[teacher?.id] ?? 0);
const overallLoad = (teacher) => Number(props.overallLoads?.[teacher?.id] ?? 0);

const loadByDay = (map, teacher, day) => Number(map?.[teacher?.id]?.[day] ?? 0);

const proctorAtCap = (teacher) =>
    dayTabs.value.some((day) => loadByDay(props.proctorLoadsByDay, teacher, day) >= Number(props.group.max_proctors_per_teacher));

const dayBreakdown = (map, teacher) =>
    dayTabs.value.map((day) => `D${day} ${loadByDay(map, teacher, day)}`).join(' · ');

const overallSpread = computed(() => {
    const loads = props.teachers.map((teacher) => overallLoad(teacher));

    if (loads.length === 0) {
        return { min: 0, max: 0, average: 0, spread: 0 };
    }

    const min = Math.min(...loads);
    const max = Math.max(...loads);

    return {
        min,
        max,
        average: loads.reduce((sum, value) => sum + value, 0) / loads.length,
        spread: max - min,
    };
});

const setupForm = useForm({
    time_preset_id: props.group.time_preset_id ?? '',
    days: props.group.days ?? 3,
    exams_per_day: props.group.exams_per_day ?? 3,
    sections: [],
    subjects: [],
    day_presets: (props.group.day_presets ?? []).reduce((map, preset) => {
        map[Number(preset.day)] = preset.time_preset_id ?? '';

        return map;
    }, {}),
});

onMounted(() => {
    setupForm.sections = (props.group.sections ?? []).map((section) => section.id);
    setupForm.subjects = (props.group.subjects ?? [])
        .slice()
        .sort((a, b) => Number(a.pivot?.sort_order ?? 0) - Number(b.pivot?.sort_order ?? 0) || a.id - b.id)
        .map((subject) => subject.id);
    selectedProctors.value = props.teachers.map((teacher) => teacher.id);
});

const saveSetup = () => {
    setupForm.put(route('schedule-groups.setup', props.group.id), { preserveScroll: true });
};

const toggle = (list, id) => {
    const index = list.indexOf(id);
    if (index === -1) {
        list.push(id);
    } else {
        list.splice(index, 1);
    }
};

const subjectGradeMap = computed(() => new Map(props.allSubjects.map((subject) => [subject.id, Number(subject.grade_level_id)])));

const setupSubjectGrades = computed(() => {
    const map = new Map();
    const subjects = [...props.allSubjects].sort(
        (a, b) =>
            String(a.grade_level?.grade_level ?? '').localeCompare(String(b.grade_level?.grade_level ?? ''), undefined, { numeric: true }) ||
            a.subject_name.localeCompare(b.subject_name),
    );

    for (const subject of subjects) {
        const gradeId = Number(subject.grade_level_id);
        if (!map.has(gradeId)) {
            map.set(gradeId, { gradeId, label: subject.grade_level?.grade_level ?? '—', subjects: [] });
        }
        map.get(gradeId).subjects.push(subject);
    }

    return [...map.values()];
});

const setupOrderIndex = (subject) => {
    const gradeId = Number(subject.grade_level_id);
    const inGrade = setupForm.subjects.filter((id) => subjectGradeMap.value.get(id) === gradeId);
    const index = inGrade.indexOf(subject.id);

    return index === -1 ? null : index + 1;
};

const moveSubject = (id, direction) => {
    const list = setupForm.subjects;
    const index = list.indexOf(id);
    if (index === -1) {
        return;
    }

    const gradeId = subjectGradeMap.value.get(id);
    let swapIndex = -1;

    if (direction < 0) {
        for (let i = index - 1; i >= 0; i--) {
            if (subjectGradeMap.value.get(list[i]) === gradeId) {
                swapIndex = i;
                break;
            }
        }
    } else {
        for (let i = index + 1; i < list.length; i++) {
            if (subjectGradeMap.value.get(list[i]) === gradeId) {
                swapIndex = i;
                break;
            }
        }
    }

    if (swapIndex === -1) {
        return;
    }

    const swapped = list[index];
    list[index] = list[swapIndex];
    list[swapIndex] = swapped;
};

const action = (name, label, destructive = false) => {
    if (!destructive || confirm(`${label}?`)) {
        router.post(route(`schedule-groups.${name}`, props.group.id), {}, { preserveScroll: true });
    }
};

const selectedProctors = ref([]);

const eligibleProctors = computed(() => props.teachers);

const allProctorsSelected = computed(
    () =>
        eligibleProctors.value.length > 0 &&
        eligibleProctors.value.every((teacher) => selectedProctors.value.includes(teacher.id)),
);
const someProctorsSelected = computed(
    () => selectedProctors.value.length > 0 && !allProctorsSelected.value,
);

const toggleProctor = (id) => {
    const index = selectedProctors.value.indexOf(id);
    if (index === -1) {
        selectedProctors.value.push(id);
    } else {
        selectedProctors.value.splice(index, 1);
    }
};

const toggleAllProctors = () => {
    selectedProctors.value = allProctorsSelected.value ? [] : eligibleProctors.value.map((teacher) => teacher.id);
};

const generate = () => {
    if (selectedProctors.value.length === 0) {
        return;
    }

    if (confirm('Regenerate the grid (this clears existing draft schedules)?')) {
        router.post(
            route('schedule-groups.generate', props.group.id),
            { proctors: [...selectedProctors.value] },
            { preserveScroll: true },
        );
    }
};

const shortTime = (time) => (time ? String(time).slice(0, 5) : '');
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

const detailsOpen = ref(false);
const detailsTeacherId = ref(null);

const openTeacher = (id) => {
    if (!id) {
        return;
    }

    detailsTeacherId.value = id;
    detailsOpen.value = true;
};

const totalDays = computed(() => Math.max(1, Number(props.group.days) || 1));
const activeDay = ref(1);

watch(totalDays, (value) => {
    if (activeDay.value > value) {
        activeDay.value = 1;
    }
});

const dayTabs = computed(() => Array.from({ length: totalDays.value }, (_, index) => index + 1));

const hasSchedules = computed(() => (props.group.schedules ?? []).length > 0);

const scheduleByKey = computed(() => {
    const map = new Map();

    for (const schedule of props.group.schedules ?? []) {
        map.set(`${Number(schedule.day ?? 1)}-${schedule.section_id}-${schedule.subject_id}`, schedule);
    }

    return map;
});

const groupUnplaced = computed(() => props.group.unplaced ?? []);

const unplacedByKey = computed(() => {
    const map = new Map();

    for (const item of groupUnplaced.value) {
        map.set(`${Number(item.day ?? 1)}-${item.section_id}-${item.subject_id}`, item);
    }

    return map;
});

const enrollmentKeys = computed(
    () => new Set(props.enrollments.map((pair) => `${pair.section_id}-${pair.subject_id}`)),
);

const sectionsByGrade = computed(() => {
    const map = new Map();
    const sections = [...(props.group.sections ?? [])].sort((a, b) => a.id - b.id);

    for (const section of sections) {
        const gradeId = Number(section.grade_level_id);
        if (!map.has(gradeId)) {
            map.set(gradeId, []);
        }
        map.get(gradeId).push(section);
    }

    return map;
});

const subjectsByGrade = computed(() => {
    const map = new Map();
    const subjects = [...(props.group.subjects ?? [])].sort(
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
});

const presetForDay = (day) => {
    const override = (props.group.day_presets ?? []).find((entry) => Number(entry.day) === Number(day));

    return override?.time_preset ?? props.group.time_preset ?? null;
};

const dayPresetId = (day) => presetForDay(day)?.id ?? null;

const timeSlotsForGrade = (day, gradeId) => {
    const slots = presetForDay(day)?.slots ?? [];
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

const dayBlocks = computed(() => {
    const day = Number(activeDay.value);
    const blocks = [];
    const gradeIds = [...sectionsByGrade.value.keys()].sort((a, b) => a - b);

    for (const gradeId of gradeIds) {
        const sections = sectionsByGrade.value.get(gradeId) ?? [];
        if (sections.length === 0) {
            continue;
        }

        const subjectsById = new Map(
            (subjectsByGrade.value.get(gradeId) ?? []).map((subject) => [Number(subject.id), subject]),
        );
        const entries = props.subjectPlan?.[day]?.[gradeId] ?? [];
        const slots = timeSlotsForGrade(day, gradeId);
        const groups = [];

        for (const entry of entries) {
            const subject = subjectsById.get(Number(entry.subject_id));
            if (!subject) {
                continue;
            }

            const period = entry.period;
            const slot = period === null || period === undefined ? null : slots[period];
            const timeLabel = slot ? `${displayTime(slot.start_time)}–${displayTime(slot.end_time)}` : '';
            const key = `${gradeId}-${subject.id}`;

            const rows = sections
                .filter(
                    (section) =>
                        enrollmentKeys.value.has(`${section.id}-${subject.id}`) ||
                        scheduleByKey.value.has(`${day}-${section.id}-${subject.id}`),
                )
                .map((section) => ({
                    section,
                    schedule: scheduleByKey.value.get(`${day}-${section.id}-${subject.id}`) ?? null,
                    unplaced: unplacedByKey.value.get(`${day}-${section.id}-${subject.id}`) ?? null,
                }));

            if (subject.is_elective && rows.length === 0) {
                groups.push({ key, subject, timeLabel, elective: true, placeholder: true, rows: [] });
                continue;
            }

            groups.push({ key, subject, timeLabel, elective: subject.is_elective, placeholder: false, rows });
        }

        blocks.push({ gradeId, grade: sections[0].grade_level ?? null, groups });
    }

    return blocks;
});

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

const scheduleForm = useForm({
    day: 1,
    section_id: '',
    subject_id: '',
    proctor_id: '',
    start_time: '07:00',
    end_time: '08:00',
});

const assignmentLookup = computed(() => {
    const map = new Map();

    for (const assignment of props.assignments) {
        map.set(`${assignment.section_id}-${assignment.subject_id}`, assignment.teacher);
    }

    return map;
});

const assignedTeacher = computed(() => {
    if (!scheduleForm.section_id || !scheduleForm.subject_id) {
        return null;
    }

    return assignmentLookup.value.get(`${scheduleForm.section_id}-${scheduleForm.subject_id}`) ?? null;
});

const showScheduleModal = ref(false);
const editingSchedule = ref(null);

const availableSubjects = computed(() => {
    const section = props.group.sections.find((item) => item.id === Number(scheduleForm.section_id));
    if (!section) {
        return [];
    }

    return props.group.subjects.filter((subject) => subject.grade_level_id === section.grade_level_id);
});

const openAddSchedule = () => {
    editingSchedule.value = null;
    scheduleForm.reset();
    scheduleForm.clearErrors();
    scheduleForm.day = activeDay.value;
    scheduleForm.start_time = '07:00';
    scheduleForm.end_time = '08:00';
    showScheduleModal.value = true;
};

const openEditSchedule = (schedule) => {
    editingSchedule.value = schedule;
    scheduleForm.day = Number(schedule.day ?? 1);
    scheduleForm.section_id = schedule.section_id;
    scheduleForm.subject_id = schedule.subject_id;
    scheduleForm.proctor_id = schedule.proctor_id;
    scheduleForm.start_time = shortTime(schedule.start_time);
    scheduleForm.end_time = shortTime(schedule.end_time);
    scheduleForm.clearErrors();
    showScheduleModal.value = true;
};

const submitSchedule = () => {
    if (editingSchedule.value) {
        scheduleForm.put(route('schedule-groups.schedules.update', [props.group.id, editingSchedule.value.id]), {
            preserveScroll: true,
            onSuccess: () => (showScheduleModal.value = false),
        });
    } else {
        scheduleForm.post(route('schedule-groups.schedules.store', props.group.id), {
            preserveScroll: true,
            onSuccess: () => (showScheduleModal.value = false),
        });
    }
};

const removeSchedule = (schedule) => {
    if (confirm('Remove this schedule?')) {
        router.delete(route('schedule-groups.schedules.destroy', [props.group.id, schedule.id]), { preserveScroll: true });
    }
};

const openAddScheduleFor = (item) => {
    editingSchedule.value = null;
    scheduleForm.reset();
    scheduleForm.clearErrors();
    scheduleForm.day = Number(item.day ?? 1);
    scheduleForm.section_id = item.section_id;
    scheduleForm.subject_id = item.subject_id;
    scheduleForm.start_time = shortTime(item.start_time) || '07:00';
    scheduleForm.end_time = shortTime(item.end_time) || '08:00';
    showScheduleModal.value = true;
};

const assignModalOpen = ref(false);
const assignUnplaced = ref(null);
const assignmentValues = ref({});

const assignSubjects = computed(() => {
    if (!assignUnplaced.value) {
        return [];
    }

    const gradeId = Number(assignUnplaced.value.section?.grade_level_id);

    return (props.group.subjects ?? []).filter((subject) => Number(subject.grade_level_id) === gradeId);
});

const openAssignFor = (item) => {
    assignUnplaced.value = item;

    const values = {};

    for (const assignment of props.assignments) {
        if (assignment.section_id === item.section_id) {
            values[assignment.subject_id] = assignment.teacher?.id ?? '';
        }
    }

    assignmentValues.value = values;
    assignModalOpen.value = true;
};

const saveAssignments = () => {
    const payload = assignSubjects.value.map((subject) => ({
        subject_id: subject.id,
        teacher_id: assignmentValues.value[subject.id] ?? '',
    }));

    router.put(
        route('sections.assignments.update', assignUnplaced.value.section_id),
        { assignments: payload },
        { preserveScroll: true, onSuccess: () => (assignModalOpen.value = false) },
    );
};
</script>

<template>
    <Head :title="group.title" />

    <AdminLayout :title="group.title">
        <FlashBanner />

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                :class="{
                    'bg-amber-100 text-amber-800': group.status === 'draft',
                    'bg-green-100 text-green-800': group.status === 'active',
                    'bg-gray-200 text-gray-700': group.status === 'archived',
                }"
            >
                {{ group.status }}
            </span>
            <span v-if="group.school_year" class="text-sm text-gray-600">
                School Year: {{ group.school_year.name }}
            </span>
            <span v-if="group.quarter" class="text-sm text-gray-600">
                Quarter: {{ group.quarter.name }}
            </span>
            <span v-if="group.time_preset" class="text-sm text-gray-600">
                Default preset: {{ group.time_preset.name }} ({{ group.time_preset.slots?.length ?? 0 }} slots)
            </span>
            <span
                v-for="dayPreset in (group.day_presets ?? [])"
                :key="`header-day-preset-${dayPreset.day}`"
                class="text-sm text-gray-600"
            >
                Day {{ dayPreset.day }}: {{ dayPreset.time_preset?.name ?? 'default' }}
            </span>
            <span class="text-sm text-gray-600">{{ group.days }} day(s) × {{ group.exams_per_day }} exams/day</span>
            <span class="text-sm text-gray-600">Max proctorships/teacher/day: {{ group.max_proctors_per_teacher }}</span>

            <div class="ms-auto flex flex-wrap gap-2">
                <a
                    :href="route('schedule-groups.export.pdf', group.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50"
                >
                    Export PDF
                </a>
                <a
                    :href="route('schedule-groups.export.excel', group.id)"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50"
                >
                    Export Excel
                </a>
                <Link :href="route('schedule-groups.edit', group.id)">
                    <SecondaryButton type="button">Edit Group</SecondaryButton>
                </Link>
                <PrimaryButton v-if="isDraft" type="button" :disabled="selectedProctors.length === 0" @click="generate">
                    Generate
                </PrimaryButton>
                <PrimaryButton v-if="isDraft" type="button" @click="action('finalize', 'Finalize and publish this schedule group')">
                    Finalize
                </PrimaryButton>
                <SecondaryButton v-if="!isDraft" type="button" @click="action('reopen', 'Reopen as draft')">Reopen</SecondaryButton>
                <SecondaryButton v-if="isActive" type="button" @click="action('archive', 'Archive this schedule group')">Archive</SecondaryButton>
            </div>
        </div>

        <!-- Generation report -->
        <div v-if="report" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow">
            <h3 class="text-sm font-semibold text-gray-800">Generation report</h3>
            <p class="mt-1 text-sm text-gray-600">
                Placed {{ report.placed.length }} schedule(s); {{ report.unplaced.length }} could not be placed.
            </p>
            <ul v-if="report.warnings?.length" class="mt-2 list-disc space-y-1 ps-5 text-sm text-amber-700">
                <li v-for="(warning, index) in report.warnings" :key="index">{{ warning }}</li>
            </ul>
        </div>

        <!-- Needs attention (persisted unplaced exams) -->
        <div v-if="groupUnplaced.length" class="mb-4 rounded-lg border border-red-200 bg-white p-4 shadow">
            <h3 class="text-sm font-semibold text-red-700">Needs attention ({{ groupUnplaced.length }})</h3>
            <p class="mt-1 text-sm text-gray-600">
                These exams could not be placed. Fix the cause, then regenerate or add them manually.
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Day</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade / Section</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reason</th>
                            <th v-if="isDraft" class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="item in groupUnplaced" :key="item.id">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">Day {{ item.day }}</td>
                            <td class="px-3 py-2 text-gray-700">
                                {{ item.section?.grade_level?.grade_level ?? '—' }} / {{ item.section?.section_name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-700">{{ item.subject?.subject_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-red-700">{{ item.reason }}</td>
                            <td v-if="isDraft" class="whitespace-nowrap px-3 py-2 text-right">
                                <button
                                    v-if="item.reason_code === 'missing_teacher'"
                                    class="text-indigo-600 hover:text-indigo-900"
                                    @click="openAssignFor(item)"
                                >
                                    Assign teacher
                                </button>
                                <button
                                    class="text-indigo-600 hover:text-indigo-900"
                                    :class="{ 'ms-3': item.reason_code === 'missing_teacher' }"
                                    @click="openAddScheduleFor(item)"
                                >
                                    Add schedule
                                </button>
                                <Link
                                    v-if="item.reason_code === 'missing_slot' && dayPresetId(item.day)"
                                    :href="route('time-presets.edit', dayPresetId(item.day))"
                                    class="ms-3 text-indigo-600 hover:text-indigo-900"
                                >
                                    Fix time preset
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Setup -->
            <div class="rounded-lg bg-white p-6 shadow xl:col-span-1">
                <h3 class="text-base font-semibold text-gray-800">Setup</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Choose the preset, the exam length, the sections, and the ordered subjects per grade.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <InputLabel for="setup_preset" value="Default Time Preset" />
                        <select
                            id="setup_preset"
                            v-model="setupForm.time_preset_id"
                            :disabled="!isDraft"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                        >
                            <option value="">No preset</option>
                            <option v-for="preset in allPresets" :key="preset.id" :value="preset.id">
                                {{ preset.name }} ({{ preset.slots_count }} slots)
                            </option>
                        </select>
                        <InputError class="mt-2" :message="setupForm.errors.time_preset_id" />
                    </div>
                    <div>
                        <InputLabel for="setup_days" value="Days" />
                        <TextInput
                            id="setup_days"
                            v-model="setupForm.days"
                            type="number"
                            min="1"
                            max="3"
                            :disabled="!isDraft"
                            class="mt-1 block w-full disabled:bg-gray-100"
                        />
                        <InputError class="mt-2" :message="setupForm.errors.days" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="setup_epd" value="Exams per day" />
                        <TextInput
                            id="setup_epd"
                            v-model="setupForm.exams_per_day"
                            type="number"
                            min="1"
                            max="10"
                            :disabled="!isDraft"
                            class="mt-1 block w-full disabled:bg-gray-100"
                        />
                        <InputError class="mt-2" :message="setupForm.errors.exams_per_day" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Per-day presets" />
                    <p class="mt-1 text-xs text-gray-500">
                        Leave a day on "Use default" to use the default preset above.
                    </p>
                    <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div v-for="day in (Number(setupForm.days) || 1)" :key="`setup-day-preset-${day}`">
                            <InputLabel :for="`setup_day_preset_${day}`" :value="`Day ${day} preset`" />
                            <select
                                :id="`setup_day_preset_${day}`"
                                v-model="setupForm.day_presets[day]"
                                :disabled="!isDraft"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                            >
                                <option value="">Use default</option>
                                <option v-for="preset in allPresets" :key="preset.id" :value="preset.id">
                                    {{ preset.name }} ({{ preset.slots_count }} slots)
                                </option>
                            </select>
                            <InputError class="mt-2" :message="setupForm.errors[`day_presets.${day}`]" />
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Sections" />
                    <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-3">
                        <label v-for="section in allSections" :key="section.id" class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                :disabled="!isDraft"
                                :checked="setupForm.sections.includes(section.id)"
                                @change="toggle(setupForm.sections, section.id)"
                            />
                            {{ section.section_name }}
                            <span class="text-xs text-gray-400">{{ section.grade_level?.grade_level }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Subjects (ordered per grade)" />
                    <p class="mt-1 text-xs text-gray-500">
                        The order defines which subjects are sat first. Each day fills its available slots in order,
                        up to {{ group.exams_per_day }} exams per day; a short day carries the remaining subjects into
                        the next day.
                    </p>
                    <div class="mt-2 max-h-72 space-y-3 overflow-y-auto rounded-md border border-gray-200 p-3">
                        <div v-for="gradeGroup in setupSubjectGrades" :key="gradeGroup.gradeId">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                Grade {{ gradeGroup.label }}
                            </p>
                            <div class="mt-1 space-y-1">
                                <div
                                    v-for="subject in gradeGroup.subjects"
                                    :key="subject.id"
                                    class="flex items-center justify-between gap-2 rounded px-1 py-0.5 text-sm text-gray-700"
                                >
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            :disabled="!isDraft"
                                            :checked="setupForm.subjects.includes(subject.id)"
                                            @change="toggle(setupForm.subjects, subject.id)"
                                        />
                                        {{ subject.subject_name }}
                                        <span v-if="subject.is_elective" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Elective</span>
                                    </label>
                                    <span v-if="setupOrderIndex(subject)" class="flex items-center gap-1 text-xs text-gray-400">
                                        #{{ setupOrderIndex(subject) }}
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-1 leading-none hover:bg-gray-50 disabled:opacity-30"
                                            :disabled="!isDraft"
                                            @click="moveSubject(subject.id, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-1 leading-none hover:bg-gray-50 disabled:opacity-30"
                                            :disabled="!isDraft"
                                            @click="moveSubject(subject.id, 1)"
                                        >
                                            ↓
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isDraft" class="mt-4">
                    <PrimaryButton :disabled="setupForm.processing" @click="saveSetup">Save Setup</PrimaryButton>
                </div>
            </div>

            <!-- Teacher load -->
            <div class="rounded-lg bg-white p-6 shadow xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Teacher load</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Teaching slots, proctor count against the per-day cap of {{ group.max_proctors_per_teacher }}, and overall load (teaching + proctor). Teachers with two or more subjects on a given day are exempt from proctoring that day; the rule resets the next day. Unchecked teachers never proctor a generated grid.
                        </p>
                        <p class="mt-2 text-xs text-gray-500">
                            Overall load across the teacher list: min {{ overallSpread.min }}, max {{ overallSpread.max }}, average {{ overallSpread.average.toFixed(1) }}, spread {{ overallSpread.spread }}.
                        </p>
                    </div>
                    <label class="flex shrink-0 items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            :checked="allProctorsSelected"
                            :indeterminate="someProctorsSelected"
                            @change="toggleAllProctors"
                        />
                        Select all
                    </label>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm"
                    >
                        <span class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40"
                                :checked="selectedProctors.includes(teacher.id)"
                                :title="isSubjectTeacherExempt(teacher) ? `Exempt from proctoring on day(s) ${exemptDays(teacher).join(', ')}` : ''"
                                @change="toggleProctor(teacher.id)"
                            />
                            <span class="text-gray-700">
                                <button
                                    type="button"
                                    class="text-indigo-600 hover:text-indigo-900"
                                    @click.stop.prevent="openTeacher(teacher.id)"
                                >
                                    {{ teacherName(teacher) }}
                                </button>
                            </span>
                        </span>
                        <span class="flex flex-col items-end text-right">
                            <span class="font-medium text-gray-700">
                                {{ teachingLoad(teacher) }} teaching slot{{ teachingLoad(teacher) === 1 ? '' : 's' }}
                            </span>
                            <span class="text-xs text-gray-500">{{ dayBreakdown(teachingLoadsByDay, teacher) }}</span>
                            <span
                                class="mt-1 font-medium"
                                :class="proctorAtCap(teacher) ? 'text-red-600' : 'text-gray-600'"
                            >
                                {{ proctorLoad(teacher) }} proctor
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ dayBreakdown(proctorLoadsByDay, teacher) }} · cap {{ group.max_proctors_per_teacher }}/day
                            </span>
                            <span class="mt-1 text-xs font-medium text-gray-700">
                                Overall: {{ overallLoad(teacher) }}
                                <span class="font-normal text-gray-500">({{ dayBreakdown(overallLoadsByDay, teacher) }})</span>
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ subjectTeacherLoad(teacher) }} subject{{ subjectTeacherLoad(teacher) === 1 ? '' : 's' }}
                            </span>
                            <span
                                v-if="isSubjectTeacherExempt(teacher)"
                                class="mt-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800"
                            >
                                Exempt on day{{ exemptDays(teacher).length === 1 ? '' : 's' }} {{ exemptDays(teacher).join(', ') }}
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Schedule grid -->
        <div class="mt-6 rounded-lg bg-white shadow">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-800">Schedule grid</h3>
                <div class="flex items-center gap-2">
                    <button
                        v-for="day in dayTabs"
                        :key="day"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="activeDay === day ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="activeDay = day"
                    >
                        Day {{ day }}
                    </button>
                    <PrimaryButton v-if="isDraft" type="button" @click="openAddSchedule">Add Schedule</PrimaryButton>
                </div>
            </div>

            <p v-if="!hasSchedules" class="border-b border-gray-100 px-6 py-3 text-sm text-gray-500">
                No schedules generated yet. Configure the setup and generate, or add one manually.
            </p>

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
                            <th v-if="isDraft" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template v-for="block in dayBlocks" :key="`grade-${block.gradeId}`">
                            <tr class="bg-gray-100">
                                <td :colspan="isDraft ? 7 : 6" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                    Grade {{ block.grade?.grade_level ?? block.gradeId }}
                                </td>
                            </tr>
                            <template v-for="group in block.groups" :key="group.key">
                                <tr v-if="group.elective && group.placeholder">
                                    <td class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-top text-sm font-medium text-gray-900">
                                        {{ group.timeLabel || '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ group.subject.subject_name }}
                                        <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Elective</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">NA</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">Subject Teachers</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">Subject Teachers</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">Classroom Assignment</td>
                                    <td v-if="isDraft"></td>
                                </tr>
                                <template v-else>
                                    <tr v-for="(row, rowIndex) in group.rows" :key="`${group.key}-${row.section.id}`">
                                        <td
                                            v-if="rowIndex === 0"
                                            :rowspan="group.rows.length"
                                            class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-middle text-sm font-medium text-gray-900"
                                        >
                                            {{ group.timeLabel || '—' }}
                                        </td>
                                        <td
                                            v-if="rowIndex === 0"
                                            :rowspan="group.rows.length"
                                            class="border-r border-gray-100 px-4 py-3 align-middle text-sm text-gray-700"
                                        >
                                            {{ group.subject.subject_name }}
                                            <span v-if="group.elective" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Elective</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ row.section.section_name }}
                                            <span
                                                v-if="row.unplaced"
                                                class="ms-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700"
                                                :title="row.unplaced.reason"
                                            >
                                                Unplaced
                                            </span>
                                        </td>
                                        <td
                                            v-if="rowIndex === 0 && uniformTeacher(group)"
                                            :rowspan="group.rows.length"
                                            class="border-r border-gray-100 px-4 py-3 align-middle text-sm text-gray-700"
                                        >
                                            <button
                                                v-if="uniformTeacher(group)?.id"
                                                class="text-indigo-600 hover:text-indigo-900"
                                                @click="openTeacher(uniformTeacher(group).id)"
                                            >
                                                {{ teacherName(uniformTeacher(group)) }}
                                            </button>
                                            <span v-else>—</span>
                                        </td>
                                        <td v-else-if="!uniformTeacher(group)" class="px-4 py-3 text-sm text-gray-700">
                                            <button
                                                v-if="row.schedule?.subject_teacher_id"
                                                class="text-indigo-600 hover:text-indigo-900"
                                                @click="openTeacher(row.schedule.subject_teacher_id)"
                                            >
                                                {{ teacherName(row.schedule.subject_teacher) }}
                                            </button>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <button
                                                v-if="row.schedule?.proctor_id"
                                                class="text-indigo-600 hover:text-indigo-900"
                                                @click="openTeacher(row.schedule.proctor_id)"
                                            >
                                                {{ teacherName(row.schedule.proctor) }}
                                            </button>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ row.section.room?.room_name ?? '—' }}</td>
                                        <td v-if="isDraft" class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                            <template v-if="row.schedule">
                                                <button class="text-indigo-600 hover:text-indigo-900" @click="openEditSchedule(row.schedule)">Edit</button>
                                                <button class="ms-3 text-red-600 hover:text-red-900" @click="removeSchedule(row.schedule)">Remove</button>
                                            </template>
                                            <span v-else>—</span>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                        </template>
                        <tr v-if="dayBlocks.length === 0">
                            <td :colspan="isDraft ? 7 : 6" class="px-4 py-6 text-center text-sm text-gray-500">
                                No sections or subjects are configured for this group yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showScheduleModal" @close="showScheduleModal = false" max-width="xl">
            <form class="p-6" @submit.prevent="submitSchedule">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingSchedule ? 'Edit Schedule' : 'Add Schedule' }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="schedule_day" value="Day" />
                        <select
                            id="schedule_day"
                            v-model="scheduleForm.day"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="day in dayTabs" :key="day" :value="day">Day {{ day }}</option>
                        </select>
                        <InputError class="mt-2" :message="scheduleForm.errors.day" />
                    </div>
                    <div>
                        <InputLabel for="schedule_section" value="Section" />
                        <select
                            id="schedule_section"
                            v-model="scheduleForm.section_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select a section</option>
                            <option v-for="section in group.sections" :key="section.id" :value="section.id">
                                {{ section.section_name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="scheduleForm.errors.section_id" />
                    </div>
                    <div>
                        <InputLabel for="schedule_subject" value="Subject" />
                        <select
                            id="schedule_subject"
                            v-model="scheduleForm.subject_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select a subject</option>
                            <option v-for="subject in availableSubjects" :key="subject.id" :value="subject.id">
                                {{ subject.subject_name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="scheduleForm.errors.subject_id" />
                    </div>
                    <div>
                        <InputLabel value="Subject Teacher" />
                        <div class="mt-1 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                            <span v-if="assignedTeacher" class="text-gray-700">{{ teacherName(assignedTeacher) }}</span>
                            <span v-else class="text-gray-400">No teacher assigned</span>
                        </div>
                        <p
                            v-if="scheduleForm.section_id && scheduleForm.subject_id && !assignedTeacher"
                            class="mt-1 text-xs text-red-600"
                        >
                            No teacher is assigned to this subject for the section.
                        </p>
                        <InputError class="mt-2" :message="scheduleForm.errors.subject_id" />
                    </div>
                    <div>
                        <InputLabel for="schedule_proctor" value="Proctor" />
                        <select
                            id="schedule_proctor"
                            v-model="scheduleForm.proctor_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select a teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacherName(teacher) }}</option>
                        </select>
                        <InputError class="mt-2" :message="scheduleForm.errors.proctor_id" />
                    </div>
                    <div>
                        <InputLabel for="schedule_start" value="Start Time" />
                        <TextInput id="schedule_start" v-model="scheduleForm.start_time" type="time" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="scheduleForm.errors.start_time" />
                    </div>
                    <div>
                        <InputLabel for="schedule_end" value="End Time" />
                        <TextInput id="schedule_end" v-model="scheduleForm.end_time" type="time" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="scheduleForm.errors.end_time" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showScheduleModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="scheduleForm.processing || !assignedTeacher">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="assignModalOpen" @close="assignModalOpen = false" max-width="xl">
            <form class="p-6" @submit.prevent="saveAssignments">
                <h2 class="text-lg font-medium text-gray-900">
                    Assign Teachers — {{ assignUnplaced?.section?.section_name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Choose the teacher assigned to each subject for this section. Leave blank if unassigned.
                </p>

                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto">
                    <div
                        v-for="subject in assignSubjects"
                        :key="subject.id"
                        class="grid grid-cols-1 items-center gap-2 sm:grid-cols-2"
                    >
                        <span class="text-sm text-gray-700">{{ subject.subject_name }}</span>
                        <select
                            v-model="assignmentValues[subject.id]"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">No teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacherName(teacher) }}
                            </option>
                        </select>
                    </div>
                    <p v-if="assignSubjects.length === 0" class="text-sm text-gray-500">
                        No subjects are defined for this grade level yet.
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="assignModalOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton>Save Assignments</PrimaryButton>
                </div>
            </form>
        </Modal>

        <TeacherDetailsModal
            :show="detailsOpen"
            :teacher-id="detailsTeacherId"
            @close="detailsOpen = false"
        />
    </AdminLayout>
</template>
