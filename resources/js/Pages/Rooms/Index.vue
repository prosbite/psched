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
    rooms: { type: Array, required: true },
});

const showModal = ref(false);
const editing = ref(null);
const form = useForm({ room_name: '' });

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (room) => {
    editing.value = room;
    form.room_name = room.room_name;
    form.clearErrors();
    showModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('rooms.update', editing.value.id), { onSuccess: () => (showModal.value = false) });
    } else {
        form.post(route('rooms.store'), { onSuccess: () => (showModal.value = false) });
    }
};

const destroy = (room) => {
    if (confirm(`Delete ${room.room_name}?`)) {
        form.delete(route('rooms.destroy', room.id));
    }
};
</script>

<template>
    <Head title="Rooms" />

    <AdminLayout title="Rooms">
        <FlashBanner />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">Rooms are assigned to sections (one room per section).</p>
            <PrimaryButton @click="openCreate">Add Room</PrimaryButton>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Room</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sections</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="room in rooms" :key="room.id">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ room.room_name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ room.sections_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <button class="text-indigo-600 hover:text-indigo-900" @click="openEdit(room)">Edit</button>
                            <button class="ms-3 text-red-600 hover:text-red-900" @click="destroy(room)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="rooms.length === 0">
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No rooms yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editing ? 'Edit Room' : 'New Room' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="room_name" value="Room Name" />
                    <TextInput id="room_name" v-model="form.room_name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.room_name" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
