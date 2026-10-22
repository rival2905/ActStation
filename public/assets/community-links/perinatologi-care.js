/**
 * Perinatologi Care Theme 3D & Interactive Motion Controller
 * Vanilla JS using window.THREE r128 (no external imports/storage/CDN).
 * ponytail: single IIFE theme controller. Ceiling: two canvas scenes; upgrade path: shared WebGL context / offscreen canvas worker.
 */
(() => {
    'use strict';

    const heroCanvas = document.getElementById('hero-3d-canvas');
    const bgCanvas = document.getElementById('bg-3d-canvas');
    const toggleBtn = document.getElementById('motion-toggle');
    const motionLabel = document.getElementById('motion-label');
    const heroFallback = document.getElementById('hero-fallback') || document.querySelector('.perina-static');

    // Scope to perinatologi-care theme page only
    if (!heroCanvas && !toggleBtn && !document.body.classList.contains('perinatologi-care')) {
        return;
    }

    // Unhide motion toggle button (parent initially marks hidden)
    if (toggleBtn) {
        toggleBtn.removeAttribute('hidden');
        toggleBtn.hidden = false;
    }

    const reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const isSaveData = Boolean(typeof navigator !== 'undefined' && navigator.connection && navigator.connection.saveData);


    const imageEmblemUrl = heroCanvas?.dataset?.imageEmblemUrl;
    const modelUrl = heroCanvas?.dataset?.modelUrl;
    const emblemSpeed = Math.max(0, Math.min(100, Number(document.body.dataset.emblemSpeed ?? 50))) / 50;
    let modelReady = !modelUrl && !imageEmblemUrl;
    let modelLoading = false;
    let webGlReady = false;
    let webGlFailed = false;
    let motionEnabled = document.body.dataset.motionDefault !== '0' && !isSaveData && !reducedMotionQuery?.matches;
    let isRunning = false;
    let rafId = null;

    function updateMotionUI() {
        const showCanvas = motionEnabled && webGlReady && !webGlFailed;
        if (heroCanvas) heroCanvas.hidden = !(showCanvas && modelReady);
        if (heroFallback) heroFallback.hidden = showCanvas && modelReady;
        if (bgCanvas) bgCanvas.hidden = !showCanvas;
        if (webGlFailed) {
            document.body.dataset.motion = 'static';
        } else {
            document.body.dataset.motion = motionEnabled ? 'on' : 'off';
        }

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-pressed', String(motionEnabled));
            toggleBtn.disabled = webGlFailed || isSaveData || Boolean(reducedMotionQuery?.matches);
        }

        if (motionLabel) {
            if (webGlFailed) {
                motionLabel.textContent = 'Animasi tidak tersedia';
            } else if (isSaveData || reducedMotionQuery?.matches) {
                motionLabel.textContent = 'Animasi dinonaktifkan sesuai perangkat';
            } else {
                motionLabel.textContent = motionEnabled ? 'Matikan animasi' : 'Aktifkan animasi';
            }
        }
    }

    function setMotion(enabled) {
        motionEnabled = Boolean(enabled);
        updateMotionUI();
        if (motionEnabled) {
            if (webGlReady && !document.hidden && !webGlFailed) {
                loadModel();
                startLoop();
            }
        } else {
            stopLoop();
            resetAllCards();
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (webGlFailed || isSaveData || reducedMotionQuery?.matches) {
                updateMotionUI();
                return;
            }
            setMotion(!motionEnabled);
        });
    }

    if (reducedMotionQuery) {
        const onReducedMotionChange = (e) => {
            if (e.matches) {
                setMotion(false);
            } else {
                updateMotionUI();
            }
        };
        if (typeof reducedMotionQuery.addEventListener === 'function') {
            reducedMotionQuery.addEventListener('change', onReducedMotionChange);
        } else if (typeof reducedMotionQuery.addListener === 'function') {
            reducedMotionQuery.addListener(onReducedMotionChange);
        }
    }

    // --- Cards [data-link] hover tilt (pointer: fine, capped 5deg) ---
    const finePointerQuery = window.matchMedia ? window.matchMedia('(pointer: fine)') : null;
    const cards = document.querySelectorAll('[data-link]');

    function resetCard(c) {
        c.style.transform = '';
        c.style.transition = 'transform 0.25s ease-out';
    }

    function resetAllCards() {
        cards.forEach(resetCard);
    }

    if (finePointerQuery && finePointerQuery.matches) {
        cards.forEach((card) => {
            card.addEventListener('pointermove', (e) => {
                if (document.body.dataset.motion !== 'on' || (reducedMotionQuery && reducedMotionQuery.matches)) {
                    card.style.transform = '';
                    return;
                }
                const rect = card.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) return;
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                const rotX = Math.max(-5, Math.min(5, -y * 10));
                const rotY = Math.max(-5, Math.min(5, x * 10));
                card.style.transition = 'transform 0.08s ease-out';
                card.style.transform = `perspective(600px) rotateX(${rotX.toFixed(2)}deg) rotateY(${rotY.toFixed(2)}deg)`;
            });
            card.addEventListener('pointerleave', () => resetCard(card));
            card.addEventListener('blur', () => resetCard(card), true);
        });
    }

    function handleContextLost() {
        webGlReady = false;
        webGlFailed = true;
        stopLoop();
        resetAllCards();
        if (heroCanvas) heroCanvas.hidden = true;
        if (heroFallback) heroFallback.hidden = false;
        if (bgCanvas) bgCanvas.hidden = true;
        motionEnabled = false;
        updateMotionUI();
    }

    // Initial state before THREE check
    updateMotionUI();

    if (!window.THREE || (!heroCanvas && !bgCanvas)) {
        webGlFailed = true;
        motionEnabled = false;
        updateMotionUI();
        return;
    }

    // --- WebGL 3D Setup ---
    let clock = null;
    let heroRenderer = null;
    let heroScene = null;
    let heroCamera = null;
    let emblemGroup = null;
    let heartMesh = null;
    let ring1 = null;
    let ring2 = null;
    let shieldMesh = null;
    const careOrbs = [];

    let isDraggingHero = false;
    let prevPointer = { x: 0, y: 0 };
    const targetRotation = { x: 0.15, y: -0.25 };

    let bgRenderer = null;
    let bgScene = null;
    let bgCamera = null;
    const bgShapes = [];

    const pointerParallax = { x: 0, y: 0 };
    let scrollOffset = 0;

    function renderLoop() {
        if (!isRunning || !motionEnabled || !webGlReady) return;
        rafId = requestAnimationFrame(renderLoop);

        const elapsed = clock.getElapsedTime();

        // Hero scene is optional when page configuration hides emblem.
        if (heroRenderer && modelReady) {
            if (!isDraggingHero) {
                targetRotation.y += 0.006 * emblemSpeed;
                targetRotation.x = Math.sin(elapsed * 0.8 * emblemSpeed) * 0.15;
            }
            emblemGroup.rotation.y += (targetRotation.y - emblemGroup.rotation.y) * 0.08;
            emblemGroup.rotation.x += (targetRotation.x - emblemGroup.rotation.x) * 0.08;

            const pulse = 0.78 + Math.sin(elapsed * 2.6) * 0.045;
            heartMesh.scale.set(pulse, pulse, pulse);

            ring1.rotation.z = elapsed * 0.45;
            ring2.rotation.x = elapsed * 0.35;
            shieldMesh.rotation.y = -elapsed * 0.2;

            for (let i = 0; i < careOrbs.length; i++) {
                const orb = careOrbs[i];
                const u = orb.userData;
                const a = u.angle + elapsed * u.speed;
                orb.position.x = Math.cos(a) * u.radius;
                orb.position.z = Math.sin(a) * u.radius;
                orb.position.y = Math.sin(elapsed * 1.5 + i) * 0.45;
            }

            heroRenderer.render(heroScene, heroCamera);
        }

        // Ambient Background animation
        if (bgRenderer && bgScene && bgCamera) {
            for (let i = 0; i < bgShapes.length; i++) {
                const b = bgShapes[i];
                const u = b.userData;
                b.position.y = u.baseY + Math.sin(elapsed * u.speed + u.phase) * u.ampY + scrollOffset;
                b.position.x = u.baseX + Math.cos(elapsed * (u.speed * 0.7) + u.phase) * 0.45;
                b.position.z = u.baseZ + Math.sin(elapsed * 0.5 + u.phase) * 0.5;
                b.rotation.x += u.rotX;
                b.rotation.y += u.rotY;
            }
            bgCamera.position.x += (pointerParallax.x - bgCamera.position.x) * 0.04;
            bgCamera.position.y += (pointerParallax.y - bgCamera.position.y) * 0.04;
            bgRenderer.render(bgScene, bgCamera);
        }
    }

    async function loadImageEmblem() {
        const url = new URL(imageEmblemUrl, location.href);
        if (url.origin !== location.origin || !window.CommunityImageEmblem) throw new Error('Logo tidak tersedia.');
        const response = await fetch(url.href, {credentials: 'same-origin', redirect: 'error', signal: AbortSignal.timeout(15000)});
        if (!response.ok) throw new Error('Logo tidak dapat dimuat.');
        const blob = await response.blob();
        if (blob.size > 2097152 || !['image/webp', 'image/png', 'image/jpeg'].includes(blob.type)) throw new Error('Gambar tidak valid.');
        const image = await createImageBitmap(blob);
        try {
            if (image.width > 2048 || image.height > 2048) throw new Error('Gambar terlalu besar.');
            const ratio = Math.min(1, 126 / Math.max(image.width, image.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.width * ratio)) + 2;
            canvas.height = Math.max(1, Math.round(image.height * ratio)) + 2;
            const ctx = canvas.getContext('2d', {willReadFrequently: true});
            ctx.drawImage(image, 1, 1, canvas.width - 2, canvas.height - 2);
            const rgba = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            const alpha = new Uint8Array(canvas.width * canvas.height);
            let transparent = false;
            for (let y = 0; y < canvas.height; y++) for (let x = 0; x < canvas.width; x++) {
                const index = y * canvas.width + x;
                alpha[index] = rgba[index * 4 + 3];
                if (x > 0 && y > 0 && x < canvas.width - 1 && y < canvas.height - 1 && alpha[index] < 128) transparent = true;
            }
            if (!transparent) throw new Error('Gunakan PNG/WebP dengan latar transparan.');
            const contours = window.CommunityImageEmblem.trace(alpha, canvas.width, canvas.height);
            const shapes = contours.map(contour => {
                const shape = new THREE.Shape(contour.outer.map(([x, y]) => new THREE.Vector2(x, y)));
                contour.holes.forEach(hole => shape.holes.push(new THREE.Path(hole.map(([x, y]) => new THREE.Vector2(x, y)))));
                return shape;
            });
            const depth = Math.max(5, Math.min(60, Number(document.body.dataset.emblemDepth ?? 20))) / 100;
            const geometry = new THREE.ExtrudeGeometry(shapes, {depth, bevelEnabled: false, steps: 1, curveSegments: 1});
            const positions = geometry.attributes.position;
            const uv = geometry.attributes.uv;
            for (let i = 0; i < positions.count; i++) uv.setXY(i, (positions.getX(i) + 1) / 2, (positions.getY(i) + 1) / 2);
            const textureCanvas = document.createElement('canvas');
            const detailScale = Math.min(8, Math.max(1, Math.max(image.width, image.height) / 126));
            textureCanvas.width = Math.round(canvas.width * detailScale);
            textureCanvas.height = Math.round(canvas.height * detailScale);
            textureCanvas.getContext('2d').drawImage(image, detailScale, detailScale, textureCanvas.width - 2 * detailScale, textureCanvas.height - 2 * detailScale);
            const texture = new THREE.CanvasTexture(textureCanvas);
            texture.encoding = THREE.sRGBEncoding;
            const front = new THREE.MeshBasicMaterial({map: texture, transparent: true, alphaTest: 0.5, side: THREE.DoubleSide});
            const side = new THREE.MeshStandardMaterial({color: document.body.dataset.emblemColor || '#f43f5e', roughness: 0.45});
            const mesh = new THREE.Mesh(geometry, [front, side]);
            mesh.position.z = -depth / 2;
            const max = Math.max(canvas.width, canvas.height);
            mesh.scale.set(canvas.width / max * 1.15, canvas.height / max * 1.15, 1);
            replaceEmblem(mesh);
        } finally {
            image.close();
        }
    }

    function replaceEmblem(model) {
        while (emblemGroup.children.length) {
            const child = emblemGroup.children[0];
            emblemGroup.remove(child);
            child.traverse(node => {
                node.geometry?.dispose();
                const materials = Array.isArray(node.material) ? node.material : [node.material];
                materials.forEach(material => material?.dispose());
            });
        }
        emblemGroup.add(model);
        modelReady = true;
        updateMotionUI();
    }

    async function loadModel() {
        if ((!modelUrl && !imageEmblemUrl) || modelLoading || modelReady || !motionEnabled || !webGlReady) return;
        modelLoading = true;
        try {
            if (imageEmblemUrl) { await loadImageEmblem(); return; }
            const url = new URL(modelUrl, location.href);
            if (url.origin !== location.origin || typeof THREE.GLTFLoader !== 'function') throw new Error('Model unavailable');
            const response = await fetch(url.href, {credentials: 'same-origin', redirect: 'error', signal: AbortSignal.timeout(15000)});
            if (!response.ok) throw new Error('Model unavailable');
            const bytes = await response.arrayBuffer();
            if (bytes.byteLength > 5242880) throw new Error('Model too large');
            const manager = new THREE.LoadingManager();
            manager.setURLModifier(resource => {
                if (!resource.startsWith('blob:')) throw new Error('External model resources forbidden');
                return resource;
            });
            const gltf = await new Promise((resolve, reject) => new THREE.GLTFLoader(manager).parse(bytes, '', resolve, reject));
            const box = new THREE.Box3().setFromObject(gltf.scene);
            const size = box.getSize(new THREE.Vector3());
            const extent = Math.max(size.x, size.y, size.z);
            if (!Number.isFinite(extent) || extent <= 0) throw new Error('Empty model');
            const center = box.getCenter(new THREE.Vector3());
            const model = new THREE.Group();
            gltf.scene.position.sub(center);
            model.add(gltf.scene);
            model.scale.setScalar(2.4 / extent);
            replaceEmblem(model);
        } catch (_) {
            // ponytail: static fallback on failed GLB; retry after page reload.
            if (motionLabel) motionLabel.textContent = imageEmblemUrl ? 'Siluet tidak dapat dibuat. Gunakan logo transparan sederhana; gambar statis tetap tampil.' : 'Model 3D gagal dimuat; logo statis tetap tersedia.';
        }
    }

    function startLoop() {
        if (isRunning || !motionEnabled || !webGlReady || document.hidden) return;
        isRunning = true;
        loadModel();
        if (clock) clock.start();
        rafId = requestAnimationFrame(renderLoop);
    }

    function stopLoop() {
        isRunning = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopLoop();
        } else if (motionEnabled && webGlReady) {
            startLoop();
        }
    });

    try {
        clock = new THREE.Clock();

        // 1. Setup Hero 3D Canvas
        if (heroCanvas) {
            heroCanvas.style.touchAction = 'pan-y';

            heroRenderer = new THREE.WebGLRenderer({
                canvas: heroCanvas,
                alpha: true,
                antialias: true,
                powerPreference: 'low-power'
            });
            heroRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            const renderSize = Math.max(152, Math.min(240, Number(document.body.dataset.emblemSize) || 88));
            heroRenderer.setSize(renderSize, renderSize, false);

            heroCanvas.addEventListener('webglcontextlost', (e) => {
                e.preventDefault();
                handleContextLost();
            });

            heroScene = new THREE.Scene();
            heroCamera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
            heroCamera.position.set(0, 0, 6.2);

            const heroAmbient = new THREE.AmbientLight(0xffffff, 0.85);
            heroScene.add(heroAmbient);

            const dirLight = new THREE.DirectionalLight(0xfff1f2, 1.25);
            dirLight.position.set(4, 5, 6);
            heroScene.add(dirLight);

            const pointLightTeal = new THREE.PointLight(0x14b8a6, 1.6, 12);
            pointLightTeal.position.set(-4, -3, 3);
            heroScene.add(pointLightTeal);

            emblemGroup = new THREE.Group();
            heroScene.add(emblemGroup);

            // Procedural 3D Heart Shape
            const heartShape = new THREE.Shape();
            const hx = 0;
            const hy = 0;
            heartShape.moveTo(hx, hy + 0.35);
            heartShape.bezierCurveTo(hx, hy + 0.35, hx - 0.1, hy, hx - 0.5, hy);
            heartShape.bezierCurveTo(hx - 1.05, hy, hx - 1.05, hy + 0.7, hx - 1.05, hy + 0.7);
            heartShape.bezierCurveTo(hx - 1.05, hy + 1.1, hx - 0.65, hy + 1.54, hx, hy + 1.9);
            heartShape.bezierCurveTo(hx + 0.65, hy + 1.54, hx + 1.05, hy + 1.1, hx + 1.05, hy + 0.7);
            heartShape.bezierCurveTo(hx + 1.05, hy + 0.7, hx + 1.05, hy, hx + 0.5, hy);
            heartShape.bezierCurveTo(hx + 0.2, hy, hx, hy + 0.35, hx, hy + 0.35);

            const heartGeo = new THREE.ExtrudeGeometry(heartShape, {
                depth: 0.35,
                bevelEnabled: true,
                bevelSegments: 5,
                steps: 1,
                bevelSize: 0.18,
                bevelThickness: 0.18
            });
            heartGeo.center();
            heartGeo.rotateZ(Math.PI);

            const heartMat = new THREE.MeshPhysicalMaterial({
                color: 0xf43f5e,
                emissive: 0xbe123c,
                emissiveIntensity: 0.22,
                roughness: 0.25,
                metalness: 0.08,
                clearcoat: 0.85,
                clearcoatRoughness: 0.15
            });
            const shape = document.body.dataset.emblemShape;
            let geometry = heartGeo;
            if (shape === 'sphere' || shape === 'diamond') {
                heartGeo.dispose();
                geometry = shape === 'sphere' ? new THREE.SphereGeometry(1, 32, 24) : new THREE.OctahedronGeometry(1.3);
            }
            const customColor = document.body.dataset.emblemColor;
            if (/^#[0-9a-fA-F]{6}$/.test(customColor ?? '') && customColor.toLowerCase() !== '#f43f5e') {
                heartMat.color.set(customColor);
                heartMat.emissive.set(customColor);
            }
            heartMesh = new THREE.Mesh(geometry, heartMat);
            heartMesh.scale.set(0.78, 0.78, 0.78);
            emblemGroup.add(heartMesh);

            // Translucent Teal Protective Rings
            const ringMatTeal = new THREE.MeshPhysicalMaterial({
                color: 0x14b8a6,
                emissive: 0x0d9488,
                emissiveIntensity: 0.18,
                roughness: 0.2,
                metalness: 0.1,
                transparent: true,
                opacity: 0.82,
                clearcoat: 1.0
            });

            ring1 = new THREE.Mesh(new THREE.TorusGeometry(1.58, 0.075, 20, 64), ringMatTeal);
            ring1.rotation.x = Math.PI / 3;
            emblemGroup.add(ring1);

            const ring2Mat = ringMatTeal.clone();
            ring2Mat.color.setHex(0x2dd4bf);
            ring2Mat.opacity = 0.55;
            ring2 = new THREE.Mesh(new THREE.TorusGeometry(1.88, 0.055, 16, 64), ring2Mat);
            ring2.rotation.y = Math.PI / 3;
            emblemGroup.add(ring2);

            // Glassy Wireframe Shield
            shieldMesh = new THREE.Mesh(
                new THREE.IcosahedronGeometry(2.12, 1),
                new THREE.MeshBasicMaterial({
                    color: 0x0d9488,
                    wireframe: true,
                    transparent: true,
                    opacity: 0.16
                })
            );
            emblemGroup.add(shieldMesh);

            // Orbiting Care Pearls
            const orbsGroup = new THREE.Group();
            emblemGroup.add(orbsGroup);
            const orbGeo = new THREE.SphereGeometry(0.13, 16, 16);
            const orbColors = [0xfbbf24, 0xfda4af, 0x5eead4];

            for (let i = 0; i < 3; i++) {
                const orbMat = new THREE.MeshPhysicalMaterial({
                    color: orbColors[i],
                    roughness: 0.15,
                    clearcoat: 1.0
                });
                const orb = new THREE.Mesh(orbGeo, orbMat);
                orb.userData = {
                    angle: (i / 3) * Math.PI * 2,
                    radius: 1.72,
                    speed: 0.9 + i * 0.2
                };
                orbsGroup.add(orb);
                careOrbs.push(orb);
            }

            // Pointer drag interaction (preserves vertical touch-action pan-y scroll)
            heroCanvas.addEventListener('pointerdown', (e) => {
                isDraggingHero = true;
                prevPointer = { x: e.clientX, y: e.clientY };
            });

            window.addEventListener('pointermove', (e) => {
                if (!isDraggingHero) return;
                const dx = e.clientX - prevPointer.x;
                const dy = e.clientY - prevPointer.y;
                targetRotation.y += dx * 0.02;
                targetRotation.x = Math.max(-1.2, Math.min(1.2, targetRotation.x + dy * 0.02));
                prevPointer = { x: e.clientX, y: e.clientY };
            });

            const stopDrag = () => {
                isDraggingHero = false;
            };
            window.addEventListener('pointerup', stopDrag);
            window.addEventListener('pointercancel', stopDrag);
        }

        // 2. Setup Ambient Background Canvas
        if (bgCanvas) {
            bgCanvas.addEventListener('webglcontextlost', (e) => {
                e.preventDefault();
                handleContextLost();
            });

            bgScene = new THREE.Scene();
            bgCamera = new THREE.PerspectiveCamera(50, window.innerWidth / Math.max(1, window.innerHeight), 0.1, 100);
            bgCamera.position.z = 18;

            bgRenderer = new THREE.WebGLRenderer({
                canvas: bgCanvas,
                alpha: true,
                antialias: false,
                powerPreference: 'low-power'
            });
            bgRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
            bgRenderer.setSize(window.innerWidth, window.innerHeight, false);

            const bgAmbient = new THREE.AmbientLight(0xffffff, 0.9);
            bgScene.add(bgAmbient);

            const pLight1 = new THREE.PointLight(0x14b8a6, 1.2, 40);
            pLight1.position.set(10, 10, 10);
            bgScene.add(pLight1);

            const pLight2 = new THREE.PointLight(0xf43f5e, 1.0, 40);
            pLight2.position.set(-10, -10, 10);
            bgScene.add(pLight2);

            const palette = [0x5eead4, 0x99f6e4, 0xfda4af, 0xfecdd3, 0x14b8a6, 0xf43f5e];
            const geos = [
                new THREE.IcosahedronGeometry(1, 0),
                new THREE.OctahedronGeometry(1, 0),
                new THREE.DodecahedronGeometry(1, 0)
            ];

            for (let i = 0; i < 14; i++) {
                const mat = new THREE.MeshStandardMaterial({
                    color: palette[i % palette.length],
                    transparent: true,
                    opacity: 0.22,
                    roughness: 0.3,
                    flatShading: true
                });
                const mesh = new THREE.Mesh(geos[i % geos.length], mat);
                const scale = 0.5 + Math.random() * 0.9;
                mesh.scale.set(scale, scale, scale);

                const initX = (Math.random() - 0.5) * 22;
                const initY = (Math.random() - 0.5) * 20;
                const initZ = (Math.random() - 0.5) * 8 - 2;
                mesh.position.set(initX, initY, initZ);

                mesh.userData = {
                    baseX: initX,
                    baseY: initY,
                    baseZ: initZ,
                    phase: Math.random() * Math.PI * 2,
                    speed: 0.35 + Math.random() * 0.45,
                    ampY: 0.5 + Math.random() * 0.7,
                    rotX: (Math.random() - 0.5) * 0.015,
                    rotY: (Math.random() - 0.5) * 0.015
                };
                bgScene.add(mesh);
                bgShapes.push(mesh);
            }

            window.addEventListener('resize', () => {
                if (!bgCamera || !bgRenderer) return;
                bgCamera.aspect = window.innerWidth / Math.max(1, window.innerHeight);
                bgCamera.updateProjectionMatrix();
                bgRenderer.setSize(window.innerWidth, window.innerHeight, false);
            });
        }

        window.addEventListener('mousemove', (e) => {
            pointerParallax.x = (e.clientX / window.innerWidth - 0.5) * 1.5;
            pointerParallax.y = -(e.clientY / Math.max(1, window.innerHeight) - 0.5) * 1.5;
        }, { passive: true });

        window.addEventListener('scroll', () => {
            scrollOffset = window.scrollY * 0.003;
        }, { passive: true });

        // Init successful: reveal canvas, hide static fallback
        webGlReady = true;
        if (heroCanvas) heroCanvas.hidden = false;
        if (heroFallback) heroFallback.hidden = true;
        updateMotionUI();

        if (motionEnabled && !document.hidden) {
            loadModel();
            startLoop();
        }
    } catch (_err) {
        handleContextLost();
    }
})();
