<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { browserNotificationSupport, enableCentroBrowserNotifications, showCentroBrowserNotification } from '@/utils/browserNotifications';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Briefcase,
    Calendar,
    CalendarX,
    CheckSquare,
    ChevronDown,
    Menu,
    LayoutDashboard,
    LogOut,
    Mail,
    Megaphone,
    Receipt,
    Search,
    Settings,
    Moon,
    Sun,
    Target,
    UserCog,
    Users,
    X,
} from '@lucide/vue';

const showingNavigationDropdown = ref(false);
const notificationMenuOpen = ref(false);
const collapsedGroups = ref({
    Aggiornamenti: true,
    Amministrazione: true,
});
const darkMode = ref(false);
const completionEffect = ref(null);
const page = usePage();
const notificationPermission = ref('unsupported');
const notificationStatusMessage = ref('');
let notificationPoller = null;
let completionEffectTimer = null;

const latestNotifications = computed(() => page.props.notifications?.latest || []);
const latestUnreadNotification = computed(() => latestNotifications.value.find((notification) => !notification.read));
const notificationBadgeCount = computed(() => page.props.notifications?.unread || 0);
const notificationStorageKey = computed(() => `centro:last-browser-notification:${page.props.auth?.user?.id || 'guest'}`);
const themeStorageKey = 'centro:theme';
const canManageAbsences = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.role));

function isGroupOpen(group) {
    return !group.collapsible || !collapsedGroups.value[group.label];
}

function toggleGroup(group) {
    if (!group.collapsible) return;

    collapsedGroups.value = {
        ...collapsedGroups.value,
        [group.label]: !collapsedGroups.value[group.label],
    };
}

function pseudoRandomPercent(index, salt = 1, min = 0, max = 100) {
    const raw = Math.sin((index + 1) * (salt * 12.9898)) * 43758.5453;
    const unit = raw - Math.floor(raw);
    return min + unit * (max - min);
}

function completionParticleStyle(index, kind) {
    if (kind === 'firework') {
        return {
            '--i': index,
            '--x': `${pseudoRandomPercent(index, 3.7, 8, 92)}%`,
            '--y': `${pseudoRandomPercent(index, 8.9, 10, 82)}%`,
            '--delay': `${pseudoRandomPercent(index, 2.3, 0, 1900)}ms`,
            '--scale': pseudoRandomPercent(index, 4.1, 0.72, 1.28),
        };
    }

    if (kind === 'snow') {
        return {
            '--i': index,
            '--x': `${pseudoRandomPercent(index, 5.1, 0, 100)}%`,
            '--delay': `${pseudoRandomPercent(index, 1.9, 0, 2200)}ms`,
            '--duration': `${pseudoRandomPercent(index, 7.3, 4.2, 7.2)}s`,
            '--size': `${pseudoRandomPercent(index, 9.1, 3, 9)}px`,
            '--drift': `${pseudoRandomPercent(index, 6.4, -90, 90)}px`,
            '--start-y': `${pseudoRandomPercent(index, 2.8, -70, -8)}vh`,
        };
    }

    return { '--i': index };
}

function applyTheme(enabled) {
    darkMode.value = enabled;
    document.documentElement.classList.toggle('dark', enabled);
    window.localStorage.setItem(themeStorageKey, enabled ? 'dark' : 'light');
}

function initializeTheme() {
    const savedTheme = window.localStorage.getItem(themeStorageKey);
    const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    applyTheme(savedTheme ? savedTheme === 'dark' : prefersDark);
}

function toggleDarkMode() {
    applyTheme(!darkMode.value);
}

function playCompletionEffect(event = null) {
    const effect = event?.detail?.effect || page.props.auth?.user?.completion_effect || 'balloons';
    completionEffect.value = effect;
    window.clearTimeout(completionEffectTimer);
    completionEffectTimer = window.setTimeout(() => {
        completionEffect.value = null;
    }, 5600);
}

function refreshNotificationPermission() {
    notificationPermission.value = browserNotificationSupport();
}

function rememberLatestNotification() {
    const latest = latestNotifications.value[0];
    if (latest?.id) {
        window.localStorage.setItem(notificationStorageKey.value, latest.id);
    }
}

