<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import Modal from '@/Components/Modal.vue';
import TeacherDetailsModal from '@/Components/TeacherDetailsModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    teachers: { type: Array, required: true },
    subjects: { type: Array, required: true },
    sections: { type: Array, required: true },
    gradeLevels: { type: Array, required: true },
    assignments: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const detailsOpen = ref(false);
const detailsTeacherId = ref(null);
const form = useForm({
    firstname: '',
    middlename: '',
    lastname: '',
    email: '',
});

const fullName = (teacher) => [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ');

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (teacher) => {
    editing.value = teacher;
    form.firstname = teacher.firstname;
    form.middlename = teacher.middlename ?? '';
    form.lastname = teacher.lastname;
    form.email = teacher.email ?? '';
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('teachers.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('teachers.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (teacher) => {
    if (confirm(`Delete ${fullName(teacher)}?`)) {
        form.delete(route('teachers.destroy', teacher.id));
    }
};

const openDetails = (teacher) => {
    detailsTeacherId.value = teacher.id;
    detailsOpen.value = true;
};

const assignOpen = ref(false);
const assigningTeacher = ref(null);
const activeGradeId = ref(null);
const selectedPairs = ref(new Set());

const gradeTabs = computed(() =>
    props.gradeLevels
        .filter((grade) => props.subjects.some((subject) => subject.grade_level_id === grade.id))
        .sort((a, b) => Number(a.grade_level) - Number(b.grade_level)),
);

const subjectsForGrade = (gradeId) => props.subjects.filter((subject) => subject.grade_level_id === gradeId);
const sectionsForGrade = (gradeId) => props.sections.filter((section) => section.grade_level_id === gradeId);

const pairKey = (sectionId, subjectId) => `${sectionId}-${subjectId}`;
const isPairSelected = (sectionId, subjectId) => selectedPairs.value.has(pairKey(sectionId, subjectId));

const togglePair = (sectionId, subjectId) => {
    const next = new Set(selectedPairs.value);
    const key = pairKey(sectionId, subjectId);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    selectedPairs.value = next;
};

const assignedSubjectCount = (teacher) =>
    new Set(
        props.assignments
            .filter((assignment) => assignment.teacher_id === teacher.id)
            .map((assignment) => assignment.subject_id),
    ).size;

const openAssign = (teacher) => {
    assigningTeacher.value = teacher;
    selectedPairs.value = new Set(
        props.assignments
            .filter((assignment) => assignment.teacher_id === teacher.id)
            .map((assignment) => pairKey(assignment.section_id, assignment.subject_id)),
    );

    const firstGrade = gradeTabs.value[0];
    activeGradeId.value = firstGrade ? firstGrade.id : null;
    assignOpen.value = true;
};

const saveAssignments = () => {
    const assignments = [...selectedPairs.value].map((key) => {
        const [section_id, subject_id] = key.split('-').map(Number);

        return { section_id, subject_id };
    });

    router.put(
        route('teachers.assignments.update', assigningTeacher.value.id),
        { assignments },
        { preserveScroll: true, onSuccess: () => (assignOpen.value = false) },
    );
};
</script>

<template>
    <Head title="Teachers" />

    <AdminLayout title="Teachers">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Assign each teacher's subjects and sections here, or manage them per subject on the Subjects page.</p>
            <PrimaryButton @click="openCreate">Add Teacher</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assigned Subjects</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="teacher in teachers" :key="teacher.id">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openDetails(teacher)">
                                {{ fullName(teacher) }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ teacher.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ assignedSubjectCount(teacher) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openAssign(teacher)">Assign Subjects</button>
                            <button class="ms-3 text-indigo-600 hover:text-indigo-900" @click="openEdit(teacher)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(teacher)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="teachers.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No teachers yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false" max-width="xl">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Teacher' : 'New Teacher' }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="firstname" value="First Name" />
                        <TextInput id="firstname" v-model="form.firstname" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.firstname" />
                    </div>
                    <div>
                        <InputLabel for="middlename" value="Middle Name" />
                        <TextInput id="middlename" v-model="form.middlename" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.middlename" />
                    </div>
                    <div>
                        <InputLabel for="lastname" value="Last Name" />
                        <TextInput id="lastname" v-model="form.lastname" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.lastname" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="email" value="Email (optional)" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="assignOpen" @close="assignOpen = false" max-width="2xl">
            <form class="p-6" @submit.prevent="saveAssignments">
                <h2 class="text-lg font-medium text-gray-900">
                    Assign Subjects — {{ assigningTeacher ? fullName(assigningTeacher) : '' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Toggle each section this teacher handles for a subject. Only sections of the subject's grade can be selected.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="grade in gradeTabs"
                        :key="grade.id"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="activeGradeId === grade.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="activeGradeId = grade.id"
                    >
                        Grade {{ grade.grade_level }}
                    </button>
                </div>

                <div class="mt-4 max-h-96 space-y-3 overflow-y-auto">
                    <div
                        v-for="subject in subjectsForGrade(activeGradeId)"
                        :key="subject.id"
                        class="rounded-md border border-gray-200 p-3"
                    >
                        <div class="text-sm font-medium text-gray-900">{{ subject.subject_name }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="section in sectionsForGrade(activeGradeId)"
                                :key="section.id"
                                type="button"
                                class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="
                                    isPairSelected(section.id, subject.id)
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                                @click="togglePair(section.id, subject.id)"
                            >
                                {{ section.section_name }}
                            </button>
                            <span v-if="sectionsForGrade(activeGradeId).length === 0" class="text-xs text-gray-500">
                                No sections for this grade.
                            </span>
                        </div>
                    </div>
                    <p v-if="subjectsForGrade(activeGradeId).length === 0" class="text-sm text-gray-500">
                        No subjects for this grade.
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="assignOpen = false">Cancel</SecondaryButton>
                    <PrimaryButton>Save Assignments</PrimaryButton>
                </div>
            </form>
        </Modal>

        <TeacherDetailsModal
            :show="detailsOpen"
            :teacher-id="detailsTeacherId"
            @close="detailsOpen = false"
        />
    </AdminLayout>
</template>
