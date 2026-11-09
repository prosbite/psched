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
    sections: { type: Array, required: true },
    gradeLevels: { type: Array, required: true },
    rooms: { type: Array, required: true },
    subjects: { type: Array, required: true },
    teachers: { type: Array, required: true },
    assignments: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ section_name: '', grade_level_id: '', room_id: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (section) => {
    editing.value = section;
    form.section_name = section.section_name;
    form.grade_level_id = section.grade_level_id;
    form.room_id = section.room_id ?? '';
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('sections.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('sections.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (section) => {
    if (confirm(`Delete ${section.section_name}?`)) {
        form.delete(route('sections.destroy', section.id));
    }
};

const teacherName = (teacher) => [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ');

const assignModal = ref(false);
const assigningSection = ref(null);
const assignmentValues = ref({});

const assignableSubjects = computed(() => {
    if (!assigningSection.value) {
        return [];
    }

    return props.subjects.filter((subject) => subject.grade_level_id === assigningSection.value.grade_level_id);
});

const assignmentCount = (section) =>
    props.assignments.filter((assignment) => assignment.section_id === section.id).length;

const openAssign = (section) => {
    assigningSection.value = section;

    const values = {};

    for (const assignment of props.assignments) {
        if (assignment.section_id === section.id) {
            values[assignment.subject_id] = assignment.teacher_id;
        }
    }

    assignmentValues.value = values;
    assignModal.value = true;
};

const saveAssignments = () => {
    const payload = assignableSubjects.value.map((subject) => ({
        subject_id: subject.id,
        teacher_id: assignmentValues.value[subject.id] ?? '',
    }));

    router.put(
        route('sections.assignments.update', assigningSection.value.id),
        { assignments: payload },
        { preserveScroll: true, onSuccess: () => (assignModal.value = false) },
    );
};
</script>

<template>
    <Head title="Sections" />

    <AdminLayout title="Sections">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Assign each section a grade level and room, then assign a teacher to each subject.</p>
            <PrimaryButton @click="openCreate">Add Section</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Section</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Room</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assigned Subjects</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="section in sections" :key="section.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ section.section_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ section.grade_level?.grade_level }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ section.room?.room_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ assignmentCount(section) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openAssign(section)">Assign Teachers</button>
                            <button class="ms-3 text-indigo-600 hover:text-indigo-900" @click="openEdit(section)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(section)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="sections.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No sections yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Section' : 'New Section' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="section_name" value="Section Name" />
                    <TextInput id="section_name" v-model="form.section_name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.section_name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="section_grade_level" value="Grade Level" />
                    <select
                        id="section_grade_level"
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

                <div class="mt-4">
                    <InputLabel for="room_id" value="Room" />
                    <select
                        id="room_id"
                        v-model="form.room_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">No room</option>
                        <option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.room_name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.room_id" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="assignModal" @close="assignModal = false" max-width="xl">
            <form class="p-6" @submit.prevent="saveAssignments">
                <h2 class="text-lg font-medium text-gray-900">
                    Assign Teachers — {{ assigningSection?.section_name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Choose the teacher assigned to each subject for this section. Leave blank if unassigned.
                </p>

                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto">
                    <div
                        v-for="subject in assignableSubjects"
                        :key="subject.id"
                        class="grid grid-cols-1 items-center gap-2 sm:grid-cols-2"
                    >
                        <span class="text-sm text-gray-700">{{ subject.subject_name }}</span>
                        <select
                            v-model="assignmentValues[subject.id]"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">No teacher</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacherName(teacher) }}
                            </option>
                        </select>
                    </div>
                    <p v-if="assignableSubjects.length === 0" class="text-sm text-gray-500">
                        No subjects are defined for this grade level yet.
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="assignModal = false">Cancel</SecondaryButton>
                    <PrimaryButton>Save Assignments</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
