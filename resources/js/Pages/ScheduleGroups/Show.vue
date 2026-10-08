<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    group: { type: Object, required: true },
    allSections: { type: Array, required: true },
    allSubjects: { type: Array, required: true },
    allPresets: { type: Array, required: true },
    teachers: { type: Array, required: true },
    proctorLoads: { type: Object, default: () => ({}) },
});

const page = usePage();
const report = computed(() => page.props.flash?.generationReport ?? null);

const isDraft = computed(() => props.group.status === 'draft');
const isActive = computed(() => props.group.status === 'active');

const setupForm = useForm({
    time_preset_id: props.group.time_preset_id ?? '',
    sections: [],
    subjects: [],
});

onMounted(() => {
    setupForm.sections = (props.group.sections ?? []).map((section) => section.id);
    setupForm.subjects = (props.group.subjects ?? []).map((subject) => subject.id);
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

const action = (name, label, destructive = false) => {
    if (!destructive || confirm(`${label}?`)) {
        router.post(route(`schedule-groups.${name}`, props.group.id), {}, { preserveScroll: true });
    }
};

const shortTime = (time) => (time ? String(time).slice(0, 5) : '');
const teacherName = (teacher) => (teacher ? [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ') : '—');

const sortedSchedules = computed(() =>
    [...(props.group.schedules ?? [])].sort(
        (a, b) => String(a.start_time).localeCompare(String(b.start_time)) || a.section_id - b.section_id,
    ),
);

const scheduleGroups = computed(() => {
    const groups = [];
    const index = new Map();

    for (const schedule of sortedSchedules.value) {
        const key = `${schedule.start_time}-${schedule.end_time}`;

        if (!index.has(key)) {
            index.set(key, groups.length);
            groups.push({
                key,
                label: `${shortTime(schedule.start_time)}–${shortTime(schedule.end_time)}`,
                schedules: [],
            });
        }

        groups[index.get(key)].schedules.push(schedule);
    }

    return groups;
});

const scheduleForm = useForm({
    section_id: '',
    subject_id: '',
    subject_teacher_id: '',
    proctor_id: '',
    start_time: '07:00',
    end_time: '08:00',
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
    scheduleForm.start_time = '07:00';
    scheduleForm.end_time = '08:00';
    showScheduleModal.value = true;
};

const openEditSchedule = (schedule) => {
    editingSchedule.value = schedule;
    scheduleForm.section_id = schedule.section_id;
    scheduleForm.subject_id = schedule.subject_id;
    scheduleForm.subject_teacher_id = schedule.subject_teacher_id;
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
            <span v-if="group.time_preset" class="text-sm text-gray-600">
                Preset: {{ group.time_preset.name }} ({{ group.time_preset.slots?.length ?? 0 }} slots)
            </span>
            <span class="text-sm text-gray-600">Max proctorships/teacher: {{ group.max_proctors_per_teacher }}</span>

            <div class="ms-auto flex flex-wrap gap-2">
                <Link :href="route('schedule-groups.edit', group.id)">
                    <SecondaryButton type="button">Edit Group</SecondaryButton>
                </Link>
                <PrimaryButton v-if="isDraft" type="button" @click="action('generate', 'Regenerate the grid (this clears existing draft schedules)', true)">
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
            <ul v-if="report.unplaced.length" class="mt-2 list-disc space-y-1 ps-5 text-sm text-red-700">
                <li v-for="(item, index) in report.unplaced" :key="index">
                    {{ item.section_name }} — {{ item.subject_name }}: {{ item.reason }}
                </li>
            </ul>
            <ul v-if="report.warnings?.length" class="mt-2 list-disc space-y-1 ps-5 text-sm text-amber-700">
                <li v-for="(warning, index) in report.warnings" :key="index">{{ warning }}</li>
            </ul>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <!-- Setup -->
            <div class="rounded-lg bg-white p-6 shadow xl:col-span-1">
                <h3 class="text-base font-semibold text-gray-800">Setup</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Choose the preset day, the sections, and the subjects to schedule.
                </p>

                <div class="mt-4">
                    <InputLabel for="setup_preset" value="Time Preset" />
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
                    <InputLabel value="Subjects" />
                    <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-3">
                        <label v-for="subject in allSubjects" :key="subject.id" class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                :disabled="!isDraft"
                                :checked="setupForm.subjects.includes(subject.id)"
                                @change="toggle(setupForm.subjects, subject.id)"
                            />
                            {{ subject.subject_name }}
                            <span class="text-xs text-gray-400">{{ subject.grade_level?.grade_level }}</span>
                        </label>
                    </div>
                </div>

                <div v-if="isDraft" class="mt-4">
                    <PrimaryButton :disabled="setupForm.processing" @click="saveSetup">Save Setup</PrimaryButton>
                </div>
            </div>

            <!-- Teacher load -->
            <div class="rounded-lg bg-white p-6 shadow xl:col-span-2">
                <h3 class="text-base font-semibold text-gray-800">Teacher proctor load</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Proctorship count per teacher against the cap of {{ group.max_proctors_per_teacher }}.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm"
                    >
                        <span class="text-gray-700">{{ teacherName(teacher) }}</span>
                        <span
                            class="font-medium"
                            :class="(proctorLoads[teacher.id] ?? 0) >= group.max_proctors_per_teacher ? 'text-red-600' : 'text-gray-600'"
                        >
                            {{ proctorLoads[teacher.id] ?? 0 }}/{{ group.max_proctors_per_teacher }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule grid -->
        <div class="mt-6 rounded-lg bg-white shadow">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-800">Schedule grid</h3>
                <PrimaryButton v-if="isDraft" type="button" @click="openAddSchedule">Add Schedule</PrimaryButton>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Section</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject Teacher</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Proctor</th>
                            <th v-if="isDraft" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template v-for="group in scheduleGroups" :key="group.key">
                            <tr v-for="(schedule, rowIndex) in group.schedules" :key="schedule.id">
                                <td
                                    v-if="rowIndex === 0"
                                    :rowspan="group.schedules.length"
                                    class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-top text-sm font-medium text-gray-900"
                                >
                                    {{ group.label }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ schedule.section?.section_name }}
                                    <span v-if="schedule.section?.room" class="text-xs text-gray-400">({{ schedule.section.room.room_name }})</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ schedule.subject?.subject_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ teacherName(schedule.subject_teacher) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ teacherName(schedule.proctor) }}</td>
                                <td v-if="isDraft" class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    <button class="text-indigo-600 hover:text-indigo-900" @click="openEditSchedule(schedule)">Edit</button>
                                    <button class="ms-3 text-red-600 hover:text-red-900" @click="removeSchedule(schedule)">Remove</button>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="sortedSchedules.length === 0">
                            <td :colspan="isDraft ? 6 : 5" class="px-4 py-6 text-center text-sm text-gray-500">
                                No schedules yet. Configure the setup and generate, or add one manually.
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
                        <InputLabel for="schedule_teacher" value="Subject Teacher" />
                        <select
                            id="schedule_teacher"
                            v-model="scheduleForm.subject_teacher_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Select a teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacherName(teacher) }}</option>
                        </select>
                        <InputError class="mt-2" :message="scheduleForm.errors.subject_teacher_id" />
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
                    <PrimaryButton :disabled="scheduleForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
