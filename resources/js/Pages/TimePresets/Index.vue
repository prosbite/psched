<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    timePresets: { type: Array, required: true },
});

const form = useForm({});

const destroy = (preset) => {
    if (confirm(`Delete ${preset.name}?`)) {
        form.delete(route('time-presets.destroy', preset.id));
    }
};
</script>

<template>
    <Head title="Time Presets" />

    <AdminLayout title="Time Presets">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Reusable exam-day time slots selected by a schedule group.</p>
            <Link :href="route('time-presets.create')">
                <PrimaryButton>Add Time Preset</PrimaryButton>
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Slots</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="preset in timePresets" :key="preset.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ preset.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ preset.slots_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <Link :href="route('time-presets.edit', preset.id)" class="text-indigo-600 hover:text-indigo-900">Edit</Link>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(preset)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="timePresets.length === 0">
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No time presets yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
