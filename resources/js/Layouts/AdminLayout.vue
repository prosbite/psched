<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.isAdmin === true);
const userName = computed(() => page.props.auth?.user?.name ?? 'Guest');

const collapsed = ref(false);
const mobileOpen = ref(false);

onMounted(() => {
    collapsed.value = localStorage.getItem('admin-sidebar-collapsed') === '1';
});

const toggleSidebar = () => {
    collapsed.value = !collapsed.value;
    localStorage.setItem('admin-sidebar-collapsed', collapsed.value ? '1' : '0');
};

const adminNav = [
    {
        label: 'Dashboard',
        route: 'dashboard',
        pattern: 'dashboard',
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    },
    {
        label: 'Schedule Groups',
        route: 'schedule-groups.index',
        pattern: 'schedule-groups.*',
        icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    },
    {
        label: 'Teachers',
        route: 'teachers.index',
        pattern: 'teachers.*',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        label: 'Users',
        route: 'users.index',
        pattern: 'users.*',
        icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
    },
    {
        label: 'Subjects',
        route: 'subjects.index',
        pattern: 'subjects.*',
        icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
    },
    {
        label: 'Grade Levels',
        route: 'grade-levels.index',
        pattern: 'grade-levels.*',
        icon: 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342',
    },
    {
        label: 'Sections',
        route: 'sections.index',
        pattern: 'sections.*',
        icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    },
    {
        label: 'Rooms',
        route: 'rooms.index',
        pattern: 'rooms.*',
        icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    },
    {
        label: 'Time Presets',
        route: 'time-presets.index',
        pattern: 'time-presets.*',
        icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    },
];

const isActive = (item) => route().current(item.pattern) || route().current(item.route);
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Mobile overlay -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            @click="mobileOpen = false"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex flex-col bg-gray-900 text-gray-300 transition-all duration-200"
            :class="[
                collapsed ? 'lg:w-20' : 'lg:w-64',
                mobileOpen ? 'w-64 translate-x-0' : '-translate-x-full lg:translate-x-0',
                'w-64',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-800 px-4">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <ApplicationLogo class="h-8 w-8 shrink-0 fill-current text-white" />
                    <span
                        v-show="!collapsed || mobileOpen"
                        class="truncate text-sm font-semibold uppercase tracking-wider text-white"
                    >
                        Exam Scheduler
                    </span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-4">
                <Link
                    v-for="item in adminNav"
                    v-show="isAdmin"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="isActive(item)
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
                    :title="item.label"
                >
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span v-show="!collapsed || mobileOpen" class="truncate">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="border-t border-gray-800 p-2">
                <Link
                    :href="route('public.schedules')"
                    target="_blank"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-300 transition hover:bg-gray-800 hover:text-white"
                >
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    <span v-show="!collapsed || mobileOpen" class="truncate">View public schedule</span>
                </Link>
            </div>
        </aside>

        <!-- Main column -->
        <div
            class="flex min-h-screen flex-col transition-all duration-200"
            :class="collapsed ? 'lg:pl-20' : 'lg:pl-64'"
        >
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
                <button
                    type="button"
                    class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden"
                    @click="mobileOpen = true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="hidden rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:inline-flex"
                    @click="toggleSidebar"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <h1 class="truncate text-lg font-semibold text-gray-800">
                    {{ title }}
                </h1>

                <div class="ms-auto flex items-center">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:text-gray-900"
                            >
                                {{ userName }}
                                <svg class="-me-0.5 ms-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