async function enableBrowserNotifications() {
    const result = await enableCentroBrowserNotifications();
    notificationPermission.value = result.permission;
    notificationStatusMessage.value = result.message;
    rememberLatestNotification();
}

async function maybeShowBrowserNotification(notification) {
    if (typeof window === 'undefined' || !notification || notificationPermission.value !== 'granted') return;

    const lastId = window.localStorage.getItem(notificationStorageKey.value);
    if (lastId === notification.id) return;

    window.localStorage.setItem(notificationStorageKey.value, notification.id);
    await showCentroBrowserNotification('Il Centro', {
        body: notification.message,
        tag: notification.id,
        renotify: false,
        data: { url: notificationHref(notification) },
    });
}

function notificationHref(notification) {
    return notification.task_id ? route('tasks.show', notification.task_id) : route('notifications.index');
}

function openNotification(notification) {
    notificationMenuOpen.value = false;

    if (notification.read) {
        router.visit(notificationHref(notification));
        return;
    }

    router.patch(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onFinish: () => router.visit(notificationHref(notification)),
    });
}

function markAllNotificationsReadFromMenu() {
    router.patch(route('notifications.read-all'), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            router.reload({ only: ['notifications'], preserveScroll: true, preserveState: true });
        },
    });
}

watch(latestUnreadNotification, (notification) => {
    maybeShowBrowserNotification(notification);
});

onMounted(() => {
    initializeTheme();
    refreshNotificationPermission();
    rememberLatestNotification();
    window.addEventListener('centro:task-completed', playCompletionEffect);
    notificationPoller = window.setInterval(() => {
        router.reload({ only: ['notifications'], preserveScroll: true, preserveState: true });
    }, 15000);
});

onUnmounted(() => {
    window.clearInterval(notificationPoller);
    window.clearTimeout(completionEffectTimer);
    window.removeEventListener('centro:task-completed', playCompletionEffect);
});

const groups = computed(() => [
    {
        label: 'Menu',
        links: [
            ['dashboard', 'Dashboard', LayoutDashboard],
            ['clients.index', 'Clienti', Users],
            ['projects.index', 'Progetti', Briefcase],
            ['tasks.index', 'Task', CheckSquare],
            ['calendar.index', 'Calendario', Calendar],
            ...(canManageAbsences.value ? [['absences.index', 'Assenze', CalendarX]] : []),
            ['settings.index', 'Impostazioni', Settings],
        ],
    },
    {
        label: 'Aggiornamenti',
        collapsible: true,
        links: [
            ['updates.social', 'Social', Megaphone],
            ['updates.newsletter', 'Newsletter', Mail],
            ['updates.seo', 'SEO', Search],
            ['updates.adv', 'ADV', Target],
        ],
    },
    {
        label: 'Amministrazione',
        collapsible: true,
        links: [
            ['notifications.index', 'Notifiche', Bell],
            ['billing.index', 'Fatturazione', Receipt],
            ['users.index', 'Utenti', UserCog],
        ],
    },
]);
</script>

