<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    teacherId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const details = ref(null);

const statusClasses = {
    draft: 'bg-amber-100 text-amber-800',
    active: 'bg-green-100 text-green-800',
    archived: 'bg-gray-200 text-gray-700',
};

const roleClasses = {
    proctor: 'bg-blue-100 text-blue-800',
    subject_teacher: 'bg-indigo-100 text-indigo-800',
};

const roleLabels = {
    proctor: 'Proctor',
    subject_teacher: 'Subject Teacher',
};

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

const fullName = (teacher) => [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ');

const groupedSchedules = (schedules) => {
    const groups = [];
    const index = new Map();

    for (const schedule of schedules) {
        const key = schedule.group.id;

        if (!index.has(key)) {
            index.set(key, groups.length);
            groups.push({ group: schedule.group, schedules: [] });
        }

        groups[index.get(key)].schedules.push(schedule);
    }

    return groups;
};

const load = async () => {
    if (!props.teacherId) {
        return;
    }

    loading.value = true;
    error.value = '';
    details.value = null;

    try {
        const response = await fetch(route('teachers.details', props.teacherId), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        details.value = await response.json();
    } catch (e) {
        error.value = 'Could not load teacher details.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.show, props.teacherId],
    ([show]) => {
        if (show) {
            load();
        } else {
            details.value = null;
            error.value = '';
        }
    },
);
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div v-if="details">
                    <h2 class="text-lg font-medium text-gray-900">{{ fullName(details.teacher) }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ details.teacher.email ?? '—' }}</p>
                </div>
                <h2 v-else class="text-lg font-medium text-gray-900">Teacher details</h2>
                <button type="button" class="text-gray-400 hover:text-gray-600" aria-label="Close" @click="emit('close')">✕</button>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Loading…</div>

            <div v-else-if="error" class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p>{{ error }}</p>
                <SecondaryButton type="button" class="mt-3" @click="load">Retry</SecondaryButton>
            </div>

            <template v-else-if="details">
                <!-- Subjects handled -->
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-800">Subjects handled</h3>
                    <p v-if="details.subjects.length === 0" class="mt-2 text-sm text-gray-500">No subjects assigned.</p>
                    <ul v-else class="mt-2 space-y-3">
                        <li
                            v-for="subject in details.subjects"
                            :key="subject.subject_id"
                            class="rounded-md border border-gray-200 p-3"
                        >
                            <div class="text-sm font-medium text-gray-900">
                                {{ subject.subject_name }}
                                <span v-if="subject.grade_level" class="text-xs font-normal text-gray-500">· {{ subject.grade_level }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span
                                    v-for="section in subject.sections"
                                    :key="section.id"
                                    class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                >
                                    {{ section.section_name }}<template v-if="section.grade_level"> ({{ section.grade_level }})</template>
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Exam schedules -->
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-800">Exam schedules</h3>
                    <p v-if="details.schedules.length === 0" class="mt-2 text-sm text-gray-500">No exam schedules yet.</p>
                    <div v-else class="mt-2 space-y-4">
                        <div
                            v-for="group in groupedSchedules(details.schedules)"
                            :key="group.group.id"
                            class="overflow-hidden rounded-md border border-gray-200"
                        >
                            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 bg-gray-50 px-3 py-2">
                                <span class="text-sm font-medium text-gray-900">{{ group.group.title }}</span>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClasses[group.group.status]"
                                >
                                    {{ group.group.status_label }}
                                </span>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                <li
                                    v-for="schedule in group.schedules"
                                    :key="schedule.id"
                                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <span class="text-gray-700">
                                        {{ displayTime(schedule.start_time) }}–{{ displayTime(schedule.end_time) }}
                                        · {{ schedule.section.section_name }}<template v-if="schedule.section.room"> ({{ schedule.section.room }})</template>
                                        · {{ schedule.subject.subject_name }}
                                    </span>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="roleClasses[schedule.role]"
                                    >
                                        {{ roleLabels[schedule.role] }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </Modal>
</template>
