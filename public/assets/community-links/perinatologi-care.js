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


    let webGlReady = false;
    let webGlFailed = false;
    let motionEnabled = !isSaveData && !reducedMotionQuery?.matches;
    let isRunning = false;
    let rafId = null;

    function updateMotionUI() {
        const showCanvas = motionEnabled && webGlReady && !webGlFailed;
        if (heroCanvas) heroCanvas.hidden = !showCanvas;
        if (heroFallback) heroFallback.hidden = showCanvas;
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

    if (!window.THREE || !heroCanvas) {
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

        // Hero Emblem animation
        if (!isDraggingHero) {
            targetRotation.y += 0.006;
            targetRotation.x = Math.sin(elapsed * 0.8) * 0.15;
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

    function startLoop() {
        if (isRunning || !motionEnabled || !webGlReady || document.hidden) return;
        isRunning = true;
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
        heroCanvas.style.touchAction = 'pan-y';
        heroRenderer = new THREE.WebGLRenderer({
            canvas: heroCanvas,
            alpha: true,
            antialias: true,
            powerPreference: 'low-power'
        });
        heroRenderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        heroRenderer.setSize(152, 152, false);

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
        heartMesh = new THREE.Mesh(heartGeo, heartMat);
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
        heroCanvas.hidden = false;
        if (heroFallback) heroFallback.hidden = true;
        updateMotionUI();

        if (motionEnabled && !document.hidden) {
            startLoop();
        }
    } catch (_err) {
        handleContextLost();
    }
})();
