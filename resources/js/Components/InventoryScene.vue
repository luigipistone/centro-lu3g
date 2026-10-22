<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { ZoomOut } from '@lucide/vue';
import { createDeviceModels } from './inventory/deviceModels';

const props = defineProps({ items: { type: Array, default: () => [] }, type: { type: String, default: 'physical' }, selectedId: String });
const emit = defineEmits(['select']);
const canvas = ref(null);
const labels = ref([]);
const ready = ref(false);
const unavailable = ref(false);
const inspecting = ref(false);
let renderer, scene, camera, controls, inventory, resizeObserver, visibilityObserver, frameId, selectedGroups = [];
let active = true;
let sceneWidth = 11.4;
let focusX = null;
let hoveredGroups = [];
let introStartedAt = null;
let introDistance = 0;
let flight = null;
const homePosition = new THREE.Vector3();
const homeTarget = new THREE.Vector3();
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
    mesh.castShadow = castShadow;
    mesh.receiveShadow = true;
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
    labels.value = [];
}

function build() {
    if (!scene) return;
    clearInventory();
    inventory = new THREE.Group();
    scene.add(inventory);
    const physical = props.type === 'physical';
    const items = props.items.filter((item) => item.type === props.type);
    const columns = physical ? 10 : 6;
    const rows = physical ? 3 : Math.max(3, Math.ceil(items.length / columns));
    const shelfWidth = physical ? 11.4 : 7.5;
    sceneWidth = shelfWidth;
    const shelfDepth = physical ? 1.2 : .9;
    const shelfStep = physical ? 1.38 : 1.05;
    const base = .17;
    for (let row = 0; row < rows; row++) {
        box(inventory, shelfWidth, .075, shelfDepth, 0, base + row * shelfStep, 0, shelfMaterial, false);
    }
    const height = base + rows * shelfStep;
    for (const x of [-shelfWidth / 2, shelfWidth / 2]) box(inventory, .09, height, shelfDepth, x, height / 2, 0, shelfMaterial, false);
    box(inventory, shelfWidth, height, .045, 0, height / 2, -shelfDepth / 2, backMaterial, false);
    box(inventory, shelfWidth, .08, shelfDepth, 0, height, 0, shelfMaterial, false);

    const placements = [];
    if (physical) {
        const preferred = ['iMac 23', 'iMac 27', 'iMac 21', 'Monitor', 'iPad Pro', 'iPad Air', 'iPhone'];
        const sorted = [...items].sort((a, b) => preferred.findIndex((name) => a.name.startsWith(name)) - preferred.findIndex((name) => b.name.startsWith(name)));
        const imac = sorted.find((item) => item.name.startsWith('iMac 23'));
        if (imac) for (let i = 0; i < Math.min(Number(imac.quantity), 9); i++) placements.push({ item: imac, row: 2, col: i });
        let middle = 0, bottom = 0;
        for (const item of sorted.filter((entry) => entry.id !== imac?.id)) {
            const count = Math.min(Number(item.quantity || 0), 20);
            for (let i = 0; i < count; i++) {
                const compact = /iphone/i.test(item.name);
                const row = compact ? 0 : 1;
                const col = compact ? bottom++ : middle++;
                placements.push({ item, row, col });
            }
        }
    } else {
        items.forEach((item, i) => placements.push({ item, row: rows - 1 - Math.floor(i / columns), col: i % columns }));
    }
    const centers = new Map();
    for (const [i, placement] of placements.entries()) {
        const { item, row, col } = placement;
        const count = placements.filter((entry) => entry.row === row).length;
        const colWidth = physical ? 1.08 : 1.08;
        const x = (col - (count - 1) / 2) * colWidth;
        const group = models.create(item, i, props.type);
        group.position.set(x, base + row * shelfStep + .04, physical ? .05 : .13);
        group.userData.baseY = group.position.y;
        group.traverse((object) => { object.userData.itemId = item.id; });
        inventory.add(group);
        clickTargets.push(group);
        byId.set(item.id, [...(byId.get(item.id) || []), group]);
        const center = centers.get(item.id) || { sum: 0, count: 0, y: group.position.y + .88, z: .25 };
        center.sum += x;
        center.count++;
        centers.set(item.id, center);
    }
    labels.value = physical ? items.map((item) => {
        const center = centers.get(item.id);
        return center ? { id: item.id, name: item.name, quantity: item.quantity, x: center.sum / center.count, y: center.y, z: center.z } : null;
    }).filter(Boolean) : [];
    camera.position.set(physical ? 1.0 : .7, physical ? 4.7 : 4.0, physical ? 11.8 : 8.6);
    controls.target.set(0, height / 2, 0);
    controls.update();
    resize();
    homePosition.copy(camera.position);
    homeTarget.copy(controls.target);
    inspecting.value = false;
    flight = null;
    introStartedAt = reducedMotion ? null : performance.now();
    highlight();
}

function highlight() {
    selectedGroups = byId.get(props.selectedId) || [];
    if (camera?.aspect < 1.1 && selectedGroups.length) {
        focusX = selectedGroups.reduce((sum, group) => sum + group.position.x, 0) / selectedGroups.length;
    }
}

