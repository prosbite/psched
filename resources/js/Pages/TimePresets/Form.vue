<script setup>
import { onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashBanner from '@/Components/FlashBanner.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    timePreset: { type: Object, default: null },
    slots: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.timePreset?.name ?? '',
    slots: [],
});

onMounted(() => {
    form.slots = props.slots.length
        ? props.slots.map((slot) => ({ start_time: slot.start_time, end_time: slot.end_time }))
        : [{ start_time: '07:00', end_time: '08:00' }];
});

const addSlot = () => {
    form.slots.push({ start_time: '', end_time: '' });
};

const removeSlot = (index) => {
    form.slots.splice(index, 1);
};

const submit = () => {
    if (props.timePreset) {
        form.put(route('time-presets.update', props.timePreset.id));
    } else {
        form.post(route('time-presets.store'));
    }
};
</script>

<template>
    <Head :title="timePreset ? 'Edit Time Preset' : 'New Time Preset'" />

    <AdminLayout :title="timePreset ? 'Edit Time Preset' : 'New Time Preset'">
        <FlashBanner />

        <form class="max-w-2xl rounded-lg bg-white p-6 shadow" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Preset Name" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <InputLabel value="Time Slots" />
                    <SecondaryButton type="button" @click="addSlot">Add Slot</SecondaryButton>
                </div>
                <InputError class="mt-2" :message="form.errors.slots" />

                <div class="mt-2 space-y-3">
                    <div
                        v-for="(slot, index) in form.slots"
                        :key="index"
                        class="grid grid-cols-1 items-end gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-[1fr_1fr_auto]"
                    >
                        <div>
                            <InputLabel :value="`Start (${index + 1})`" />
                            <TextInput v-model="slot.start_time" type="time" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors[`slots.${index}.start_time`]" />
                        </div>
                        <div>
                            <InputLabel value="End" />
                            <TextInput v-model="slot.end_time" type="time" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors[`slots.${index}.end_time`]" />
                        </div>
                        <button
                            type="button"
                            class="mb-1 text-sm text-red-600 hover:text-red-900"
                            :disabled="form.slots.length === 1"
                            @click="removeSlot(index)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <Link :href="route('time-presets.index')">
                    <SecondaryButton type="button">Cancel</SecondaryButton>
                </Link>
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>
