<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    subjects: { type: Array, required: true },
    gradeLevels: { type: Array, required: true },
    sections: { type: Array, required: true },
    teachers: { type: Array, required: true },
    assignments: { type: Array, required: true },
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

const teacherName = (teacher) => [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ');

const assignOpen = ref(false);
const assigningSubject = ref(null);
const assignmentValues = ref({});

const assignableSections = computed(() => {
    if (!assigningSubject.value) {
        return [];
    }

    return props.sections.filter((section) => section.grade_level_id === assigningSubject.value.grade_level_id);
});

const openAssign = (subject) => {
    assigningSubject.value = subject;

    const values = {};

    for (const assignment of props.assignments) {
        if (assignment.subject_id === subject.id) {
            values[assignment.section_id] = assignment.teacher_id;
        }
    }

    assignmentValues.value = values;
    assignOpen.value = true;
};

const saveAssignments = () => {
    const assignments = assignableSections.value.map((section) => ({
        section_id: section.id,
        teacher_id: assignmentValues.value[section.id] ?? '',
    }));

    router.put(
        route('subjects.assignments.update', assigningSubject.value.id),
        { assignments },
        { preserveScroll: true, onSuccess: () => (assignOpen.value = false) },
    );
};
</script>

<template>
    <Head title="Subjects" />

    <AdminLayout title="Subjects">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Each subject belongs to a grade level. Assign its teacher per section here, or manage a teacher's subjects on the Teachers page.</p>
            <PrimaryButton @click="openCreate">Add Subject</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assignments</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="subject in subjects" :key="subject.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ subject.subject_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ subject.grade_level?.grade_level }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ subject.section_subject_teachers_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openAssign(subject)">Assign Teachers</button>
                            <button class="ms-3 text-indigo-600 hover:text-indigo-900" @click="openEdit(subject)">Edit</button>
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

        <Modal :show="assignOpen" @close="assignOpen = false" max-width="xl">
            <form class="p-6" @submit.prevent="saveAssignments">
                <h2 class="text-lg font-medium text-gray-900">
                    Assign Teachers — {{ assigningSubject?.subject_name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Choose the teacher for each section sitting this subject. Leave blank if unassigned.
                </p>

                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto">
                    <div
                        v-for="section in assignableSections"
                        :key="section.id"
                        class="grid grid-cols-1 items-center gap-2 sm:grid-cols-2"
                    >
                        <span class="text-sm text-gray-700">
                            {{ section.section_name }}
                            <span class="text-xs text-gray-400">{{ section.grade_level?.grade_level }}</span>
                        </span>
                        <select
                            v-model="assignmentValues[section.id]"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">No teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacherName(teacher) }}
                            </option>
                        </select>
                    </div>
                    <p v-if="assignableSections.length === 0" class="text-sm text-gray-500">
                        No sections are defined for this grade level yet.
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="assignOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton>Save Assignments</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