function selectAt(event) {
    if (!renderer) return;
    const rect = canvas.value.getBoundingClientRect();
    pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
    raycaster.setFromCamera(pointer, camera);
    const hit = raycaster.intersectObjects(clickTargets, true)[0];
    if (hit?.object.userData.itemId) {
        emit('select', hit.object.userData.itemId);
        let group = hit.object;
        while (group.parent && group.parent !== inventory) group = group.parent;
        const target = new THREE.Vector3(group.position.x, group.position.y + .45, group.position.z);
        const direction = camera.position.clone().sub(controls.target).normalize();
        flyTo(target.clone().addScaledVector(direction, props.type === 'physical' ? 2.45 : 2.2), target);
        inspecting.value = true;
    }
}

function flyTo(position, target) {
    introStartedAt = null;
    if (reducedMotion) {
        camera.position.copy(position);
        controls.target.copy(target);
        controls.update();
        return;
    }
    flight = {
        fromPosition: camera.position.clone(), fromTarget: controls.target.clone(),
        position, target, startedAt: performance.now(),
    };
}

function resetView() {
    inspecting.value = false;
    flyTo(homePosition.clone(), homeTarget.clone());
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
    const frameDistance = ((sceneWidth / 2) + .7) / (Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camera.aspect);
    const distance = width < 640
        ? Math.min(frameDistance, props.type === 'physical' ? 14.5 : 10.5)
        : Math.max(props.type === 'physical' ? 11.8 : 8.6, frameDistance);
    homePosition.z = distance;
    if (!inspecting.value) camera.position.z = distance;
    introDistance = distance;
}

function animate() {
    frameId = requestAnimationFrame(animate);
    if (!active || !renderer) return;
    if (focusX !== null && !reducedMotion) {
        controls.target.x += (focusX - controls.target.x) * .065;
        if (Math.abs(focusX - controls.target.x) < .01) focusX = null;
    }
    if (introStartedAt !== null) {
        const progress = Math.min((performance.now() - introStartedAt) / 950, 1);
        camera.position.z = introDistance + 1.6 * (1 - progress) ** 3;
        if (progress === 1) introStartedAt = null;
    }
    if (flight) {
        const progress = Math.min((performance.now() - flight.startedAt) / 700, 1);
        const ease = 1 - (1 - progress) ** 3;
        camera.position.lerpVectors(flight.fromPosition, flight.position, ease);
        controls.target.lerpVectors(flight.fromTarget, flight.target, ease);
        if (progress === 1) flight = null;
    }
    controls.update();
    for (const group of clickTargets) {
        const hovered = hoveredGroups.includes(group);
        const goal = hovered ? 1.075 : selectedGroups.includes(group) ? 1.035 : 1;
        group.scale.lerp(new THREE.Vector3(goal, goal, goal), reducedMotion ? 1 : .12);
        group.position.y += ((group.userData.baseY + (hovered ? .045 : 0)) - group.position.y) * (reducedMotion ? 1 : .12);
    }
    renderer.render(scene, camera);
    if (labels.value.length) {
        const rect = canvas.value.getBoundingClientRect();
        for (const label of labels.value) {
            const projected = new THREE.Vector3(label.x, label.y, label.z).project(camera);
            const element = canvas.value.parentElement.querySelector(`[data-inventory-label="${label.id}"]`);
            if (element) {
                element.style.left = `${(projected.x + 1) * rect.width / 2}px`;
                element.style.top = `${(1 - projected.y) * rect.height / 2}px`;
                element.hidden = projected.z > 1 || projected.x < -1 || projected.x > 1;
            }
        }
    }
}

onMounted(() => {
    try {
        renderer = new THREE.WebGLRenderer({ canvas: canvas.value, antialias: true, powerPreference: 'high-performance' });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
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
        key.castShadow = true;
        key.shadow.mapSize.set(1024, 1024);
        key.shadow.camera.left = -11;
        key.shadow.camera.right = 11;
        key.shadow.camera.top = 9;
        key.shadow.camera.bottom = -9;
        scene.add(key);
        const ground = new THREE.Mesh(new THREE.PlaneGeometry(200, 200), material('#e6edf5', 0, 1));
        ground.rotation.x = -Math.PI / 2;
        ground.position.y = -.03;
        ground.receiveShadow = true;
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
watch(() => props.selectedId, highlight);
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
        <canvas ref="canvas" class="block h-full w-full touch-none" @pointerdown="introStartedAt = null; flight = null" @pointermove="hoverAt" @pointerleave="clearHover" @click="selectAt" />
        <div v-if="ready" class="pointer-events-none absolute inset-0" aria-hidden="true">
            <span v-for="label in labels" v-show="label.id === selectedId && !inspecting" :key="label.id" :data-inventory-label="label.id" class="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap rounded-md border border-white/80 bg-white/90 px-2 py-1 text-[11px] font-semibold text-gray-800 shadow-sm">
                {{ label.name }} <span class="text-gray-500">×{{ label.quantity }}</span>
            </span>
        </div>
        <button v-if="inspecting" type="button" class="icon-btn absolute right-4 top-4 z-10 h-9 w-9 bg-white/90 shadow-sm" title="Vista completa" aria-label="Vista completa" @click="resetView"><ZoomOut class="h-4 w-4" /></button>
        <div v-if="unavailable" class="absolute inset-0 grid place-items-center px-5 text-center text-sm text-gray-600">La vista 3D non è disponibile su questo dispositivo. Puoi usare l'elenco per consultare e modificare l'inventario.</div>
    </div>
</template>
