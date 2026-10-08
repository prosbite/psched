<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    groups: { type: Array, required: true },
});

const selectedGroup = ref('');
const selectedSection = ref('');

const sections = computed(() => {
    const map = new Map();
    props.groups.forEach((group) => {
        (group.schedules ?? []).forEach((schedule) => {
            if (schedule.section) {
                map.set(schedule.section.id, schedule.section);
            }
        });
    });

    return [...map.values()].sort((a, b) => a.section_name.localeCompare(b.section_name));
});

const visibleGroups = computed(() =>
    props.groups
        .filter((group) => !selectedGroup.value || group.id === Number(selectedGroup.value))
        .map((group) => ({
            ...group,
            schedules: [...(group.schedules ?? [])]
                .filter((schedule) => !selectedSection.value || schedule.section_id === Number(selectedSection.value))
                .sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)) || a.section_id - b.section_id),
        }))
        .filter((group) => group.schedules.length > 0),
);

const shortTime = (time) => (time ? String(time).slice(0, 5) : '');
const teacherName = (teacher) => (teacher ? [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ') : '—');
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
                    <label for="filter_group" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Exam Day</label>
                    <select
                        id="filter_group"
                        v-model="selectedGroup"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">All days</option>
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
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ group.title }}</h2>
                    <p v-if="group.description" class="mt-1 text-sm text-gray-500">{{ group.description }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Section</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Room</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Proctor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="schedule in group.schedules" :key="schedule.id">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                    {{ shortTime(schedule.start_time) }}–{{ shortTime(schedule.end_time) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ schedule.section?.section_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ schedule.section?.room?.room_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ schedule.subject?.subject_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ teacherName(schedule.subject_teacher) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ teacherName(schedule.proctor) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>
