<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { BrainCircuit, PackageOpen } from '@lucide/vue';
import { computed } from 'vue';

defineProps({ active: { type: String, default: 'analysis' } });
const page = usePage();
const tabs = computed(() => [
    { key: 'analysis', label: 'Analisi', route: 'ai-agency.index', section: 'ai_agency', icon: BrainCircuit },
    { key: 'modules', label: 'Moduli', route: 'modules.index', section: 'modules', icon: PackageOpen },
].filter((tab) => {
    const user = page.props.auth?.user;
    return (user?.role === 'superadmin' || user?.permissions?.includes(`${tab.section}.view`))
        && (['superadmin', 'admin'].includes(user?.role) || page.props.sectionVisibility?.[tab.section] !== false);
}));
</script>

<template>
    <nav aria-label="Sezioni Agenzia AI" class="mt-4 flex gap-5 border-b border-gray-200">
        <Link v-for="tab in tabs" :key="tab.key" :href="route(tab.route)"
            :aria-current="active === tab.key ? 'page' : undefined"
            class="inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-semibold transition-colors"
            :class="active === tab.key ? 'border-[hsl(var(--primary-app))] text-[hsl(var(--primary-app))]' : 'border-transparent text-gray-500 hover:text-[hsl(var(--primary-app))]'">
            <component :is="tab.icon" class="h-4 w-4" :stroke-width="1.7" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
