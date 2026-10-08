<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div v-if="success || error" class="mb-4 space-y-2">
        <div v-if="success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ success }}
        </div>
        <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ error }}
        </div>
    </div>
</template>
