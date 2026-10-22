<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { createDeviceModels } from './inventory/deviceModels';

const props = defineProps({ items: { type: Array, default: () => [] }, type: { type: String, default: 'physical' }, selectedId: String });
const emit = defineEmits(['select']);
const canvas = ref(null);
const ready = ref(false);
const unavailable = ref(false);
const rackIndex = ref(0);
const rackCount = ref(1);
let renderer, scene, camera, controls, inventory, resizeObserver, visibilityObserver, frameId;
let active = true;
let hoveredGroups = [];
let pulledGroup = null;
let targetRackX = 0;
let pointerDown = null;
const rackSpacing = 4.45;
const rackWidth = 3.75;
const slotsPerRack = 9;
const reducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const clickTargets = [];
const byId = new Map();
const materials = new Map();
const models = createDeviceModels();
const shelfMaterial = material('#627d99', .35, .48);
const backMaterial = material('#d5e2ef', 0, .9);

function material(color, metalness = 0, roughness = .6) {
    const key = `${color}:${metalness}:${roughness}`;
    if (!materials.has(key)) materials.set(key, new THREE.MeshStandardMaterial({ color, metalness, roughness }));
    return materials.get(key);
}

function box(parent, width, height, depth, x, y, z, surface, castShadow = true) {
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(width, height, depth), surface);
    mesh.position.set(x, y, z);
    mesh.castShadow = false;
    mesh.receiveShadow = false;
    parent.add(mesh);
    return mesh;
}

function clearInventory() {
    if (!inventory) return;
    scene.remove(inventory);
    inventory.traverse((object) => object.geometry?.dispose());
    inventory = null;
    clickTargets.length = 0;
    byId.clear();
    hoveredGroups = [];
    pulledGroup = null;
}

function build() {
    if (!scene) return;
    clearInventory();
    inventory = new THREE.Group();
    scene.add(inventory);
    const physical = props.type === 'physical';
    const items = props.items.filter((item) => item.type === props.type);
    const rows = 3;
    const columns = 3;
    const shelfDepth = physical ? 1.2 : .9;
    const shelfStep = physical ? 1.38 : 1.05;
    const base = .17;
    const height = base + rows * shelfStep;
    const placements = physical
        ? items.flatMap((item) => Array.from({ length: Math.min(Number(item.quantity || 0), 100) }, () => item))
        : items;
    rackCount.value = Math.max(1, Math.ceil(placements.length / slotsPerRack));
    for (let rack = 0; rack < rackCount.value; rack++) {
        const x = rack * rackSpacing;
        for (let row = 0; row < rows; row++) box(inventory, rackWidth, .075, shelfDepth, x, base + row * shelfStep, 0, shelfMaterial, false);
        for (const edge of [-rackWidth / 2, rackWidth / 2]) box(inventory, .09, height, shelfDepth, x + edge, height / 2, 0, shelfMaterial, false);
        box(inventory, rackWidth, height, .045, x, height / 2, -shelfDepth / 2, backMaterial, false);
        box(inventory, rackWidth, .08, shelfDepth, x, height, 0, shelfMaterial, false);
    }
    for (const [i, item] of placements.entries()) {
        const rack = Math.floor(i / slotsPerRack);
        const slot = i % slotsPerRack;
        const row = rows - 1 - Math.floor(slot / columns);
        const col = slot % columns;
        const x = rack * rackSpacing + (col - 1) * 1.08;
        const group = models.create(item, i, props.type);
        group.position.set(x, base + row * shelfStep + .04, physical ? .05 : .13);
        group.userData.home = group.position.clone();
        group.userData.rack = rack;
        group.traverse((object) => { object.userData.itemId = item.id; });
        inventory.add(group);
        clickTargets.push(group);
        byId.set(item.id, [...(byId.get(item.id) || []), group]);
    }
    rackIndex.value = Math.min(rackIndex.value, rackCount.value - 1);
    targetRackX = rackIndex.value * rackSpacing;
    camera.position.set(targetRackX, physical ? 3.8 : 3.2, physical ? 7.6 : 6.8);
    controls.target.set(targetRackX, height / 2, 0);
    controls.update();
    resize();
}

function moveRack(index) {
    rackIndex.value = THREE.MathUtils.clamp(index, 0, rackCount.value - 1);
    targetRackX = rackIndex.value * rackSpacing;
    pulledGroup = null;
}

function finishPointer(event) {
    if (!pointerDown) return;
    const distance = event.clientX - pointerDown.x;
    if (Math.abs(distance) > 45) moveRack(rackIndex.value + (distance < 0 ? 1 : -1));
}

function selectAt(event) {
    if (!renderer) return;
    if (pointerDown && Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y) > 7) return;
    const rect = canvas.value.getBoundingClientRect();
    pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
    raycaster.setFromCamera(pointer, camera);
    const hit = raycaster.intersectObjects(clickTargets, true)[0];
    if (hit?.object.userData.itemId) {
        emit('select', hit.object.userData.itemId);
        let group = hit.object;
        while (group.parent && group.parent !== inventory) group = group.parent;
        pulledGroup = pulledGroup === group ? null : group;
        moveRack(group.userData.rack);
        pulledGroup = group;
    } else {
        pulledGroup = null;
    }
}

