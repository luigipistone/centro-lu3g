import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

export function createDeviceModels() {
    const materials = new Map();
    const textures = new Map();
    const imported = new Map();
    const loading = new Map();
    const logoTextures = new Map();
    const loader = new GLTFLoader();
    const assets = {
        'imac-intel': { file: 'imac-intel.glb', width: .79, rotation: Math.PI },
        'imac-24': { file: 'imac-24.glb', width: .89, rotation: 0 },
        iphone: { file: 'iphone-xs.glb', width: .3, rotation: 0 },
        'ipad-pro': { file: 'ipad-pro.glb', width: .51, rotation: 0 },
        'ipad-air': { file: 'ipad-air.glb', width: .49, rotation: 0 },
        monitor: { file: 'monitor.glb', width: .87, rotation: -Math.PI / 2 },
    };

    function loadBuiltIn(key) {
        if (imported.has(key)) return Promise.resolve();
        if (loading.has(key)) return loading.get(key);
        const asset = assets[key];
        const promise = new Promise((resolve) => {
            loader.load(`/models/inventory/${asset.file}`, ({ scene }) => {
            const oriented = new THREE.Group();
            scene.rotation.y = asset.rotation;
            oriented.add(scene);
            oriented.updateMatrixWorld(true);
            const bounds = new THREE.Box3().setFromObject(oriented);
            const size = bounds.getSize(new THREE.Vector3());
            if (!size.x || !Number.isFinite(size.x)) { resolve(); return; }
            oriented.scale.setScalar(asset.width / size.x);
            oriented.updateMatrixWorld(true);
            const scaled = new THREE.Box3().setFromObject(oriented);
            const center = scaled.getCenter(new THREE.Vector3());
            scene.position.set(-center.x / oriented.scale.x, -scaled.min.y / oriented.scale.y, -center.z / oriented.scale.z);
            scene.traverse((object) => {
                if (!object.isMesh) return;
                const surface = object.material;
                if (surface?.map && /screen|retina/i.test(object.name)) {
                    object.material = new THREE.MeshBasicMaterial({ map: surface.map, side: THREE.DoubleSide, toneMapped: false });
                    if (key === 'imac-24') {
                        object.material.side = THREE.FrontSide;
                        object.material.transparent = true;
                        object.material.blending = THREE.NoBlending;
                        object.material.depthTest = false;
                        object.material.depthWrite = false;
                        object.renderOrder = 999;
                    }
                } else if (surface?.map && key.startsWith('ipad')) {
                    object.material = surface.clone();
                    object.material.emissive = new THREE.Color('#ffffff');
                    object.material.emissiveMap = surface.map;
                    object.material.emissiveIntensity = .2;
                }
            });
            imported.set(key, oriented);
            resolve();
            }, undefined, () => resolve());
        });
        loading.set(key, promise);
        return promise;
    }

    function loadCustom(url) {
        const key = `custom:${url}`;
        if (imported.has(key)) return Promise.resolve();
        if (loading.has(key)) return loading.get(key);
        const promise = new Promise((resolve) => {
            loader.load(url, ({ scene }) => {
                const bounds = new THREE.Box3().setFromObject(scene);
                const size = bounds.getSize(new THREE.Vector3());
                if (size.x > 0 && size.y > 0 && Number.isFinite(size.x)) {
                    scene.scale.setScalar(Math.min(.9 / size.x, .82 / size.y));
                    scene.updateMatrixWorld(true);
                    const fitted = new THREE.Box3().setFromObject(scene);
                    const center = fitted.getCenter(new THREE.Vector3());
                    scene.position.add(new THREE.Vector3(-center.x, -fitted.min.y, -center.z));
                    imported.set(key, scene);
                }
                resolve();
            }, undefined, () => resolve());
        });
        loading.set(key, promise);
        return promise;
    }

    async function loadLogo(url) {
        if (logoTextures.has(url)) return;
        try {
            const texture = await new THREE.TextureLoader().loadAsync(url);
            texture.colorSpace = THREE.SRGBColorSpace;
            logoTextures.set(url, texture);
        } catch {
            logoTextures.set(url, null);
        }
    }

    function prepare(items, type) {
        if (type === 'digital') return Promise.all(items.filter((item) => item.logo_url).map((item) => loadLogo(item.logo_url)));
        const keys = new Set();
        const jobs = [];
        for (const item of items) {
            if (item.model_url) jobs.push(loadCustom(item.model_url));
            else if (/iphone/i.test(item.name)) keys.add('iphone');
            else if (/ipad pro/i.test(item.name)) keys.add('ipad-pro');
            else if (/ipad air/i.test(item.name)) keys.add('ipad-air');
            else if (/monitor hp/i.test(item.name)) keys.add('monitor');
            else if (/imac/i.test(item.name)) keys.add(/m3|24/i.test(item.name) ? 'imac-24' : 'imac-intel');
        }
        keys.forEach((key) => jobs.push(loadBuiltIn(key)));
        return Promise.all(jobs);
    }

    function addImported(parent, key, scale = 1) {
        const source = imported.get(key);
        if (!source) return false;
        const model = source.clone(true);
        model.scale.multiplyScalar(scale);
        model.traverse((object) => {
            if (object.isMesh) object.geometry = object.geometry.clone();
        });
        parent.add(model);
        return true;
    }

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
        const height = width * (modern ? .64 : .59);
        const bottom = modern ? .27 : .29;
        const casing = hp ? surface('#30353c', .32, .43) : modern ? surface('#9fc4cf', .67, .31) : silver;
        const bezel = modern ? surface('#e7f3f6', .22, .45) : surface('#0c1117', .24, .3);
        const backDepth = modern ? .045 : hp ? .055 : .14;
        const panelZ = -.08;
        addBox(parent, width, height, backDepth, 0, bottom + height / 2, panelZ, casing, modern ? .016 : .02);
        const faceZ = panelZ + backDepth / 2 + .006;
        addBox(parent, width * .992, height * .987, .008, 0, bottom + height / 2, faceZ, bezel, .009);
        const chin = modern ? .145 : hp ? .055 : .135;
        const inset = modern ? .035 : hp ? .025 : .045;
        const displayHeight = height * (1 - chin - inset * 2);
        const displayY = bottom + height * chin + height * inset + displayHeight / 2;
        const display = addBox(parent, width * (1 - inset * 2), displayHeight, .003, 0, displayY, faceZ + .007, screen(hp ? 'hp' : modern ? 'modern' : 'intel'), .002);
        display.receiveShadow = false;
        addDot(parent, .0035, 0, bottom + height * .979, faceZ + .012, lens);
        if (modern) {
            addDot(parent, .009, 0, bottom + height * .069, faceZ + .012, surface('#9faeb5', .45, .35));
        } else if (hp) {
            addDot(parent, .009, 0, bottom + height * .026, faceZ + .012, silverLight);
        } else {
            // The black glass and full-width silver chin distinguish the Intel iMacs.
            addBox(parent, width * .988, height * chin * .89, .005, 0, bottom + height * chin * .5, faceZ + .007, silver, .004);
            addDot(parent, .009, 0, bottom + height * chin * .52, faceZ + .014, surface('#8b969d', .4, .4));
            const back = new THREE.Mesh(new THREE.SphereGeometry(1, 24, 16), casing);
            back.scale.set(width * .44, height * .42, .085);
            back.position.set(0, bottom + height / 2, panelZ - backDepth / 2);
            parent.add(back);
        }
        const stand = hp ? graphite : modern ? surface('#a8cbd5', .67, .3) : silver;
        const neck = addBox(parent, width * (hp ? .075 : .09), .24, .045, 0, .17, -.18, stand, .008);
        neck.rotation.x = -.14;
        const foot = addBox(parent, width * (hp ? .48 : .43), .018, hp ? .29 : .27, 0, .019, -.12, stand, .009);
        foot.rotation.x = -.025;
    }

    function addTablet(parent, pro) {
        const width = pro ? .53 : .49;
        const height = pro ? .76 : .71;
        const body = new THREE.Group();
        body.rotation.x = -.13;
        body.position.y = .045;
        parent.add(body);
        const chassis = pro ? graphite : surface('#afbbca', .78, .29);
        addBox(body, width, height, pro ? .04 : .035, 0, height / 2, 0, chassis, .016);
        addBox(body, width * .986, height * .991, .005, 0, height / 2, .022, black, .012);
        addBox(body, width * .92, height * .948, .003, 0, height / 2, .027, screen(pro ? 'tablet-pro' : 'tablet-air'), .004);
        addDot(body, .0035, 0, height * .982, .03, lens);
        addBox(parent, width * .5, .02, .31, 0, .02, -.17, surface('#98a4ae', .6, .38), .009);
        const support = addBox(parent, .021, height * .59, .04, 0, height * .3, -.19, surface('#98a4ae', .6, .38));
        support.rotation.x = -.22;
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
        const frame = surface('#c4b7a7', .84, .23);
        addBox(parent, .306, height, .045, 0, height / 2 + .018, 0, frame, .028);
        addBox(parent, .294, height * .986, .006, 0, height / 2 + .018, .025, black, .021);
        addBox(parent, .276, height * .963, .002, 0, height / 2 + .018, .03, screen('phone'), .015);
        addBox(parent, .135, .028, .003, 0, height * .973, .034, black, .009);
        addDot(parent, .004, .036, height * .971, .038, lens);
        addBox(parent, .008, .082, .014, -.156, height * .68, 0, frame, .003);
        addBox(parent, .008, .044, .014, .156, height * .71, 0, frame, .003);
        addBox(parent, .275, height * .965, .003, 0, height / 2 + .018, -.025, surface('#d9c8b7', .64, .32), .019);
        addBox(parent, .062, .085, .009, -.069, height * .78, -.032, frame, .013);
        for (const [x, y] of [[-.082, .802], [-.052, .802]]) addDot(parent, .011, x, height * y, -.038, lens);
    }

    function licenseTexture(item, color) {
        const key = `license:${item.id}:${color}:${item.logo_url || ''}`;
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
            const logo = logoTextures.get(item.logo_url);
            if (logo?.image) {
                const image = logo.image;
                const ratio = Math.min(140 / image.width, 140 / image.height);
                const width = image.width * ratio;
                const height = image.height * ratio;
                context.drawImage(image, (256 - width) / 2, 95 + (140 - height) / 2, width, height);
            } else {
                context.strokeStyle = color;
                context.lineWidth = 10;
                context.beginPath();
                context.arc(128, 165, 55, 0, Math.PI * 2);
                context.moveTo(73, 165);
                context.lineTo(183, 165);
                context.moveTo(128, 110);
                context.lineTo(128, 220);
                context.stroke();
            }
            const texture = new THREE.CanvasTexture(canvas);
            texture.colorSpace = THREE.SRGBColorSpace;
            textures.set(key, texture);
            materials.set(key, new THREE.MeshBasicMaterial({ map: texture, toneMapped: false }));
        }
        return materials.get(key);
    }

    function addLicense(parent, item, index) {
        const colors = ['#2177a3', '#328b81', '#ad7181', '#577db6', '#b68b50', '#68798e'];
        const color = colors[index % colors.length];
        addBox(parent, .58, .73, .075, 0, .395, 0, surface(color, .2, .46), .018);
        addBox(parent, .51, .62, .005, 0, .41, .041, licenseTexture(item, color));
        addBox(parent, .56, .025, .17, 0, .025, -.03, surface('#d9e3ec', .35, .43), .01);
    }

    function create(item, index, type) {
        const group = new THREE.Group();
        if (type === 'digital') addLicense(group, item, index);
        else if (item.model_url) {
            const key = `custom:${item.model_url}`;
            if (!addImported(group, key)) addBox(group, .55, .55, .45, 0, .29, 0, graphite, .02);
        }
        else if (/iphone/i.test(item.name)) {
            if (!addImported(group, 'iphone')) addPhone(group);
        }
        else if (/ipad pro/i.test(item.name)) {
            if (!addImported(group, 'ipad-pro')) addTablet(group, true);
        }
        else if (/ipad air/i.test(item.name)) {
            if (!addImported(group, 'ipad-air')) addTablet(group, false);
        }
        else if (/monitor hp/i.test(item.name)) {
            if (!addImported(group, 'monitor')) addMonitor(group, .87, 'hp');
        }
        else if (/imac/i.test(item.name)) {
            const modern = /m3|24/i.test(item.name);
            const key = modern ? 'imac-24' : 'imac-intel';
            if (!addImported(group, key, /27/.test(item.name) ? 1.22 : 1)) addMonitor(group, /27/.test(item.name) ? 1.03 : /21/.test(item.name) ? .79 : .88, modern ? 'modern' : 'intel');
        }
        else addBox(group, .55, .55, .45, 0, .29, 0, graphite, .02);
        return group;
    }

    function dispose() {
        imported.forEach((model) => model.traverse((object) => {
            object.geometry?.dispose();
            if (object.material) (Array.isArray(object.material) ? object.material : [object.material]).forEach((material) => material.dispose());
        }));
        textures.forEach((texture) => texture.dispose());
        logoTextures.forEach((texture) => texture?.dispose());
        materials.forEach((material) => material.dispose());
    }

    return { create, prepare, dispose };
}
