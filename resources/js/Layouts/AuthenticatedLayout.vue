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
    ChevronDown,
    Menu,
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
    X,
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
            ['notifications.index', 'Notifiche', Bell],
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
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-white/60 bg-white/62 shadow-[20px_0_55px_rgba(28,42,73,0.08)] backdrop-blur-2xl lg:flex">
            <div class="flex h-16 items-center justify-between border-b border-white/60 px-4">
                <Link :href="route('dashboard')" class="flex items-center gap-2 font-semibold tracking-tight text-gray-950">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-white/80 text-sm font-extrabold text-indigo-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.8),0_12px_24px_rgba(79,70,229,0.14)]">L</span>
                    <span>Agency Hub</span>
                </Link>
                <div class="group relative">
                    <Link
                        :href="route('notifications.index')"
                        class="relative inline-flex h-9 w-9 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-indigo-600 hover:shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]"
                    >
                        <Bell class="h-[18px] w-[18px]" :stroke-width="1.6" />
                        <span
                            v-if="$page.props.notifications?.unread"
                            class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white"
                        >
                            {{ $page.props.notifications.unread > 9 ? '9+' : $page.props.notifications.unread }}
                        </span>
                    </Link>

                    <div class="invisible absolute right-0 top-11 z-40 w-80 overflow-hidden rounded-2xl border border-white/70 bg-white/78 opacity-0 shadow-[0_24px_70px_rgba(28,42,73,0.14)] backdrop-blur-2xl transition group-hover:visible group-hover:opacity-100">
                        <div class="flex items-center justify-between border-b border-white/60 px-3 py-2">
                            <span class="text-sm font-semibold text-gray-900">Notifiche</span>
                            <Link :href="route('notifications.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Vedi tutte</Link>
                        </div>
                        <div v-if="$page.props.notifications?.latest?.length" class="max-h-80 overflow-y-auto py-1">
                            <Link
                                v-for="notification in $page.props.notifications.latest"
                                :key="notification.id"
                                :href="notification.task_id ? route('tasks.show', notification.task_id) : route('notifications.index')"
                                :class="['block border-l-2 px-3 py-2 text-sm transition hover:bg-white/58', notification.read ? 'border-transparent text-gray-600' : 'border-indigo-600 bg-indigo-50/70 text-gray-900']"
                            >
                                <span class="line-clamp-2">{{ notification.message }}</span>
                            </Link>
                        </div>
                        <div v-else class="px-3 py-8 text-center text-sm text-gray-500">Nessuna notifica</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3 pt-4">
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

            <div class="border-t border-white/60 p-3">
                <Link :href="route('profile.edit')" class="mb-2 flex items-center gap-2 rounded-2xl p-2 transition hover:bg-white/58">
                    <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-indigo-100/80 text-xs font-semibold text-indigo-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.76)]">
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
            <nav class="sticky top-0 z-30 border-b border-white/60 bg-white/68 shadow-[0_16px_40px_rgba(28,42,73,0.08)] backdrop-blur-2xl lg:hidden">
                <div class="px-4 sm:px-6">
                    <div class="flex h-16 justify-between">
                        <div class="flex items-center">
                            <Link :href="route('dashboard')" class="flex items-center gap-2 font-semibold text-gray-950">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-white/80 text-sm font-extrabold text-indigo-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.8),0_12px_24px_rgba(79,70,229,0.14)]">L</span>
                                Agency Hub
                            </Link>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('notifications.index')"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-indigo-600"
                            >
                                <Bell class="h-[18px] w-[18px]" :stroke-width="1.6" />
                                <span
                                    v-if="$page.props.notifications?.unread"
                                    class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white"
                                >
                                    {{ $page.props.notifications.unread > 9 ? '9+' : $page.props.notifications.unread }}
                                </span>
                            </Link>
                            <!-- Settings Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-2xl">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-2xl border border-white/70 bg-white/64 px-3 py-2 text-sm font-semibold leading-4 text-gray-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.72)] transition duration-150 ease-in-out hover:text-gray-900 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <ChevronDown class="-me-0.5 ms-2 h-4 w-4" :stroke-width="1.8" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profilo
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Esci
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>

                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-gray-500 transition duration-150 ease-in-out hover:bg-white/70 hover:text-gray-900 focus:bg-white/70 focus:text-gray-900 focus:outline-none"
                            >
                                <X v-if="showingNavigationDropdown" class="h-5 w-5" :stroke-width="1.8" />
                                <Menu v-else class="h-5 w-5" :stroke-width="1.8" />
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
                    class="border-t border-white/60 bg-white/70 backdrop-blur-2xl sm:hidden"
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
                        class="border-t border-white/60 pb-1 pt-4"
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
                                Profilo
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Esci
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header class="border-b border-white/60 bg-white/46 backdrop-blur-2xl" v-if="$slots.header">
                <div class="px-4 py-6 sm:px-6 lg:px-8">
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
