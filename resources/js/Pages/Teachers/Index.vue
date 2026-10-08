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
    teachers: { type: Array, required: true },
    subjects: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({
    firstname: '',
    middlename: '',
    lastname: '',
    email: '',
    subjects: [],
});

const fullName = (teacher) => [teacher.firstname, teacher.middlename, teacher.lastname].filter(Boolean).join(' ');

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.subjects = [];
    showModal.value = true;
};

const openEdit = (teacher) => {
    editing.value = teacher;
    form.firstname = teacher.firstname;
    form.middlename = teacher.middlename ?? '';
    form.lastname = teacher.lastname;
    form.email = teacher.email ?? '';
    form.subjects = (teacher.subjects ?? []).map((subject) => subject.id);
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

const toggleSubject = (subjectId) => {
    const index = form.subjects.indexOf(subjectId);
    if (index === -1) {
        form.subjects.push(subjectId);
    } else {
        form.subjects.splice(index, 1);
    }
};
</script>

<template>
    <Head title="Teachers" />

    <AdminLayout title="Teachers">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Qualified subjects drive which teachers can be assigned to an exam.</p>
            <PrimaryButton @click="openCreate">Add Teacher</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subjects</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="teacher in teachers" :key="teacher.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ fullName(teacher) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ teacher.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <span
                                v-for="subject in teacher.subjects"
                                :key="subject.id"
                                class="me-1 mb-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                            >
                                {{ subject.subject_name }}
                            </span>
                            <span v-if="!teacher.subjects?.length" class="text-gray-400">None</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(teacher)">Edit</button>
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

                <div class="mt-4">
                    <InputLabel value="Qualified Subjects" />
                    <div class="mt-2 grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-md border border-gray-200 p-3 sm:grid-cols-2">
                        <label
                            v-for="subject in subjects"
                            :key="subject.id"
                            class="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                :checked="form.subjects.includes(subject.id)"
                                @change="toggleSubject(subject.id)"
                            />
                            {{ subject.subject_name }}
                            <span class="text-xs text-gray-400">{{ subject.grade_level?.grade_level }}</span>
                        </label>
                    </div>
                    <InputError class="mt-2" :message="form.errors.subjects" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
