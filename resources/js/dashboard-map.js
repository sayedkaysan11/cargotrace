import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

window.L = L;

const startMap = async () => {
    const container = document.getElementById('dashboard-map');
    if (!container || container.dataset.initialized === 'true') return;

    container.dataset.initialized = 'true';
    const loading = document.getElementById('map-loading');
    const errorPanel = document.getElementById('map-error');
    const errorMessage = document.getElementById('map-error-message');
    const emptyPanel = document.getElementById('map-empty');
    const lastUpdated = document.getElementById('map-last-updated');
    const statusFilter = document.getElementById('shipment-map-status');
    const layerSelect = document.getElementById('shipment-map-layer');
    const limitNotice = document.getElementById('map-limit-notice');
    const mapPanel = document.getElementById('global-map');
    const defaultView = { center: [20, 0], zoom: 2 };
    const colors = {
        in_transit: '#168bff',
        delivered: '#28ce91',
        delayed: '#ff9e43',
        exception: '#fb596f',
        other: '#a4b4ca',
    };

    await import('leaflet.markercluster');

    const map = L.map(container, {
        center: defaultView.center,
        zoom: defaultView.zoom,
        minZoom: 2,
        maxZoom: 18,
        worldCopyJump: true,
        zoomControl: true,
        scrollWheelZoom: true,
        preferCanvas: true,
        zoomAnimation: true,
        fadeAnimation: true,
        markerZoomAnimation: true,
    });
    const tiles = {
        standard: L.tileLayer(container.dataset.tileStandard, {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
        }),
        dark: L.tileLayer(container.dataset.tileDark, {
            maxZoom: 20,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
        }),
    };
    let activeTiles = tiles.dark;
    let fetchController = null;
    let refreshTimer = null;
    let tileErrors = 0;
    let hasLoaded = false;

    const tileStatus = document.getElementById('map-tile-status');
    const updateTileStatus = (event) => {
        if (event.type === 'tileerror') tileErrors += 1;
        if (tileErrors > 0) {
            tileStatus.hidden = false;
            tileStatus.textContent = 'Some map tiles could not be loaded. Check your connection.';
        } else if (hasLoaded) {
            tileStatus.hidden = true;
        }
    };
    activeTiles.on('tileerror', updateTileStatus);
    activeTiles.on('load', updateTileStatus);
    activeTiles.addTo(map);

    const routeLayer = L.featureGroup().addTo(map);
    const markerCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 48,
        showCoverageOnHover: false,
        spiderfyOnMaxZoom: true,
        animate: true,
        animateAddingMarkers: false,
        iconCreateFunction: (cluster) => L.divIcon({
            className: 'cargo-map-cluster',
            html: `<span>${cluster.getChildCount()}</span>`,
            iconSize: [38, 38],
        }),
    }).addTo(map);

    const validCoordinates = (location) => location
        && Number.isFinite(location.latitude)
        && Number.isFinite(location.longitude)
        && location.latitude >= -90
        && location.latitude <= 90
        && location.longitude >= -180
        && location.longitude <= 180;

    const formatDate = (date) => {
        if (!date) return 'Not recorded';
        const parsed = new Date(date);
        if (Number.isNaN(parsed.getTime())) return 'Not recorded';

        return new Intl.DateTimeFormat('en-GB', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'UTC',
        }).format(parsed) + ' UTC';
    };

    const popupContent = (shipment, pointKind) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'shipment-map-popup';
        const heading = document.createElement('strong');
        heading.textContent = shipment.tracking_number;
        wrapper.append(heading);

        const lines = [
            ['Origin', shipment.origin?.name ?? 'Coordinates unavailable'],
            ['Destination', shipment.destination?.name ?? 'Coordinates unavailable'],
            ['Status', shipment.status_label],
            ['Last known location', pointKind === 'position'
                ? shipment.position.location
                : pointKind === 'route'
                    ? 'GPS position not recorded'
                    : `${pointKind === 'origin' ? shipment.origin?.name ?? 'Origin' : shipment.destination?.name ?? 'Destination'} · route endpoint`],
            ['ETA', formatDate(shipment.eta)],
            ['Last GPS update', shipment.position ? formatDate(shipment.position.updated_at) : 'GPS position not recorded'],
            ['Shipment updated', formatDate(shipment.shipment_updated_at)],
            ['Route', shipment.origin && shipment.destination ? 'Estimated origin-to-destination route' : 'Route coordinates unavailable'],
        ];

        lines.forEach(([label, value]) => {
            const row = document.createElement('span');
            const key = document.createElement('small');
            key.textContent = label;
            const text = document.createElement('b');
            text.textContent = value;
            row.append(key, text);
            wrapper.append(row);
        });

        const link = document.createElement('a');
        link.href = shipment.url;
        link.textContent = 'Open shipment details →';
        wrapper.append(link);

        return wrapper;
    };

    const markerIcon = (kind, statusKey) => L.divIcon({
        className: 'cargo-map-icon',
        html: `<span class="cargo-map-marker ${kind} ${statusKey}" aria-hidden="true"></span>`,
        iconSize: kind === 'position' ? [22, 22] : [16, 16],
        iconAnchor: kind === 'position' ? [11, 11] : [8, 8],
        popupAnchor: [0, kind === 'position' ? -12 : -8],
    });

    const showError = (message) => {
        errorMessage.textContent = message;
        errorPanel.hidden = false;
    };
    const hideError = () => { errorPanel.hidden = true; };

    const renderShipments = (shipments) => {
        routeLayer.clearLayers();
        markerCluster.clearLayers();
        let pointCount = 0;
        const bounds = [];

        shipments.forEach((shipment) => {
            const originValid = validCoordinates(shipment.origin);
            const destinationValid = validCoordinates(shipment.destination);
            const routeColor = colors[shipment.status_key] ?? colors.other;

            if (originValid && destinationValid) {
                const route = L.polyline(
                    [
                        [shipment.origin.latitude, shipment.origin.longitude],
                        [shipment.destination.latitude, shipment.destination.longitude],
                    ],
                    {
                        color: routeColor,
                        weight: 2.5,
                        opacity: 0.78,
                        dashArray: '7 8',
                        lineCap: 'round',
                        className: 'shipment-route-estimated',
                    },
                );
                route.bindPopup(popupContent(shipment, 'route'));
                routeLayer.addLayer(route);
            }

            [
                { location: shipment.origin, kind: 'origin', label: 'Origin' },
                { location: shipment.destination, kind: 'destination', label: 'Destination' },
                { location: shipment.position, kind: 'position', label: 'Recorded GPS position' },
            ].forEach(({ location, kind, label }) => {
                if (!validCoordinates(location)) return;
                const marker = L.marker([location.latitude, location.longitude], {
                    icon: markerIcon(kind, shipment.status_key),
                    title: `${shipment.tracking_number} · ${label}`,
                    alt: `${label} for ${shipment.tracking_number}`,
                    keyboard: true,
                });
                marker.bindPopup(popupContent(shipment, kind), { maxWidth: 300, minWidth: 245 });
                markerCluster.addLayer(marker);
                bounds.push([location.latitude, location.longitude]);
                pointCount += 1;
            });
        });

        container.classList.toggle('map-has-no-coordinates', pointCount === 0);
        return { pointCount, bounds };
    };

    const refresh = async ({ fit = false, initial = false } = {}) => {
        fetchController?.abort();
        fetchController = new AbortController();
        if (initial) loading.hidden = false;

        const url = new URL(container.dataset.endpoint, window.location.origin);
        url.searchParams.set('status', statusFilter.value);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: fetchController.signal,
            });
            if (!response.ok) throw new Error(`Map data request failed (${response.status}).`);

            const result = await response.json();
            if (!Array.isArray(result.data) || !result.meta) {
                throw new Error('The map endpoint returned an invalid response.');
            }

            const rendered = renderShipments(result.data);
            hasLoaded = true;
            loading.hidden = true;
            hideError();
            emptyPanel.hidden = rendered.pointCount > 0;
            limitNotice.hidden = !result.meta.truncated;
            lastUpdated.textContent = formatDate(result.meta.fetched_at);
            document.getElementById('map-shipment-count').textContent = `${result.meta.shipment_count} shipments · ${rendered.pointCount} mapped points`;

            if (fit && rendered.bounds.length > 0) {
                map.fitBounds(rendered.bounds, { padding: [36, 36], maxZoom: 8 });
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            loading.hidden = true;
            emptyPanel.hidden = true;
            showError('Could not refresh shipment locations. The map will retry automatically.');
            console.error('Shipment map refresh failed.', error);
        }
    };

    statusFilter.addEventListener('change', () => refresh({ fit: true }));
    document.getElementById('map-fit-all').addEventListener('click', () => refresh({ fit: true }));
    document.getElementById('map-reset-view').addEventListener('click', () => map.setView(defaultView.center, defaultView.zoom, { animate: true }));
    document.getElementById('map-retry').addEventListener('click', () => refresh({ initial: !hasLoaded }));
    layerSelect.addEventListener('change', () => {
        activeTiles.off('tileerror', updateTileStatus);
        activeTiles.off('load', updateTileStatus);
        map.removeLayer(activeTiles);
        tileErrors = 0;
        activeTiles = tiles[layerSelect.value];
        activeTiles.on('tileerror', updateTileStatus);
        activeTiles.on('load', updateTileStatus);
        activeTiles.addTo(map);
        tileStatus.hidden = true;
    });

    const fullscreenButton = document.getElementById('map-fullscreen');
    fullscreenButton.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === mapPanel) {
                await document.exitFullscreen();
            } else {
                await mapPanel.requestFullscreen();
            }
        } catch (error) {
            showError('Fullscreen is not available in this browser.');
            console.error('Could not enter fullscreen map view.', error);
        }
    });
    document.addEventListener('fullscreenchange', () => {
        const isFullscreen = document.fullscreenElement === mapPanel;
        fullscreenButton.setAttribute('aria-pressed', String(isFullscreen));
        fullscreenButton.textContent = isFullscreen ? 'Exit fullscreen' : 'Fullscreen';
        window.setTimeout(() => map.invalidateSize({ pan: false }), 100);
    });

    const resizeObserver = new ResizeObserver(() => map.invalidateSize({ pan: false }));
    resizeObserver.observe(container);
    window.addEventListener('orientationchange', () => window.setTimeout(() => map.invalidateSize(), 150));

    await refresh({ fit: true, initial: true });
    refreshTimer = window.setInterval(() => {
        if (!document.hidden) refresh();
    }, 120_000);

    window.addEventListener('pagehide', () => {
        window.clearInterval(refreshTimer);
        fetchController?.abort();
        resizeObserver.disconnect();
        map.remove();
        container.dataset.initialized = 'false';
    }, { once: true });
};

startMap().catch((error) => {
    console.error('Could not initialize the shipment map.', error);
    const errorPanel = document.getElementById('map-error');
    if (errorPanel) {
        document.getElementById('map-loading').hidden = true;
        errorPanel.hidden = false;
        document.getElementById('map-error-message').textContent = 'The interactive map could not be initialized. Reload the page to try again.';
    }
});
