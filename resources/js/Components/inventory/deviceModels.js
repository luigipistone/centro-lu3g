import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

export function createDeviceModels() {
    const materials = new Map();
    const textures = new Map();
    let intelImac = null;
    let iphoneXs = null;
    let onReady = null;
    const loader = new GLTFLoader();

    function normalizeModel(model, width, rotation = 0) {
        const bounds = new THREE.Box3().setFromObject(model);
        const size = bounds.getSize(new THREE.Vector3());
        if (!size.x || !Number.isFinite(size.x)) return null;
        const normalized = new THREE.Group();
        model.scale.setScalar(width / size.x);
        model.rotation.y = rotation;
        normalized.add(model);
        const orientedBounds = new THREE.Box3().setFromObject(model);
        const center = orientedBounds.getCenter(new THREE.Vector3());
        model.position.set(-center.x, -orientedBounds.min.y, -center.z);
        return normalized;
    }

    loader.load('/models/inventory/imac-intel.glb', ({ scene }) => {
        intelImac = normalizeModel(scene, .85, Math.PI);
        onReady?.();
    }, undefined, () => {});
    loader.load('/models/inventory/iphone-xs.glb', ({ scene }) => {
        iphoneXs = normalizeModel(scene, .32);
        onReady?.();
    }, undefined, () => {});

    function surface(color, metalness = 0, roughness = .55) {
        const key = `${color}:${metalness}:${roughness}`;
        if (!materials.has(key)) materials.set(key, new THREE.MeshStandardMaterial({ color, metalness, roughness }));
        return materials.get(key);
    }

    function addBox(parent, width, height, depth, x, y, z, material, radius = 0) {
        const geometry = radius
            ? new RoundedBoxGeometry(width, height, depth, 4, Math.min(radius, width / 2, height / 2, depth / 2))
            : new THREE.BoxGeometry(width, height, depth);
        const mesh = new THREE.Mesh(geometry, material);
        mesh.position.set(x, y, z);
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        parent.add(mesh);
        return mesh;
    }

    function addDot(parent, radius, x, y, z, material) {
        const mesh = new THREE.Mesh(new THREE.CircleGeometry(radius, 20), material);
        mesh.position.set(x, y, z);
        parent.add(mesh);
        return mesh;
    }

    function makeScreenTexture(kind) {
        const portrait = kind === 'phone' || kind === 'tablet-pro' || kind === 'tablet-air';
        const canvas = document.createElement('canvas');
        canvas.width = portrait ? 256 : 512;
        canvas.height = portrait ? 512 : 320;
        const context = canvas.getContext('2d');
        const { width, height } = canvas;
        const palettes = {
            modern: ['#143a71', '#349abe', '#f1a1ab', '#efbd8e'],
            intel: ['#173e63', '#617e9b', '#e1b89d', '#995e6b'],
            hp: ['#113752', '#2394a7', '#75c9d3', '#d2e5e5'],
            phone: ['#071634', '#373779', '#e3886c', '#f1c293'],
            'tablet-pro': ['#1a2960', '#6071c4', '#e9b2bd', '#f2c9aa'],
            'tablet-air': ['#12364e', '#4f9aa6', '#8bd5c4', '#d6e5bf'],
        };
        const colors = palettes[kind] || palettes.modern;
        const gradient = context.createLinearGradient(0, 0, width, height);
        colors.forEach((color, index) => gradient.addColorStop(index / (colors.length - 1), color));
        context.fillStyle = gradient;
        context.fillRect(0, 0, width, height);
        context.globalAlpha = .65;
        for (let i = 0; i < 3; i++) {
            context.beginPath();
            context.moveTo(-width * .15, height * (.75 - i * .22));
            context.bezierCurveTo(width * .22, height * (.12 + i * .22), width * .57, height * (1.07 - i * .15), width * 1.15, height * (.19 + i * .22));
            context.lineWidth = portrait ? 100 : 135;
            context.strokeStyle = colors[(i + 1) % colors.length];
            context.stroke();
        }
        context.globalAlpha = 1;
        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = 4;
        return texture;
    }

    function screen(kind) {
        const key = `screen:${kind}`;
        if (!materials.has(key)) {
            const texture = makeScreenTexture(kind);
            textures.set(key, texture);
            materials.set(key, new THREE.MeshBasicMaterial({ map: texture, toneMapped: false }));
        }
        return materials.get(key);
    }

    const silver = surface('#bac3cd', .78, .28);
    const silverLight = surface('#e8edf1', .55, .35);
    const graphite = surface('#3d4853', .78, .29);
    const black = surface('#161c25', .32, .42);
    const lens = surface('#152c42', .15, .18);

    function addMonitor(parent, width, kind) {
        const hp = kind === 'hp';
        const modern = kind === 'modern';
        const panelHeight = width * (modern ? .62 : .59);
        const bottom = modern ? .255 : .29;
        const shell = hp ? surface('#292c32', .35, .42) : modern ? surface('#c9d1d5', .72, .3) : silver;
        const face = modern ? surface('#edf1f2', .32, .38) : black;
        const depth = modern ? .035 : hp ? .045 : .09;
        addBox(parent, width, panelHeight, depth, 0, bottom + panelHeight / 2, -.075, shell, modern ? .014 : .019);
        addBox(parent, width * .994, panelHeight * .987, .008, 0, bottom + panelHeight / 2, -.048, face, .008);
        const chin = modern ? .145 : hp ? .075 : .16;
        const screenWidth = width * (modern ? .94 : hp ? .96 : .89);
        const screenHeight = panelHeight * (1 - chin - .045);
        const screenY = bottom + panelHeight * (chin + .025) + screenHeight / 2;
        const display = addBox(parent, screenWidth, screenHeight, .004, 0, screenY, -.04, screen(hp ? 'hp' : modern ? 'modern' : 'intel'));
        display.receiveShadow = false;
        addDot(parent, .004, 0, bottom + panelHeight * .978, -.036, lens);
        if (modern) {
            addDot(parent, .009, 0, bottom + panelHeight * .072, -.035, surface('#aab4b9', .35, .43));
        } else if (hp) {
            addDot(parent, .009, 0, bottom + panelHeight * .035, -.035, silverLight);
            addBox(parent, width * .96, .008, .012, 0, bottom + .014, -.036, graphite, .002);
        }
        const neck = addBox(parent, width * (modern ? .105 : .08), .24, modern ? .032 : .055, 0, .16, -.17, hp ? graphite : silver, .008);
        neck.rotation.x = modern ? -.12 : -.17;
        if (hp) {
            addBox(parent, width * .5, .019, .26, 0, .019, -.12, graphite, .008);
            addBox(parent, width * .18, .007, .1, 0, .034, -.09, black, .003);
        } else {
            const foot = addBox(parent, width * .4, .018, .29, 0, .019, -.105, silver, .01);
            foot.rotation.x = -.025;
        }
        if (!modern && !hp) {
            const back = new THREE.Mesh(new THREE.SphereGeometry(1, 16, 10), silver);
            back.scale.set(width * .43, panelHeight * .37, .045);
            back.position.set(0, bottom + panelHeight / 2, -.145);
            parent.add(back);
        }
    }

    function addTablet(parent, pro) {
        const width = pro ? .53 : .49;
        const height = pro ? .76 : .71;
        const body = new THREE.Group();
        body.rotation.x = -.12;
        body.position.y = .08;
        parent.add(body);
        addBox(body, width, height, pro ? .042 : .031, 0, height / 2, 0, pro ? graphite : surface('#b9c4d1', .8, .28), .016);
        addBox(body, width * .987, height * .991, .006, 0, height / 2, .019, black, .006);
        addBox(body, width * .94, height * .955, .003, 0, height / 2, .024, screen(pro ? 'tablet-pro' : 'tablet-air'));
        addDot(body, .004, 0, height * .985, .028, lens);
        addBox(parent, width * .5, .025, .33, 0, .024, -.15, silver, .012);
        const support = addBox(parent, .025, height * .66, .05, 0, height * .33, -.17, silver);
        support.rotation.x = -.21;
        if (pro) {
            addBox(body, .065, .065, .012, -width * .32, height * .85, -.03, black, .01);
            addDot(body, .015, -width * .32, height * .85, -.038, lens);
        } else {
            addDot(body, .014, -width * .38, height * .89, -.021, lens);
            addBox(body, .006, .075, .012, width * .5, height * .7, 0, graphite, .003);
        }
    }

    function addPhone(parent) {
        const height = .64;
        addBox(parent, .304, height, .051, 0, height / 2 + .055, 0, surface('#adb7c0', .84, .23), .025);
        addBox(parent, .29, height * .98, .018, 0, height / 2 + .055, .028, black, .009);
        addBox(parent, .269, height * .94, .004, 0, height / 2 + .055, .04, screen('phone'));
        addBox(parent, .126, .027, .007, 0, height * .985, .046, black, .003);
        addDot(parent, .006, .036, height * .985, .051, lens);
        addBox(parent, .095, .017, .007, -.097, height * .7, -.031, silver);
        addBox(parent, .16, .02, .23, 0, .025, -.055, silver, .008);
        const support = addBox(parent, .022, .27, .03, 0, .15, -.12, silver);
        support.rotation.x = -.18;
    }

    function licenseTexture(name, color) {
        const key = `license:${name}:${color}`;
        if (!materials.has(key)) {
            const canvas = document.createElement('canvas');
            canvas.width = 256;
            canvas.height = 320;
            const context = canvas.getContext('2d');
            context.fillStyle = '#f9fbfd';
            context.fillRect(0, 0, 256, 320);
            context.fillStyle = color;
            context.fillRect(0, 0, 256, 66);
            context.fillStyle = '#e9eef4';
            context.fillRect(24, 228, 208, 3);
            context.fillRect(24, 251, 150, 3);
            context.fillRect(24, 274, 183, 3);
            context.fillStyle = color;
            context.font = '600 70px system-ui, sans-serif';
            context.textAlign = 'center';
            context.fillText(name.replace(/[^a-z0-9 ]/gi, '').split(/\s+/).slice(0, 2).map((word) => word[0]).join('').toUpperCase() || 'D', 128, 182);
            const texture = new THREE.CanvasTexture(canvas);
            texture.colorSpace = THREE.SRGBColorSpace;
            textures.set(key, texture);
            materials.set(key, new THREE.MeshBasicMaterial({ map: texture, toneMapped: false }));
        }
        return materials.get(key);
    }

    function addLicense(parent, name, index) {
        const colors = ['#2177a3', '#328b81', '#ad7181', '#577db6', '#b68b50', '#68798e'];
        const color = colors[index % colors.length];
        addBox(parent, .58, .73, .075, 0, .395, 0, surface(color, .2, .46), .018);
        addBox(parent, .51, .62, .005, 0, .41, .041, licenseTexture(name, color));
        addBox(parent, .56, .025, .17, 0, .025, -.03, surface('#d9e3ec', .35, .43), .01);
    }

    function create(item, index, type) {
        const group = new THREE.Group();
        if (type === 'digital') addLicense(group, item.name, index);
        else if (/imac/i.test(item.name) && !/m3/i.test(item.name) && intelImac) {
            const model = intelImac.clone(true);
            model.traverse((object) => { if (object.isMesh) object.geometry = object.geometry.clone(); });
            model.scale.setScalar(/27/.test(item.name) ? 1.22 : 1);
            group.add(model);
        }
        else if (/iphone/i.test(item.name) && iphoneXs) {
            const model = iphoneXs.clone(true);
            model.traverse((object) => { if (object.isMesh) object.geometry = object.geometry.clone(); });
            group.add(model);
        }
        else if (/iphone/i.test(item.name)) addPhone(group);
        else if (/ipad/i.test(item.name)) addTablet(group, /pro/i.test(item.name));
        else if (/monitor hp/i.test(item.name)) addMonitor(group, .87, 'hp');
        else if (/imac/i.test(item.name)) addMonitor(group, /27/.test(item.name) ? 1.03 : /21/.test(item.name) ? .79 : .88, /m3/i.test(item.name) ? 'modern' : 'intel');
        return group;
    }

    function dispose() {
        [intelImac, iphoneXs].forEach((model) => model?.traverse((object) => {
            object.geometry?.dispose();
            if (object.material) (Array.isArray(object.material) ? object.material : [object.material]).forEach((material) => material.dispose());
        }));
        textures.forEach((texture) => texture.dispose());
        materials.forEach((material) => material.dispose());
    }

    return { create, dispose, setOnReady(callback) { onReady = callback; } };
}
