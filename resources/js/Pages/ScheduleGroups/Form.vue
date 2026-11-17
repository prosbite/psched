<script setup>
import { computed, ref, watch } from 'vue';
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
    copyableGroups: { type: Array, default: () => [] },
});

const dayPresetValues = () => {
    const map = {};

    for (const preset of props.group?.day_presets ?? []) {
        map[Number(preset.day)] = preset.time_preset_id ?? '';
    }

    return map;
};

const form = useForm({
    title: props.group?.title ?? '',
    description: props.group?.description ?? '',
    time_preset_id: props.group?.time_preset_id ?? '',
    days: props.group?.days ?? 3,
    exams_per_day: props.group?.exams_per_day ?? 3,
    school_year_id: props.group?.school_year_id ?? '',
    quarter_id: props.group?.quarter_id ?? '',
    max_proctors_per_teacher: props.group?.max_proctors_per_teacher ?? 2,
    day_presets: dayPresetValues(),
    copy_setup_from: null,
});

const copySourceId = ref('');
const copyNotice = ref('');

const copyableSources = computed(() =>
    props.copyableGroups.filter(
        (group) => (group.section_ids?.length ?? 0) > 0 || (group.subject_ids?.length ?? 0) > 0,
    ),
);

const copySource = computed(
    () => copyableSources.value.find((group) => Number(group.id) === Number(copySourceId.value)) ?? null,
);

watch(copySourceId, () => {
    copyNotice.value = '';
    form.copy_setup_from = null;
});

const applyCopiedSetup = () => {
    const source = copySource.value;

    if (!source) {
        return;
    }

    const days = Math.max(1, Number(source.days) || 1);
    const dayPresets = {};

    for (let day = 1; day <= days; day++) {
        dayPresets[day] = '';
    }

    for (const preset of source.day_presets ?? []) {
        const day = Number(preset.day);

        if (day >= 1 && day <= days) {
            dayPresets[day] = preset.time_preset_id ?? '';
        }
    }

    form.time_preset_id = source.time_preset_id ?? '';
    form.days = days;
    form.exams_per_day = Number(source.exams_per_day) || 1;
    form.max_proctors_per_teacher = Number(source.max_proctors_per_teacher) || 0;
    form.day_presets = dayPresets;
    form.copy_setup_from = source.id;

    const sectionCount = source.section_ids?.length ?? 0;
    const subjectCount = source.subject_ids?.length ?? 0;

    copyNotice.value = `Copied the setup from "${source.title}". Its ${sectionCount} section(s) and ${subjectCount} subject(s) are applied when you save.`;
};

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
            <div v-if="!group && copyableSources.length > 0" class="mb-6 rounded-md border border-dashed border-gray-300 p-3">
                <InputLabel for="copy_setup_source" value="Copy setup from a previous group" />
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <select
                        id="copy_setup_source"
                        v-model="copySourceId"
                        class="block w-full flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Select a schedule group</option>
                        <option v-for="source in copyableSources" :key="source.id" :value="source.id">
                            #{{ source.id }} · {{ source.title }} ({{ source.days }} day{{ source.days === 1 ? '' : 's' }}, {{ source.exams_per_day }}/day)
                        </option>
                    </select>
                    <SecondaryButton type="button" :disabled="!copySource" @click="applyCopiedSetup">Use setup</SecondaryButton>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    Prefills the preset, days, exams per day, per-day presets, and proctorship limit. Its sections and
                    subject order are copied when you save.
                </p>
                <p v-if="copyNotice" class="mt-1 text-xs font-medium text-indigo-700">{{ copyNotice }}</p>
                <InputError class="mt-2" :message="form.errors.copy_setup_from" />
            </div>

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
                    <InputLabel for="time_preset_id" value="Default Time Preset" />
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

            <div class="mt-4">
                <InputLabel value="Per-day presets" />
                <p class="mt-1 text-xs text-gray-500">
                    Leave a day on "Use default" to use the default preset above.
                </p>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div v-for="day in (Number(form.days) || 1)" :key="day">
                        <InputLabel :for="`day_preset_${day}`" :value="`Day ${day} preset`" />
                        <select
                            :id="`day_preset_${day}`"
                            v-model="form.day_presets[day]"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Use default</option>
                            <option v-for="preset in timePresets" :key="preset.id" :value="preset.id">
                                {{ preset.name }} ({{ preset.slots_count }} slots)
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors[`day_presets.${day}`]" />
                    </div>
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
