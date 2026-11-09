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
    schoolYears: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ name: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (schoolYear) => {
    editing.value = schoolYear;
    form.name = schoolYear.name;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('school-years.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('school-years.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (schoolYear) => {
    if (confirm(`Delete ${schoolYear.name}?`)) {
        form.delete(route('school-years.destroy', schoolYear.id));
    }
};
</script>

<template>
    <Head title="School Years" />

    <AdminLayout title="School Years">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Manage the school years that schedule groups belong to.</p>
            <PrimaryButton @click="openCreate">Add School Year</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Schedule Groups</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="schoolYear in schoolYears" :key="schoolYear.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ schoolYear.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ schoolYear.schedule_groups_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(schoolYear)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(schoolYear)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="schoolYears.length === 0">
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No school years yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit School Year' : 'New School Year' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="2026-2027"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
