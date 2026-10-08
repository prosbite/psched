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
    subjects: { type: Array, required: true },
    gradeLevels: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ subject_name: '', grade_level_id: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (subject) => {
    editing.value = subject;
    form.subject_name = subject.subject_name;
    form.grade_level_id = subject.grade_level_id;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('subjects.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('subjects.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (subject) => {
    if (confirm(`Delete ${subject.subject_name}?`)) {
        form.delete(route('subjects.destroy', subject.id));
    }
};
</script>

<template>
    <Head title="Subjects" />

    <AdminLayout title="Subjects">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Each subject belongs to a grade level and is taught by qualified teachers.</p>
            <PrimaryButton @click="openCreate">Add Subject</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Teachers</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="subject in subjects" :key="subject.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ subject.subject_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ subject.grade_level?.grade_level }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ subject.teachers_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(subject)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(subject)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="subjects.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No subjects yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Subject' : 'New Subject' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="subject_name" value="Subject Name" />
                    <TextInput id="subject_name" v-model="form.subject_name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.subject_name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="grade_level_id" value="Grade Level" />
                    <select
                        id="grade_level_id"
                        v-model="form.grade_level_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="" disabled>Select a grade level</option>
                        <option v-for="gradeLevel in gradeLevels" :key="gradeLevel.id" :value="gradeLevel.id">
                            {{ gradeLevel.grade_level }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.grade_level_id" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
