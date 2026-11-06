<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({ taskId: { default: null } });
const open = ref(false);
const users = ref([]);
const query = ref('');
const position = ref({});
const selected = ref(0);
const loading = ref(false);
const error = ref(false);
let range;
let editor;
let loadedId;
const matches = computed(() => users.value.filter(user => (user.name || user.email || '').toLowerCase().includes(query.value.toLowerCase())));
watch(() => props.taskId, () => { open.value = false; users.value = []; loadedId = null; });
async function inspect(event) {
    const target = event.target.closest?.('[data-task-comment-editor], [data-calendar-comment-editor]');
    if (!target || !props.taskId) return;
    const selection = window.getSelection();
    if (!selection?.rangeCount || !selection.isCollapsed || selection.anchorNode?.nodeType !== Node.TEXT_NODE) { open.value = false; return; }
    const node = selection.anchorNode;
    if (!target.contains(node)) return;
    const text = node.textContent.slice(0, selection.anchorOffset);
    const match = text.match(/(?:^|\s)@([^@\n]{0,60})$/);
    if (!match) { open.value = false; return; }
    editor = target;
    range = selection.getRangeAt(0).cloneRange();
    range.setStart(node, selection.anchorOffset - match[1].length - 1);
    query.value = match[1];
    selected.value = 0;
    const rect = selection.getRangeAt(0).getBoundingClientRect();
    position.value = { left: `${Math.max(8, Math.min(rect.left, window.innerWidth - 296))}px`, top: `${Math.max(8, Math.min(rect.bottom + 6, window.innerHeight - 260))}px` };
    open.value = true;
    if (loadedId === props.taskId) return;
    const id = props.taskId;
    loadedId = id;
    loading.value = true;
    error.value = false;
    try {
        const response = await fetch(`/tasks/${encodeURIComponent(id)}/mention-users`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unavailable');
        const result = await response.json();
        if (props.taskId === id) users.value = result;
    } catch {
        if (props.taskId === id) { error.value = true; loadedId = null; }
    } finally { if (props.taskId === id) loading.value = false; }
}
function choose(user) {
    if (!range || !editor?.isConnected) return;
    const tag = document.createElement('span');
    tag.setAttribute('data-mention-user', user.id);
    tag.setAttribute('contenteditable', 'false');
    tag.className = 'font-semibold text-[hsl(var(--primary-app))]';
    tag.textContent = `@${user.name || user.email}`;
    range.deleteContents();
    range.insertNode(tag);
    const space = document.createTextNode('\u00a0');
    tag.after(space);
    range.setStartAfter(space);
    range.collapse(true);
    editor.focus();
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    open.value = false;
    editor.dispatchEvent(new Event('input', { bubbles: true }));
}
function keydown(event) {
    if (!open.value) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); open.value = false; }
    if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        selected.value = (selected.value + (event.key === 'ArrowDown' ? 1 : -1) + matches.value.length) % (matches.value.length || 1);
    }
    if (event.key === 'Enter' && matches.value[selected.value]) { event.preventDefault(); event.stopPropagation(); choose(matches.value[selected.value]); }
}
function outside(event) { if (!event.target.closest?.('[data-mention-menu]')) open.value = false; }
function close() { open.value = false; }
onMounted(() => {
    document.addEventListener('input', inspect);
    document.addEventListener('keydown', keydown, true);
    document.addEventListener('pointerdown', outside);
    window.addEventListener('resize', close);
});
onUnmounted(() => {
    document.removeEventListener('input', inspect);
    document.removeEventListener('keydown', keydown, true);
    document.removeEventListener('pointerdown', outside);
    window.removeEventListener('resize', close);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" data-mention-menu class="app-popover field-dropdown-menu fixed z-[11000] w-72 max-w-[calc(100vw-1rem)] p-2" :style="position" role="listbox" aria-label="Menziona utente" @mousedown.prevent>
            <div class="max-h-56 overflow-y-auto">
                <button v-for="(user, index) in matches" :key="user.id" type="button" role="option" :aria-selected="index === selected" class="field-dropdown-option flex w-full items-center gap-2 px-3 py-2 text-left text-sm" :class="index === selected ? 'bg-indigo-50' : ''" @click="choose(user)">
                    <UserAvatar :user="user" size="sm" /><span class="truncate">{{ user.name || user.email }}</span>
                </button>
                <p v-if="!matches.length" class="px-3 py-2 text-sm text-gray-500">{{ loading ? 'Caricamento...' : error ? 'Impossibile caricare gli utenti.' : 'Nessun partecipante trovato' }}</p>
            </div>
        </div>
    </Teleport>
</template>
