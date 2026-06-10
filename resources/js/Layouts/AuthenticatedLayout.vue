<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    Briefcase,
    Calendar,
    CheckSquare,
    LayoutDashboard,
    LogOut,
    Mail,
    Megaphone,
    Receipt,
    RefreshCcw,
    Search,
    Settings,
    Target,
    User,
    UserCog,
    Users,
} from '@lucide/vue';

const showingNavigationDropdown = ref(false);

const groups = [
    {
        label: 'Menu',
        links: [
            ['dashboard', 'Dashboard', LayoutDashboard],
            ['clients.index', 'Clienti', Users],
            ['projects.index', 'Progetti', Briefcase],
            ['tasks.index', 'Task', CheckSquare],
            ['calendar.index', 'Calendario', Calendar],
        ],
    },
    {
        label: 'Aggiornamenti',
        icon: RefreshCcw,
        links: [
            ['updates.social', 'Social', Megaphone],
            ['updates.newsletter', 'Newsletter', Mail],
            ['updates.seo', 'SEO', Search],
            ['updates.adv', 'ADV', Target],
        ],
    },
    {
        label: 'Account',
        links: [
            ['profile.edit', 'Profilo', User],
        ],
    },
    {
        label: 'Amministrazione',
        links: [
            ['billing.index', 'Fatturazione', Receipt],
            ['users.index', 'Utenti', UserCog],
            ['settings.index', 'Impostazioni', Settings],
        ],
    },
];
</script>

<template>
    <div class="min-h-screen bg-[hsl(var(--background))]">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-gray-200 bg-white lg:flex">
            <div class="flex h-14 items-center justify-between border-b border-gray-100 px-4">
                <Link :href="route('dashboard')" class="font-semibold tracking-tight text-gray-900">Agency Hub</Link>
                <Bell class="h-[18px] w-[18px] text-gray-400" :stroke-width="1.6" />
            </div>

            <div class="flex-1 overflow-y-auto p-3">
                <div v-for="group in groups" :key="group.label" class="mb-5">
                    <div class="mb-2 flex items-center gap-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-gray-400">
                        <component v-if="group.icon" :is="group.icon" class="h-3 w-3" :stroke-width="1.6" />
                        {{ group.label }}
                    </div>
                    <div class="space-y-1">
                        <Link
                            v-for="[name, label, icon] in group.links"
                            :key="name"
                            :href="route(name)"
                            :class="['nav-link', route().current(name) ? 'nav-link-active' : '']"
                        >
                            <component :is="icon" class="h-[18px] w-[18px]" :stroke-width="1.6" />
                            <span>{{ label }}</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 p-3">
                <Link :href="route('profile.edit')" class="mb-2 flex items-center gap-2 rounded-md p-2 hover:bg-gray-50">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                        {{ $page.props.auth.user.name?.slice(0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold text-gray-900">{{ $page.props.auth.user.name }}</div>
                        <div class="truncate text-[10px] text-gray-500">{{ $page.props.auth.user.email }}</div>
                    </div>
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="nav-link w-full">
                    <LogOut class="h-[18px] w-[18px]" :stroke-width="1.6" />
                    Esci
                </Link>
            </div>
        </aside>

        <div class="lg:pl-64">
            <nav class="border-b border-gray-100 bg-white lg:hidden">
                <div class="px-4 sm:px-6">
                    <div class="flex h-14 justify-between">
                        <div class="flex items-center">
                            <Link :href="route('dashboard')" class="font-semibold text-gray-900">Agency Hub</Link>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Settings Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>

                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <template v-for="group in groups" :key="group.label">
                            <ResponsiveNavLink
                                v-for="[name, label] in group.links"
                                :key="name"
                                :href="route(name)"
                                :active="route().current(name)"
                            >
                                {{ label }}
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header class="border-b border-gray-100 bg-white" v-if="$slots.header">
                <div class="px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="min-h-[calc(100vh-4rem)]">
                <slot />
            </main>
        </div>
    </div>
</template>
