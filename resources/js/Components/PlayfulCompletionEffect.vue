<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { startCompletionAnimation } from "@/utils/completionAnimations";

const props = defineProps({ effect: { type: String, required: true } });
const emit = defineEmits(["finished"]);
const canvas = ref(null);
const veil = ref(null);
const message = ref(null);
let stop = () => {};
function finish() {
    stop();
    emit("finished");
}
onMounted(() => {
    stop = startCompletionAnimation({
        canvas: canvas.value,
        veil: veil.value,
        message: message.value,
        kind: props.effect === "snowfall" ? "snow" : props.effect,
        onFinish: finish,
    });
    window.addEventListener("resize", finish);
});
onUnmounted(() => {
    stop();
    window.removeEventListener("resize", finish);
});
</script>

<template>
    <div
        :class="['playful-completion', `playful-completion-${effect}`]"
        aria-hidden="true"
    >
        <div ref="veil" class="playful-completion-veil"></div>
        <canvas ref="canvas"></canvas>
        <div ref="message" class="playful-completion-message">
            {{ effect === "mario" ? "+1 TASK COMPLETATA" : "Completato" }}
        </div>
    </div>
</template>

<style scoped>
.playful-completion {
    position: fixed;
    inset: 0;
    z-index: 20000;
    pointer-events: none;
    overflow: hidden;
    isolation: isolate;
}
.playful-completion canvas,
.playful-completion-veil {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}
.playful-completion canvas {
    z-index: 1;
}
.playful-completion-veil {
    opacity: 0;
    background: linear-gradient(155deg, #10283d, #245470);
}
.playful-completion-mario .playful-completion-veil {
    background: linear-gradient(#daf2ff, #f3fbff);
}
.playful-completion-rocket .playful-completion-veil {
    background: linear-gradient(160deg, #071c2b, #18384c);
}
.playful-completion-message {
    position: absolute;
    left: 50%;
    top: 43%;
    transform: translate(-50%, -50%);
    z-index: 2;
    opacity: 0;
    color: #f5fbff;
    text-align: center;
    font:
        700 44px/1.2 Inter,
        system-ui,
        sans-serif;
    letter-spacing: 0;
    width: calc(100% - 32px);
    text-shadow: 0 4px 25px #163e5c40;
}
.playful-completion-mario .playful-completion-message {
    top: 23%;
    color: #174f6c;
    text-shadow: none;
    font:
        800 30px/1.2 ui-monospace,
        monospace;
}
@media (max-width: 520px) {
    .playful-completion-message {
        font-size: 36px;
    }
    .playful-completion-mario .playful-completion-message {
        font-size: 26px;
    }
}
</style>
