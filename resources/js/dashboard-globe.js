import * as THREE from 'three';
import { feature } from 'topojson-client';
import worldAtlas from 'world-atlas/countries-110m.json';

const stage = document.getElementById('hero-globe');

if (stage) {
    const hint = document.getElementById('hero-globe-hint');
    const popover = document.getElementById('hero-globe-popover');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const textureWidth = 2048;
    const textureHeight = 1024;
    const earthRadius = 1.2;
    const shipmentLimit = 100;
    const routeLimit = 36;
    const statusColors = {
        in_transit: 0x31aaff,
        delivered: 0x34d89e,
        delayed: 0xffad56,
        exception: 0xff657a,
        other: 0xa5bedb,
    };
    const fallbackImage = stage.querySelector('.hero-globe-fallback');

    const validLocation = (location) => location
        && Number.isFinite(location.latitude)
        && Number.isFinite(location.longitude)
        && location.latitude >= -90
        && location.latitude <= 90
        && location.longitude >= -180
        && location.longitude <= 180;

    const formatDate = (value) => {
        if (!value) return 'Not recorded';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Not recorded';

        return `${new Intl.DateTimeFormat('en-GB', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'UTC',
        }).format(date)} UTC`;
    };

    const createEarthTexture = () => {
        const canvas = document.createElement('canvas');
        canvas.width = textureWidth;
        canvas.height = textureHeight;
        const context = canvas.getContext('2d');
        if (!context) throw new Error('Could not create the globe texture canvas.');

        const ocean = context.createLinearGradient(0, 0, textureWidth, textureHeight);
        ocean.addColorStop(0, '#0c2e50');
        ocean.addColorStop(.48, '#061b34');
        ocean.addColorStop(1, '#031126');
        context.fillStyle = ocean;
        context.fillRect(0, 0, textureWidth, textureHeight);

        const glow = context.createRadialGradient(
            textureWidth * .42, textureHeight * .42, textureWidth * .04,
            textureWidth * .42, textureHeight * .42, textureWidth * .56,
        );
        glow.addColorStop(0, 'rgba(32, 132, 206, .22)');
        glow.addColorStop(1, 'rgba(5, 20, 42, 0)');
        context.fillStyle = glow;
        context.fillRect(0, 0, textureWidth, textureHeight);

        const project = ([longitude, latitude]) => [
            (longitude + 180) / 360 * textureWidth,
            (90 - latitude) / 180 * textureHeight,
        ];
        const drawRing = (ring, fill) => {
            if (ring.length < 3) return;
            context.beginPath();
            let previousLongitude = null;
            ring.forEach((coordinate, index) => {
                const [x, y] = project(coordinate);
                if (index === 0 || Math.abs(coordinate[0] - previousLongitude) > 180) {
                    if (index > 0) context.closePath();
                    context.moveTo(x, y);
                } else {
                    context.lineTo(x, y);
                }
                previousLongitude = coordinate[0];
            });
            context.closePath();
            if (fill) {
                context.fillStyle = 'rgba(35, 111, 163, .9)';
                context.fill();
            }
            context.strokeStyle = 'rgba(121, 204, 244, .27)';
            context.lineWidth = 1.2;
            context.stroke();
        };
        const countries = feature(worldAtlas, worldAtlas.objects.countries).features;
        countries.forEach(({ geometry }) => {
            if (!geometry) return;
            const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
            polygons.forEach((polygon) => polygon.forEach((ring, index) => drawRing(ring, index === 0)));
        });

        context.beginPath();
        for (let longitude = -180; longitude <= 180; longitude += 15) {
            for (let latitude = -90; latitude <= 90; latitude += 2) {
                const [x, y] = project([longitude, latitude]);
                if (latitude === -90) context.moveTo(x, y);
                else context.lineTo(x, y);
            }
        }
        for (let latitude = -75; latitude <= 75; latitude += 15) {
            for (let longitude = -180; longitude <= 180; longitude += 2) {
                const [x, y] = project([longitude, latitude]);
                if (longitude === -180) context.moveTo(x, y);
                else context.lineTo(x, y);
            }
        }
        context.strokeStyle = 'rgba(103, 189, 241, .14)';
        context.lineWidth = 1;
        context.stroke();

        return canvas;
    };

    const buildScene = async () => {
        let renderer;
        try {
            renderer = new THREE.WebGLRenderer({
                alpha: true,
                antialias: true,
                powerPreference: 'low-power',
            });
        } catch (error) {
            hint.textContent = 'Interactive globe unavailable · static view shown';
            console.warn('WebGL is unavailable for the dashboard globe.', error);
            return;
        }

        const scene = new THREE.Scene();
        const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, .1, 30);
        camera.position.set(0, 0, 7);
        const globeGroup = new THREE.Group();
        globeGroup.rotation.y = -Math.PI / 2;
        scene.add(globeGroup);

        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        renderer.setClearColor(0x000000, 0);
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.domElement.setAttribute('aria-label', 'Interactive 3D globe. Drag to rotate, scroll or pinch to zoom.');
        renderer.domElement.setAttribute('role', 'img');
        renderer.domElement.tabIndex = 0;
        stage.insertBefore(renderer.domElement, popover);

        try {
            const texture = new THREE.CanvasTexture(createEarthTexture());
            texture.colorSpace = THREE.SRGBColorSpace;
            texture.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);
            const earth = new THREE.Mesh(
                new THREE.SphereGeometry(earthRadius, 96, 64),
                new THREE.MeshPhongMaterial({
                    map: texture,
                    color: 0xd5edff,
                    specular: 0x4c9ff1,
                    shininess: 24,
                }),
            );
            globeGroup.add(earth);

            const atmosphere = new THREE.Mesh(
                new THREE.SphereGeometry(earthRadius * 1.055, 64, 48),
                new THREE.ShaderMaterial({
                    vertexShader: `
                        varying vec3 vNormal;
                        varying vec3 vViewPosition;
                        void main() {
                            vec4 viewPosition = modelViewMatrix * vec4(position, 1.0);
                            vNormal = normalize(normalMatrix * normal);
                            vViewPosition = -viewPosition.xyz;
                            gl_Position = projectionMatrix * viewPosition;
                        }
                    `,
                    fragmentShader: `
                        varying vec3 vNormal;
                        varying vec3 vViewPosition;
                        void main() {
                            float rim = pow(1.0 - max(dot(normalize(vNormal), normalize(vViewPosition)), 0.0), 2.4);
                            gl_FragColor = vec4(0.20, 0.63, 1.0, rim * 0.48);
                        }
                    `,
                    blending: THREE.AdditiveBlending,
                    side: THREE.BackSide,
                    transparent: true,
                    depthWrite: false,
                }),
            );
            globeGroup.add(atmosphere);

            const keyLight = new THREE.DirectionalLight(0xb9e8ff, 2.4);
            keyLight.position.set(-3, 2, 5);
            scene.add(keyLight);
            scene.add(new THREE.AmbientLight(0x416b9a, 1.05));
            const fillLight = new THREE.PointLight(0x168cff, 1.5, 8);
            fillLight.position.set(3, -1, -3);
            scene.add(fillLight);

            const raycaster = new THREE.Raycaster();
            const pointer = new THREE.Vector2();
            const markerMeshes = [];
            const interactiveMeshes = [earth];
            const animatedPackets = [];
            const markerByCoordinate = new Map();
            let selectedMarker = null;
            let animationFrame = 0;
            let previousFrame = performance.now();
            let rotationVelocityX = 0;
            let rotationVelocityY = 0;
            let autoRotate = !reducedMotion.matches;
            let zoom = .84;
            let firstResize = true;
            let halfHeight = 1.7;
            let halfWidth = 1.7;
            let pinchStart = null;
            let pointerStart = null;
            let pointerMoved = false;
            let disposed = false;
            const shipmentController = new AbortController();

            const maxZoom = () => Math.min(1.2, (halfWidth - .12) / earthRadius, (halfHeight - .12) / earthRadius);
            const clampZoom = (value) => THREE.MathUtils.clamp(value, Math.min(.78, maxZoom()), maxZoom());
            const resize = () => {
                const { width, height } = stage.getBoundingClientRect();
                if (width <= 0 || height <= 0) return;
                renderer.setSize(width, height, false);
                const aspect = width / height;
                halfHeight = 1.7;
                halfWidth = halfHeight * aspect;
                camera.left = -halfWidth;
                camera.right = halfWidth;
                camera.top = halfHeight;
                camera.bottom = -halfHeight;
                camera.updateProjectionMatrix();
                zoom = firstResize ? maxZoom() * .88 : clampZoom(zoom);
                firstResize = false;
                camera.zoom = zoom;
                camera.updateProjectionMatrix();
            };
            const resizeObserver = new ResizeObserver(resize);
            resizeObserver.observe(stage);
            resize();

            const globePosition = (location, radius = earthRadius) => {
                const longitude = THREE.MathUtils.degToRad(location.longitude);
                const latitude = THREE.MathUtils.degToRad(location.latitude);
                return new THREE.Vector3(
                    radius * Math.cos(latitude) * Math.cos(longitude),
                    radius * Math.sin(latitude),
                    -radius * Math.cos(latitude) * Math.sin(longitude),
                );
            };
            const addMarker = (location, shipment, kind) => {
                if (!validLocation(location)) return;
                const coordinateKey = `${location.latitude.toFixed(3)}:${location.longitude.toFixed(3)}`;
                const existing = markerByCoordinate.get(coordinateKey);
                if (existing) {
                    if (!existing.shipments.some((item) => item.id === shipment.id)) existing.shipments.push(shipment);
                    if (kind === 'Recorded GPS position') {
                        existing.kind = kind;
                        existing.location = location;
                    }
                    return;
                }

                const markerData = { location, kind, shipments: [shipment] };
                const marker = new THREE.Mesh(
                    new THREE.SphereGeometry(.027, 12, 10),
                    new THREE.MeshStandardMaterial({
                        color: statusColors[shipment.status_key] ?? statusColors.other,
                        emissive: statusColors[shipment.status_key] ?? statusColors.other,
                        emissiveIntensity: 1.35,
                        roughness: .28,
                    }),
                );
                marker.position.copy(globePosition(location, earthRadius * 1.025));
                marker.userData.markerData = markerData;
                globeGroup.add(marker);

                const hitTarget = new THREE.Mesh(
                    new THREE.SphereGeometry(.105, 8, 8),
                    new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false }),
                );
                hitTarget.position.copy(marker.position);
                hitTarget.userData.markerData = markerData;
                globeGroup.add(hitTarget);

                markerMeshes.push(hitTarget);
                interactiveMeshes.push(hitTarget);
                markerByCoordinate.set(coordinateKey, markerData);
            };
            const addRoute = (shipment) => {
                if (!validLocation(shipment.origin) || !validLocation(shipment.destination)) return;
                const start = globePosition(shipment.origin, earthRadius * 1.01);
                const end = globePosition(shipment.destination, earthRadius * 1.01);
                const routePoints = [];
                const packetPoints = [];
                for (let step = 0; step <= 64; step += 1) {
                    const progress = step / 64;
                    const point = start.clone().lerp(end, progress).normalize()
                        .multiplyScalar(earthRadius * 1.01 + Math.sin(progress * Math.PI) * .17);
                    routePoints.push(point);
                    packetPoints.push(point.clone());
                }
                const routeColor = statusColors[shipment.status_key] ?? statusColors.other;
                const route = new THREE.Line(
                    new THREE.BufferGeometry().setFromPoints(routePoints),
                    new THREE.LineBasicMaterial({ color: routeColor, transparent: true, opacity: .63 }),
                );
                globeGroup.add(route);

                const packet = new THREE.Mesh(
                    new THREE.SphereGeometry(.018, 8, 8),
                    new THREE.MeshBasicMaterial({ color: 0xc4f3ff }),
                );
                packet.position.copy(packetPoints[16]);
                globeGroup.add(packet);
                animatedPackets.push({ mesh: packet, points: packetPoints });
            };

            const loadShipments = async () => {
                const response = await fetch(stage.dataset.endpoint, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: shipmentController.signal,
                });
                if (!response.ok) throw new Error(`Shipment locations request failed (${response.status}).`);
                const result = await response.json();
                if (!Array.isArray(result.data)) throw new Error('Shipment locations response has an invalid shape.');
                if (disposed) return;

                result.data.slice(0, shipmentLimit).forEach((shipment, index) => {
                    addMarker(shipment.origin, shipment, 'Origin');
                    addMarker(shipment.destination, shipment, 'Destination');
                    if (validLocation(shipment.position)) addMarker(shipment.position, shipment, 'Recorded GPS position');
                    if (index < routeLimit) addRoute(shipment);
                });
                if (markerMeshes.length === 0) hint.textContent = 'No recorded shipment coordinates';
            };

            const hidePopover = () => {
                popover.hidden = true;
                popover.replaceChildren();
            };
            const showPopover = (markerData, clientX, clientY) => {
                const shipment = markerData.shipments[0];
                const heading = document.createElement('strong');
                heading.textContent = markerData.location.name || markerData.location.location || markerData.kind;
                const tracking = document.createElement('span');
                tracking.textContent = `${shipment.tracking_number} · ${shipment.status_label}`;
                const route = document.createElement('span');
                route.textContent = `${shipment.origin?.name ?? 'Origin not recorded'} → ${shipment.destination?.name ?? 'Destination not recorded'} · ${markerData.kind}`;
                const position = document.createElement('span');
                position.textContent = shipment.position
                    ? `Recorded GPS: ${shipment.position.location} · ${formatDate(shipment.position.updated_at)} · ETA ${formatDate(shipment.eta)}`
                    : `No recorded GPS position · ETA ${formatDate(shipment.eta)}`;
                const updated = document.createElement('span');
                updated.textContent = `Shipment updated ${formatDate(shipment.shipment_updated_at)}`;
                const details = document.createElement('a');
                details.href = shipment.url;
                details.textContent = markerData.shipments.length > 1
                    ? `Open shipment · +${markerData.shipments.length - 1} at this location →`
                    : 'Open shipment details →';
                popover.replaceChildren(heading, tracking, route, position, updated, details);
                popover.hidden = false;
                if (clientX !== undefined && clientY !== undefined) {
                    const bounds = stage.getBoundingClientRect();
                    popover.style.left = `${Math.max(8, Math.min(clientX - bounds.left + 12, bounds.width - popover.offsetWidth - 8))}px`;
                    popover.style.top = `${Math.max(8, Math.min(clientY - bounds.top + 12, bounds.height - popover.offsetHeight - 8))}px`;
                    popover.style.right = 'auto';
                } else {
                    popover.style.left = '';
                    popover.style.top = '';
                    popover.style.right = '';
                }
            };
            const pickMarker = (event) => {
                const bounds = renderer.domElement.getBoundingClientRect();
                pointer.set(
                    ((event.clientX - bounds.left) / bounds.width) * 2 - 1,
                    -((event.clientY - bounds.top) / bounds.height) * 2 + 1,
                );
                raycaster.setFromCamera(pointer, camera);
                const hit = raycaster.intersectObjects(interactiveMeshes, false)[0];
                if (hit?.object === earth) return null;
                return hit?.object.userData.markerData ?? null;
            };

            renderer.domElement.addEventListener('pointerdown', (event) => {
                if (event.button !== undefined && event.button !== 0) return;
                renderer.domElement.setPointerCapture(event.pointerId);
                pointerStart = {
                    id: event.pointerId,
                    x: event.clientX,
                    y: event.clientY,
                    lastX: event.clientX,
                    lastY: event.clientY,
                    lastAt: performance.now(),
                    rotationX: globeGroup.rotation.x,
                    rotationY: globeGroup.rotation.y,
                };
                pointerMoved = false;
                autoRotate = false;
                if (event.pointerType === 'touch' && renderer.domElement.hasPointerCapture(event.pointerId)) {
                    const activePointers = renderer.domElement.__activePointers ?? new Map();
                    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                    renderer.domElement.__activePointers = activePointers;
                    if (activePointers.size === 2) {
                        const points = [...activePointers.values()];
                        pinchStart = {
                            distance: Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y),
                            zoom,
                        };
                        pointerStart = null;
                    }
                }
            });
            renderer.domElement.addEventListener('pointermove', (event) => {
                const activePointers = renderer.domElement.__activePointers;
                if (event.pointerType === 'touch' && activePointers?.has(event.pointerId)) {
                    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                    if (activePointers.size >= 2 && pinchStart) {
                        const points = [...activePointers.values()];
                        const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                        zoom = clampZoom(pinchStart.zoom * distance / Math.max(pinchStart.distance, 1));
                        camera.zoom = zoom;
                        camera.updateProjectionMatrix();
                        pointerMoved = true;
                        if (reducedMotion.matches) renderer.render(scene, camera);
                        return;
                    }
                }

                if (pointerStart?.id === event.pointerId && !pinchStart) {
                    const deltaX = event.clientX - pointerStart.x;
                    const deltaY = event.clientY - pointerStart.y;
                    const elapsed = Math.max((performance.now() - pointerStart.lastAt) / 1000, .008);
                    rotationVelocityY = THREE.MathUtils.clamp((event.clientX - pointerStart.lastX) * .006 / elapsed * .12, -1.2, 1.2);
                    rotationVelocityX = THREE.MathUtils.clamp((event.clientY - pointerStart.lastY) * .006 / elapsed * .12, -1.2, 1.2);
                    pointerStart.lastX = event.clientX;
                    pointerStart.lastY = event.clientY;
                    pointerStart.lastAt = performance.now();
                    if (Math.abs(deltaX) + Math.abs(deltaY) > 2) pointerMoved = true;
                    globeGroup.rotation.y = pointerStart.rotationY + deltaX * .006;
                    globeGroup.rotation.x = THREE.MathUtils.clamp(pointerStart.rotationX + deltaY * .006, -Math.PI / 2 + .05, Math.PI / 2 - .05);
                    selectedMarker = null;
                    hidePopover();
                    if (reducedMotion.matches) renderer.render(scene, camera);
                    return;
                }

                if (event.pointerType === 'mouse') {
                    const marker = pickMarker(event);
                    renderer.domElement.style.cursor = marker ? 'pointer' : 'grab';
                    if (marker) showPopover(marker, event.clientX, event.clientY);
                    else if (!selectedMarker) hidePopover();
                }
            });
            const finishPointer = (event) => {
                const marker = !pointerMoved ? pickMarker(event) : null;
                if (marker) {
                    selectedMarker = marker;
                    showPopover(marker);
                } else if (!pointerMoved && event.pointerType === 'touch' && !selectedMarker) {
                    hidePopover();
                }
                if (renderer.domElement.hasPointerCapture(event.pointerId)) renderer.domElement.releasePointerCapture(event.pointerId);
                const activePointers = renderer.domElement.__activePointers;
                activePointers?.delete(event.pointerId);
                if (activePointers?.size < 2) pinchStart = null;
                if (pointerStart?.id === event.pointerId) pointerStart = null;
                pointerMoved = false;
                if (!reducedMotion.matches) autoRotate = false;
            };
            renderer.domElement.addEventListener('pointerup', finishPointer);
            renderer.domElement.addEventListener('pointercancel', finishPointer);
            renderer.domElement.addEventListener('wheel', (event) => {
                event.preventDefault();
                zoom = clampZoom(zoom * Math.exp(-event.deltaY * .001));
                camera.zoom = zoom;
                camera.updateProjectionMatrix();
                autoRotate = false;
                if (reducedMotion.matches) renderer.render(scene, camera);
                window.setTimeout(() => { if (!reducedMotion.matches) autoRotate = true; }, 1200);
            }, { passive: false });
            renderer.domElement.addEventListener('keydown', (event) => {
                const step = .12;
                if (event.key === 'ArrowLeft') globeGroup.rotation.y -= step;
                else if (event.key === 'ArrowRight') globeGroup.rotation.y += step;
                else if (event.key === 'ArrowUp') globeGroup.rotation.x = THREE.MathUtils.clamp(globeGroup.rotation.x - step, -Math.PI / 2 + .05, Math.PI / 2 - .05);
                else if (event.key === 'ArrowDown') globeGroup.rotation.x = THREE.MathUtils.clamp(globeGroup.rotation.x + step, -Math.PI / 2 + .05, Math.PI / 2 - .05);
                else if (event.key === '+' || event.key === '=') zoom = clampZoom(zoom + .08);
                else if (event.key === '-') zoom = clampZoom(zoom - .08);
                else return;
                event.preventDefault();
                camera.zoom = zoom;
                camera.updateProjectionMatrix();
                autoRotate = false;
                if (reducedMotion.matches) renderer.render(scene, camera);
                if (!reducedMotion.matches) window.setTimeout(() => { autoRotate = true; }, 1200);
            });

            const render = (now) => {
                if (disposed || document.hidden) return;
                const delta = Math.min((now - previousFrame) / 1000, .05);
                previousFrame = now;
                if (!reducedMotion.matches) {
                    if (autoRotate) globeGroup.rotation.y += delta * .035;
                    else if (!pointerStart) {
                        globeGroup.rotation.y += rotationVelocityY;
                        globeGroup.rotation.x = THREE.MathUtils.clamp(globeGroup.rotation.x + rotationVelocityX, -Math.PI / 2 + .05, Math.PI / 2 - .05);
                        const damping = Math.exp(-delta * 2.1);
                        rotationVelocityX *= damping;
                        rotationVelocityY *= damping;
                        if (Math.abs(rotationVelocityX) + Math.abs(rotationVelocityY) < .00004 && !pinchStart) autoRotate = true;
                    }
                }
                const packetStep = Math.floor(now / 34);
                if (!reducedMotion.matches) {
                    animatedPackets.forEach(({ mesh, points }, index) => {
                        mesh.position.copy(points[(packetStep + index * 19) % points.length]);
                    });
                }
                renderer.render(scene, camera);
                animationFrame = reducedMotion.matches ? 0 : window.requestAnimationFrame(render);
            };

            renderer.domElement.addEventListener('webglcontextlost', (event) => {
                event.preventDefault();
                window.cancelAnimationFrame(animationFrame);
                stage.classList.remove('is-ready');
                renderer.domElement.style.display = 'none';
                hint.textContent = 'Interactive globe unavailable · static view shown';
            }, { once: true });

            const onVisibilityChange = () => {
                if (document.hidden) {
                    window.cancelAnimationFrame(animationFrame);
                    return;
                }
                previousFrame = performance.now();
                if (!reducedMotion.matches) animationFrame = window.requestAnimationFrame(render);
            };
            document.addEventListener('visibilitychange', onVisibilityChange);
            const onReducedMotionChange = (event) => {
                autoRotate = !event.matches;
                if (event.matches) {
                    rotationVelocityX = 0;
                    rotationVelocityY = 0;
                    window.cancelAnimationFrame(animationFrame);
                    animationFrame = 0;
                    renderer.render(scene, camera);
                } else if (!document.hidden) {
                    previousFrame = performance.now();
                    animationFrame = window.requestAnimationFrame(render);
                }
            };
            reducedMotion.addEventListener('change', onReducedMotionChange);

            renderer.render(scene, camera);
            stage.classList.add('is-ready');
            if (!document.hidden && !reducedMotion.matches) animationFrame = window.requestAnimationFrame(render);
            loadShipments().then(() => {
                if (reducedMotion.matches && !disposed) renderer.render(scene, camera);
            }).catch((error) => {
                if (error.name === 'AbortError') return;
                hint.textContent = 'Shipment locations unavailable · globe remains illustrative';
                console.error('Could not load shipment data for the dashboard globe.', error);
            });
            window.addEventListener('pagehide', () => {
                disposed = true;
                shipmentController.abort();
                window.cancelAnimationFrame(animationFrame);
                resizeObserver.disconnect();
                document.removeEventListener('visibilitychange', onVisibilityChange);
                reducedMotion.removeEventListener('change', onReducedMotionChange);
                renderer.dispose();
                scene.traverse((object) => {
                    object.geometry?.dispose();
                    if (Array.isArray(object.material)) object.material.forEach((material) => material.dispose());
                    else object.material?.dispose();
                });
                texture.dispose();
            }, { once: true });
        } catch (error) {
            renderer.dispose();
            renderer.domElement.remove();
            if (fallbackImage) fallbackImage.style.display = 'block';
            hint.textContent = 'Interactive globe unavailable · static view shown';
            console.error('Could not initialize the dashboard globe.', error);
        }
    };

    buildScene();
}
