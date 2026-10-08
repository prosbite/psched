<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    group: { type: Object, default: null },
    timePresets: { type: Array, required: true },
});

const form = useForm({
    title: props.group?.title ?? '',
    description: props.group?.description ?? '',
    time_preset_id: props.group?.time_preset_id ?? '',
    max_proctors_per_teacher: props.group?.max_proctors_per_teacher ?? 2,
});

const submit = () => {
    if (props.group) {
        form.put(route('schedule-groups.update', props.group.id));
    } else {
        form.post(route('schedule-groups.store'));
    }
};
</script>

<template>
    <Head :title="group ? 'Edit Schedule Group' : 'New Schedule Group'" />

    <AdminLayout :title="group ? 'Edit Schedule Group' : 'New Schedule Group'">
        <FlashBanner />

        <form class="max-w-2xl rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Title" />
                <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.title" />
            </div>

            <div class="mt-4">
                <InputLabel for="description" value="Description" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="time_preset_id" value="Time Preset" />
                    <select
                        id="time_preset_id"
                        v-model="form.time_preset_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">No preset</option>
                        <option v-for="preset in timePresets" :key="preset.id" :value="preset.id">
                            {{ preset.name }} ({{ preset.slots_count }} slots)
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.time_preset_id" />
                </div>
                <div>
                    <InputLabel for="max_proctors_per_teacher" value="Max proctorships per teacher" />
                    <TextInput
                        id="max_proctors_per_teacher"
                        v-model="form.max_proctors_per_teacher"
                        type="number"
                        min="0"
                        max="20"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.max_proctors_per_teacher" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <Link :href="route('schedule-groups.index')">
                    <SecondaryButton type="button">Cancel</SecondaryButton>
                </Link>
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
