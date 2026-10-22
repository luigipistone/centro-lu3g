import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

export function createDeviceModels() {
    const materials = new Map();
    const textures = new Map();

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
        const panelHeight = width * (modern ? .63 : .61);
        const bottom = .27;
        const bodyColor = hp ? black : modern ? surface('#9dbdd2', .67, .33) : silver;
        const faceColor = hp ? black : modern ? silverLight : surface('#171c23', .3, .4);
        const body = addBox(parent, width, panelHeight, modern ? .055 : .09, 0, bottom + panelHeight / 2, -.08, bodyColor, .025);
        body.rotation.x = modern ? -.015 : -.035;
        const face = addBox(parent, width * .99, panelHeight * .985, .013, 0, bottom + panelHeight / 2, -.035, faceColor, .006);
        face.receiveShadow = false;
        const screenWidth = width * (hp ? .92 : modern ? .91 : .89);
        const screenHeight = panelHeight * (modern ? .73 : .77);
        const screenY = bottom + panelHeight * (modern ? .585 : .58);
        const display = addBox(parent, screenWidth, screenHeight, .006, 0, screenY, -.025, screen(hp ? 'hp' : modern ? 'modern' : 'intel'));
        display.receiveShadow = false;
        addDot(parent, .007, 0, bottom + panelHeight * .965, -.018, hp ? graphite : lens);
        if (!hp) {
            const chinColor = modern ? silverLight : silver;
            const chinY = bottom + panelHeight * .085;
            const chin = addBox(parent, width * .99, panelHeight * .16, .018, 0, chinY, -.018, chinColor);
            chin.receiveShadow = false;
            addDot(parent, .012, 0, chinY, -.005, surface('#8996a1', .35, .4));
        } else {
            addBox(parent, width * .995, panelHeight * .08, .018, 0, bottom + panelHeight * .045, -.018, black);
            addDot(parent, .012, 0, bottom + panelHeight * .045, -.006, silverLight);
        }
        const neck = addBox(parent, width * .075, .23, .07, 0, .155, -.17, hp ? graphite : silver, .015);
        neck.rotation.x = -.16;
        addBox(parent, width * .36, .025, .32, 0, .026, -.1, hp ? graphite : silver, .012);
        if (!modern && !hp) {
            const back = new THREE.Mesh(new THREE.SphereGeometry(1, 16, 10), silver);
            back.scale.set(width * .43, panelHeight * .37, .045);
            back.position.set(0, bottom + panelHeight / 2, -.145);
            back.castShadow = true;
            parent.add(back);
        }
    }

    function addTablet(parent, pro) {
        const width = pro ? .53 : .49;
        const height = pro ? .76 : .71;
        const body = new THREE.Group();
        body.rotation.x = -.1;
        body.position.y = .08;
        parent.add(body);
        addBox(body, width, height, .045, 0, height / 2, 0, pro ? graphite : silver, .035);
        addBox(body, width * .975, height * .98, .009, 0, height / 2, .028, black, .008);
        addBox(body, width * .91, height * .93, .004, 0, height / 2, .035, screen(pro ? 'tablet-pro' : 'tablet-air'));
        addDot(body, .006, 0, height * .985, .039, lens);
        addBox(body, .035, .1, .05, -width * .4, height * .84, -.03, graphite, .008);
        addBox(parent, width * .5, .025, .33, 0, .024, -.15, silver, .012);
        const support = addBox(parent, .025, height * .66, .05, 0, height * .33, -.17, silver);
        support.rotation.x = -.21;
        addBox(body, .065, .065, .012, -width * .32, height * .85, -.03, black, .01);
        if (pro) addDot(body, .015, -width * .32, height * .85, -.038, lens);
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
        else if (/iphone/i.test(item.name)) addPhone(group);
        else if (/ipad/i.test(item.name)) addTablet(group, /pro/i.test(item.name));
        else if (/monitor hp/i.test(item.name)) addMonitor(group, .87, 'hp');
        else if (/imac/i.test(item.name)) addMonitor(group, /27/.test(item.name) ? 1.03 : /21/.test(item.name) ? .79 : .88, /m3/i.test(item.name) ? 'modern' : 'intel');
        return group;
    }

    function dispose() {
        textures.forEach((texture) => texture.dispose());
        materials.forEach((material) => material.dispose());
    }

    return { create, dispose };
}
