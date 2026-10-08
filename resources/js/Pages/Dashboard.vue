<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.isAdmin === true);
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout title="Dashboard">
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">
                Welcome, {{ $page.props.auth.user.name }}
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ isAdmin
                    ? 'Manage master data and build balanced exam-day schedules from the sidebar.'
                    : 'View the currently active exam schedules.' }}
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link
                    v-if="isAdmin"
                    :href="route('schedule-groups.index')"
                    class="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                >
                    Schedule Groups
                </Link>
                <Link
                    :href="route('public.schedules')"
                    class="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50"
                >
                    View Public Schedule
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
