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
    schoolYears: { type: Array, required: true },
    quarters: { type: Array, required: true },
});

const form = useForm({
    title: props.group?.title ?? '',
    description: props.group?.description ?? '',
    time_preset_id: props.group?.time_preset_id ?? '',
    days: props.group?.days ?? 3,
    exams_per_day: props.group?.exams_per_day ?? 3,
    school_year_id: props.group?.school_year_id ?? '',
    quarter_id: props.group?.quarter_id ?? '',
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
                    <InputLabel for="school_year_id" value="School Year" />
                    <select
                        id="school_year_id"
                        v-model="form.school_year_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="" disabled>Select a school year</option>
                        <option v-for="year in schoolYears" :key="year.id" :value="year.id">
                            {{ year.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.school_year_id" />
                </div>
                <div>
                    <InputLabel for="quarter_id" value="Quarter" />
                    <select
                        id="quarter_id"
                        v-model="form.quarter_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="" disabled>Select a quarter</option>
                        <option v-for="quarter in quarters" :key="quarter.id" :value="quarter.id">
                            {{ quarter.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.quarter_id" />
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
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
                    <InputLabel for="days" value="Exam days" />
                    <TextInput
                        id="days"
                        v-model="form.days"
                        type="number"
                        min="1"
                        max="3"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.days" />
                </div>
                <div>
                    <InputLabel for="exams_per_day" value="Exams per day" />
                    <TextInput
                        id="exams_per_day"
                        v-model="form.exams_per_day"
                        type="number"
                        min="1"
                        max="10"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.exams_per_day" />
                </div>
                <div>
                    <InputLabel for="max_proctors_per_teacher" value="Max proctorships/teacher" />
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