<template>
    <div class="min-h-screen bg-[hsl(var(--background))]">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-white/60 bg-white/62 shadow-[20px_0_55px_rgba(28,42,73,0.08)] backdrop-blur-2xl lg:flex">
            <div class="flex h-16 items-center justify-between border-b border-white/60 px-4">
                <Link :href="route('dashboard')" class="flex items-center gap-2 font-semibold tracking-tight text-gray-950">
                    <span class="brand-mark">
                        <ApplicationLogo class="h-7 w-7" />
                    </span>
                    <span>Il Centro</span>
                </Link>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-[hsl(var(--primary-app))] hover:shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]"
                        :aria-label="darkMode ? 'Disattiva modalità dark' : 'Attiva modalità dark'"
                        :title="darkMode ? 'Modalità chiara' : 'Modalità dark'"
                        @click="toggleDarkMode"
                    >
                        <Sun v-if="darkMode" class="h-[18px] w-[18px]" :stroke-width="1.7" />
                        <Moon v-else class="h-[18px] w-[18px]" :stroke-width="1.7" />
                    </button>
                    <div class="relative">
                        <button
                            type="button"
                            class="relative inline-flex h-9 w-9 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-[hsl(var(--primary-app))] hover:shadow-[inset_0_1px_0_rgba(255,255,255,0.72)]"
                            :aria-expanded="notificationMenuOpen"
                            aria-label="Apri notifiche"
                            @click="notificationMenuOpen = !notificationMenuOpen"
                        >
                            <Bell class="h-[18px] w-[18px]" :stroke-width="1.6" />
                            <span
                                v-if="notificationBadgeCount"
                                class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white"
                            >
                                {{ notificationBadgeCount > 9 ? '9+' : notificationBadgeCount }}
                            </span>
                        </button>

                        <Teleport to="body">
                        <button
                            v-if="notificationMenuOpen"
                            type="button"
                            class="fixed inset-0 z-[7800] cursor-default bg-transparent"
                            aria-label="Chiudi notifiche"
                            @click="notificationMenuOpen = false"
                        ></button>

                        <div
                            v-if="notificationMenuOpen"
                            class="app-popover fixed left-[17rem] top-4 z-[7900] w-96 max-w-[calc(100vw-18rem)] overflow-hidden rounded-2xl border border-white bg-white shadow-[0_24px_70px_rgba(28,42,73,0.14)]"
                            @click.stop
                        >
                            <div class="flex items-center justify-between border-b border-white/60 px-3 py-2">
                                <span class="text-sm font-semibold text-gray-900">Notifiche</span>
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="notificationBadgeCount"
                                        type="button"
                                        class="text-xs font-medium text-gray-500 transition hover:text-[hsl(var(--primary-app))]"
                                        @click="markAllNotificationsReadFromMenu"
                                    >
                                        Segna come lette
                                    </button>
                                    <Link :href="route('notifications.index')" class="text-xs font-medium text-[hsl(var(--primary-app))] hover:text-[hsl(var(--primary-app-dark))]" @click="notificationMenuOpen = false">Vedi tutte</Link>
                                </div>
                            </div>
                            <div v-if="notificationPermission === 'default'" class="border-b border-white/60 px-3 py-2">
                                <button type="button" class="text-xs font-semibold text-[hsl(var(--primary-app))] transition hover:text-[hsl(var(--primary-app-dark))]" @click="enableBrowserNotifications">
                                    Attiva notifiche browser
                                </button>
                            </div>
                            <div v-else-if="notificationStatusMessage" class="border-b border-white/60 px-3 py-2 text-xs text-gray-500">
                                {{ notificationStatusMessage }}
                            </div>
                            <div v-if="$page.props.notifications?.latest?.length" class="max-h-80 overflow-y-auto py-1">
                                <button
                                    v-for="notification in $page.props.notifications.latest"
                                    :key="notification.id"
                                    type="button"
                                    :class="['block w-full border-l-2 px-3 py-2 text-left text-sm transition hover:bg-white/58', notification.read ? 'border-transparent text-gray-600' : 'border-indigo-600 bg-indigo-50/70 text-gray-900']"
                                    @click="openNotification(notification)"
                                >
                                    <span class="line-clamp-2">{{ notification.message }}</span>
                                    <span class="mt-1 block text-[11px] text-gray-400">{{ notification.read ? 'Letta' : 'Da leggere' }}</span>
                                </button>
                            </div>
                            <div v-else class="px-3 py-8 text-center text-sm text-gray-500">Nessuna notifica</div>
                        </div>
                        </Teleport>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3 pt-4">
                <div v-for="group in groups" :key="group.label" class="mb-5">
                    <button
                        v-if="group.collapsible"
                        type="button"
                        class="mb-2 flex w-full items-center gap-1.5 rounded-2xl px-3 py-1.5 text-left text-[10px] font-semibold uppercase tracking-[0.12em] text-gray-400 transition hover:bg-white/58 hover:text-gray-600"
                        :aria-expanded="isGroupOpen(group)"
                        @click="toggleGroup(group)"
                    >
                        <component v-if="group.icon" :is="group.icon" class="h-3 w-3" :stroke-width="1.6" />
                        <span class="min-w-0 flex-1">{{ group.label }}</span>
                        <ChevronDown :class="['h-3.5 w-3.5 transition-transform', isGroupOpen(group) ? 'rotate-180' : '']" :stroke-width="1.8" />
                    </button>
                    <div v-else class="mb-2 flex items-center gap-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-gray-400">
                        <component v-if="group.icon" :is="group.icon" class="h-3 w-3" :stroke-width="1.6" />
                        {{ group.label }}
                    </div>
                    <div v-show="isGroupOpen(group)" class="space-y-1">
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
                    <UserAvatar :user="$page.props.auth.user" size="sm" />
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
                                <span class="brand-mark">
                                    <ApplicationLogo class="h-7 w-7" />
                                </span>
                                Il Centro
                            </Link>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-[hsl(var(--primary-app))]"
                                :aria-label="darkMode ? 'Disattiva modalità dark' : 'Attiva modalità dark'"
                                :title="darkMode ? 'Modalità chiara' : 'Modalità dark'"
                                @click="toggleDarkMode"
                            >
                                <Sun v-if="darkMode" class="h-[18px] w-[18px]" :stroke-width="1.7" />
                                <Moon v-else class="h-[18px] w-[18px]" :stroke-width="1.7" />
                            </button>
                            <Link
                                :href="route('notifications.index')"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-2xl text-gray-500 transition hover:bg-white/70 hover:text-[hsl(var(--primary-app))]"
                            >
                                <Bell class="h-[18px] w-[18px]" :stroke-width="1.6" />
                                <span
                                    v-if="notificationBadgeCount"
                                    class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white"
                                >
                                    {{ notificationBadgeCount > 9 ? '9+' : notificationBadgeCount }}
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
                                                <UserAvatar :user="$page.props.auth.user" size="xs" />
                                                <span class="ml-2">{{ $page.props.auth.user.name }}</span>

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

                <button
                    v-if="showingNavigationDropdown"
                    type="button"
                    class="fixed inset-0 top-16 z-20 bg-transparent sm:hidden"
                    aria-label="Chiudi menu"
                    @click="showingNavigationDropdown = false"
                ></button>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="relative z-30 border-t border-white/60 bg-white/70 backdrop-blur-2xl sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <template v-for="group in groups" :key="group.label">
                            <button
                                v-if="group.collapsible"
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-400"
                                :aria-expanded="isGroupOpen(group)"
                                @click="toggleGroup(group)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <component v-if="group.icon" :is="group.icon" class="h-3 w-3" :stroke-width="1.6" />
                                    {{ group.label }}
                                </span>
                                <ChevronDown :class="['h-4 w-4 transition-transform', isGroupOpen(group) ? 'rotate-180' : '']" :stroke-width="1.8" />
                            </button>
                            <div v-else class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-[0.12em] text-gray-400">
                                {{ group.label }}
                            </div>
                            <div v-show="isGroupOpen(group)" class="space-y-1">
                                <ResponsiveNavLink
                                    v-for="[name, label] in group.links"
                                    :key="name"
                                    :href="route(name)"
                                    :active="route().current(name)"
                                >
                                    {{ label }}
                                </ResponsiveNavLink>
                            </div>
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

            <header class="relative z-[2000] overflow-visible border-b border-white/70 bg-white/72 backdrop-blur-xl" v-if="$slots.header">
                <div class="relative z-[2001] overflow-visible px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="relative min-h-[calc(100vh-4rem)]">
                <slot />
            </main>
        </div>

        <Teleport to="body">
            <div v-if="completionEffect" :class="['completion-effect', `completion-effect-${completionEffect}`]" aria-hidden="true">
                <template v-if="completionEffect === 'balloons'">
                    <span v-for="index in 18" :key="`balloon-${index}`" class="completion-balloon" :style="{ '--i': index }"></span>
                </template>
                <template v-else-if="completionEffect === 'fireworks'">
                    <span v-for="index in 22" :key="`firework-${index}`" class="completion-firework" :style="completionParticleStyle(index, 'firework')"></span>
                </template>
                <template v-else-if="completionEffect === 'snow'">
                    <span v-for="index in 96" :key="`snow-${index}`" class="completion-snowflake" :style="completionParticleStyle(index, 'snow')"></span>
                </template>
                <template v-else-if="completionEffect === 'glitch'">
                    <div class="completion-glitch-panel">
                        <span>COMPLETATA</span>
                    </div>
                </template>
            </div>
        </Teleport>
    </div>
</template>