function hoverAt(event) {
    if (!renderer) return;
    const rect = canvas.value.getBoundingClientRect();
    pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
    raycaster.setFromCamera(pointer, camera);
    const id = raycaster.intersectObjects(clickTargets, true)[0]?.object.userData.itemId;
    hoveredGroups = byId.get(id) || [];
    canvas.value.style.cursor = id ? 'pointer' : 'grab';
}

function clearHover() {
    hoveredGroups = [];
    if (canvas.value) canvas.value.style.cursor = 'grab';
}

function resize() {
    if (!renderer || !canvas.value) return;
    const { width, height } = canvas.value.getBoundingClientRect();
    if (!width || !height) return;
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    const frameDistance = ((rackWidth / 2) + .5) / (Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camera.aspect);
    camera.position.z = Math.max(props.type === 'physical' ? 7.6 : 6.8, frameDistance);
}

function animate() {
    frameId = requestAnimationFrame(animate);
    if (!active || !renderer) return;
    const slide = reducedMotion ? 1 : .09;
    const delta = targetRackX - controls.target.x;
    controls.target.x += delta * slide;
    camera.position.x += delta * slide;
    controls.update();
    for (const group of clickTargets) {
        const hovered = hoveredGroups.includes(group);
        const pulled = group === pulledGroup;
        const goal = pulled ? 1.65 : hovered ? 1.045 : 1;
        group.scale.lerp(new THREE.Vector3(goal, goal, goal), reducedMotion ? 1 : .12);
        const home = group.userData.home;
        const destination = pulled ? new THREE.Vector3(group.userData.rack * rackSpacing, 1.15, 2.1) : home;
        group.position.lerp(destination, reducedMotion ? 1 : .12);
    }
    renderer.render(scene, camera);
}

onMounted(() => {
    try {
        renderer = new THREE.WebGLRenderer({ canvas: canvas.value, antialias: true, powerPreference: 'high-performance' });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.shadowMap.enabled = false;
        scene = new THREE.Scene();
        scene.background = new THREE.Color('#eaf1f8');
        scene.fog = new THREE.Fog('#eaf1f8', 18, 31);
        camera = new THREE.PerspectiveCamera(35, 1, .1, 70);
        controls = new OrbitControls(camera, canvas.value);
        controls.enableDamping = true;
        controls.dampingFactor = .07;
        controls.enablePan = false;
        controls.minDistance = 1.8;
        controls.maxDistance = 25;
        controls.minPolarAngle = .4;
        controls.maxPolarAngle = 1.6;
        scene.add(new THREE.HemisphereLight('#ffffff', '#8a9aac', 2.6));
        const key = new THREE.DirectionalLight('#ffffff', 2.8);
        key.position.set(-5, 10, 8);
        key.castShadow = false;
        scene.add(key);
        const ground = new THREE.Mesh(new THREE.PlaneGeometry(200, 200), material('#e6edf5', 0, 1));
        ground.rotation.x = -Math.PI / 2;
        ground.position.y = -.03;
        ground.receiveShadow = false;
        scene.add(ground);
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(canvas.value);
        visibilityObserver = new IntersectionObserver(([entry]) => { active = entry.isIntersecting; });
        visibilityObserver.observe(canvas.value);
        build();
        resize();
        ready.value = true;
        animate();
    } catch {
        unavailable.value = true;
    }
});
watch(() => [props.items, props.type], build, { deep: true });
watch(() => props.selectedId, (id) => {
    const group = byId.get(id)?.[0];
    if (group) {
        rackIndex.value = group.userData.rack;
        targetRackX = rackIndex.value * rackSpacing;
    }
});
onBeforeUnmount(() => {
    cancelAnimationFrame(frameId);
    resizeObserver?.disconnect();
    visibilityObserver?.disconnect();
    controls?.dispose();
    clearInventory();
    renderer?.dispose();
    models.dispose();
    materials.forEach((material) => material.dispose());
});
</script>

<template>
    <div class="inventory-stage relative h-[440px] min-w-0 overflow-hidden bg-[#eaf1f8] sm:h-[520px]" aria-label="Vista 3D dell'inventario">
        <canvas ref="canvas" class="block h-full w-full touch-none" @pointerdown="pointerDown = { x: $event.clientX, y: $event.clientY }" @pointerup="finishPointer" @pointermove="hoverAt" @pointerleave="clearHover" @click="selectAt" />
        <div v-if="ready && rackCount > 1" class="absolute inset-x-4 bottom-4 z-10 flex items-center justify-between pointer-events-none">
            <button type="button" class="icon-btn pointer-events-auto h-9 w-9 bg-white/95 shadow-sm" :disabled="rackIndex === 0" title="Scaffale precedente" aria-label="Scaffale precedente" @click="moveRack(rackIndex - 1)"><ChevronLeft class="h-4 w-4" /></button>
            <span class="rounded-md bg-white/95 px-2 py-1 text-xs font-medium text-gray-700">{{ rackIndex + 1 }} / {{ rackCount }}</span>
            <button type="button" class="icon-btn pointer-events-auto h-9 w-9 bg-white/95 shadow-sm" :disabled="rackIndex === rackCount - 1" title="Scaffale successivo" aria-label="Scaffale successivo" @click="moveRack(rackIndex + 1)"><ChevronRight class="h-4 w-4" /></button>
        </div>
        <div v-if="unavailable" class="absolute inset-0 grid place-items-center px-5 text-center text-sm text-gray-600">La vista 3D non è disponibile su questo dispositivo. Puoi usare l'elenco per consultare e modificare l'inventario.</div>
    </div>
</template>
