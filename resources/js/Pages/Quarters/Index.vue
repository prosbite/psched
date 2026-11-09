<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    quarters: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '', start_date: '', end_date: '' });

const formatDate = (value) => (value ? value.slice(0, 10) : '—');

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (quarter) => {
    editing.value = quarter;
    form.name = quarter.name;
    form.start_date = formatDate(quarter.start_date);
    form.end_date = formatDate(quarter.end_date);
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('quarters.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('quarters.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (quarter) => {
    if (confirm(`Delete ${quarter.name}?`)) {
        form.delete(route('quarters.destroy', quarter.id));
    }
};
</script>

<template>
    <Head title="Quarters" />

    <AdminLayout title="Quarters">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Manage the quarters that schedule groups belong to.</p>
            <PrimaryButton @click="openCreate">Add Quarter</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">End Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Schedule Groups</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="quarter in quarters" :key="quarter.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ quarter.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(quarter.start_date) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(quarter.end_date) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ quarter.schedule_groups_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(quarter)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(quarter)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="quarters.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No quarters yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Quarter' : 'New Quarter' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="start_date" value="Start Date" />
                        <TextInput id="start_date" v-model="form.start_date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.start_date" />
                    </div>
                    <div>
                        <InputLabel for="end_date" value="End Date" />
                        <TextInput id="end_date" v-model="form.end_date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.end_date" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
