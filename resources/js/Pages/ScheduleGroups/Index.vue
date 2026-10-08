<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    groups: { type: Array, required: true },
});

const form = useForm({});

const statusClasses = (status) => ({
    draft: 'bg-amber-100 text-amber-800',
    active: 'bg-green-100 text-green-800',
    archived: 'bg-gray-200 text-gray-700',
}[status] ?? 'bg-gray-100 text-gray-700');

const destroy = (group) => {
    if (confirm(`Delete "${group.title}" and all of its schedules?`)) {
        form.delete(route('schedule-groups.destroy', group.id));
    }
};
</script>

<template>
    <Head title="Schedule Groups" />

    <AdminLayout title="Schedule Groups">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Each schedule group represents one exam day.</p>
            <Link :href="route('schedule-groups.create')">
                <PrimaryButton>New Schedule Group</PrimaryButton>
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Time Preset</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sections</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subjects</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Schedules</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="group in groups" :key="group.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ group.title }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses(group.status)">
                                {{ group.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ group.time_preset?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ group.sections_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ group.subjects_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ group.schedules_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <Link :href="route('schedule-groups.show', group.id)" class="text-indigo-600 hover:text-indigo-900">Open</Link>
                            <Link :href="route('schedule-groups.edit', group.id)" class="ms-3 text-gray-600 hover:text-gray-900">Edit</Link>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(group)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="groups.length === 0">
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No schedule groups yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
