<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Pause, Play } from '@lucide/vue';
import { drawSeasonObject, drawSeasonCorner, drawSeasonEffect, isSeasonalTheme } from '@/utils/seasonalArt';
import { drawSeasonAtmosphere } from '@/utils/seasonalAtmosphere';

const props = defineProps({ mode: { type: String, default: 'object' } });
const page = usePage();
const theme = computed(() => page.props.eventTheme);
const active = computed(() => isSeasonalTheme(theme.value));
const canvas = ref(null);
const paused = ref(false);
let media, observer, frame = 0, time = 0, last = 0, effectStart = null, visible = true;
const key = () => `centro:season-motion:${page.props.auth?.user?.id || 'guest'}`;
function readPreference() { try { paused.value = localStorage.getItem(key()) === 'off'; } catch { paused.value = false; } restart(); }
function toggle() { paused.value = !paused.value; try { localStorage.setItem(key(), paused.value ? 'off' : 'on'); } catch { /* Private mode may disable storage. */ } window.dispatchEvent(new Event('centro:season-motion')); }
function celebrate() { window.dispatchEvent(new Event('centro:season-celebrate')); }
function startEffect() { if (!active.value || paused.value || media?.matches || document.hidden) return; effectStart = time; restart(); }
function draw() {
    const el = canvas.value, c = el?.getContext('2d');
    if (!c) return;
    if (props.mode === 'effect' || props.mode === 'atmosphere') {
        const ratio = Math.min(window.devicePixelRatio || 1, 2), w = window.innerWidth, h = window.innerHeight;
        if (el.width !== Math.round(w * ratio) || el.height !== Math.round(h * ratio)) { el.width = Math.round(w * ratio); el.height = Math.round(h * ratio); }
        c.setTransform(ratio, 0, 0, ratio, 0, 0); c.clearRect(0, 0, w, h);
        if (props.mode === 'atmosphere' && !media?.matches && !paused.value) drawSeasonAtmosphere(c, theme.value, time, w, h);
        else if (effectStart !== null) drawSeasonEffect(c, theme.value, (time - effectStart) / 1500, w, h);
    } else {
        c.clearRect(0, 0, 480, 400); c.save(); c.scale(2, 2);
        if (props.mode === 'corner') drawSeasonCorner(c, theme.value, time);
        else drawSeasonObject(c, theme.value, time);
        c.restore();
    }
}
function tick(now) {
    frame = 0;
    if (now - last >= 33) { time += Math.min(now - last, 50); last = now; if (effectStart !== null && time - effectStart >= 1500) effectStart = null; draw(); }
    if (!paused.value && !media?.matches && visible && !document.hidden && (props.mode !== 'effect' || effectStart !== null)) frame = requestAnimationFrame(tick);
}
function restart() {
    cancelAnimationFrame(frame); frame = 0;
    if (!active.value || props.mode === 'control') return;
    if (paused.value || media?.matches || document.hidden) effectStart = null;
    draw(); last = performance.now();
    if (!paused.value && !media?.matches && visible && !document.hidden && (props.mode !== 'effect' || effectStart !== null)) frame = requestAnimationFrame(tick);
}
watch(theme, () => { time = 0; effectStart = null; restart(); }, { flush: 'post' });
watch(() => page.props.auth?.user?.id, readPreference);
onMounted(() => {
    media = matchMedia('(prefers-reduced-motion: reduce)');
    media.addEventListener('change', restart);
    document.addEventListener('visibilitychange', restart);
    window.addEventListener('centro:season-motion', readPreference);
    window.addEventListener('storage', readPreference);
    if (props.mode === 'effect') window.addEventListener('centro:season-celebrate', startEffect);
    if (canvas.value && props.mode !== 'effect') { observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; restart(); }); observer.observe(canvas.value); }
    readPreference();
});
onUnmounted(() => {
    cancelAnimationFrame(frame); observer?.disconnect(); media?.removeEventListener('change', restart);
    document.removeEventListener('visibilitychange', restart); window.removeEventListener('centro:season-motion', readPreference);
    window.removeEventListener('storage', readPreference); window.removeEventListener('centro:season-celebrate', startEffect);
});
</script>

<template>
    <template v-if="active">
        <button v-if="mode === 'control'" type="button" class="icon-btn h-8 w-8 shrink-0" :title="paused ? 'Attiva animazioni stagionali' : 'Pausa animazioni stagionali'" :aria-label="paused ? 'Attiva animazioni stagionali' : 'Pausa animazioni stagionali'" :aria-pressed="!paused" @click="toggle">
            <Play v-if="paused" class="h-4 w-4" /><Pause v-else class="h-4 w-4" />
        </button>
        <button v-else-if="mode === 'object'" type="button" class="season-object" title="Anima il tema" aria-label="Anima il tema stagionale" @click="celebrate">
            <canvas ref="canvas" width="480" height="400" aria-hidden="true" />
        </button>
        <canvas v-else ref="canvas" width="480" height="400" :class="mode === 'effect' ? 'season-fullscreen' : mode === 'atmosphere' ? 'season-atmosphere' : 'season-corner'" aria-hidden="true" />
    </template>
</template>

<style scoped>
.season-object { width: 64px; height: 52px; flex: none; padding: 0; border: 0; background: none; border-radius: 12px; }
.season-object canvas { width: 100%; height: 100%; object-fit: contain; }
.season-corner { position: absolute; width: 85px; height: 70px; right: 0; bottom: 0; opacity: .7; pointer-events: none; }
.season-fullscreen { position: fixed; inset: 0; width: 100vw; height: 100dvh; z-index: 7900; pointer-events: none; }
.season-atmosphere { position: fixed; inset: 0; width: 100vw; height: 100dvh; z-index: 25; pointer-events: none; }
</style>
