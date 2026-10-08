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
import DangerButton from '@/Components/DangerButton.vue';

defineProps({
    gradeLevels: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ grade_level: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (gradeLevel) => {
    editing.value = gradeLevel;
    form.grade_level = gradeLevel.grade_level;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('grade-levels.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('grade-levels.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (gradeLevel) => {
    if (confirm(`Delete ${gradeLevel.grade_level}?`)) {
        form.delete(route('grade-levels.destroy', gradeLevel.id));
    }
};
</script>

<template>
    <Head title="Grade Levels" />

    <AdminLayout title="Grade Levels">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Manage the grade levels used by sections and subjects.</p>
            <PrimaryButton @click="openCreate">Add Grade Level</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sections</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subjects</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="gradeLevel in gradeLevels" :key="gradeLevel.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ gradeLevel.grade_level }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ gradeLevel.sections_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ gradeLevel.subjects_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(gradeLevel)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(gradeLevel)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="gradeLevels.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No grade levels yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Grade Level' : 'New Grade Level' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="grade_level" value="Grade Level" />
                    <TextInput
                        id="grade_level"
                        v-model="form.grade_level"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.grade_level" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
