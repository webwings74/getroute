<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Maprouter Script -- Displaying locations, and/or routes in a browser(c) Webwings 2025 -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webwings OpenStreetMap Route</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Verdana, Geneva, sans-serif;
            font-size: 14px;
        }

        /* Map fills the full viewport; shrinks horizontally when the side panel is open */
        #map {
            height: 100vh;
            width: 100vw;
            transition: width 0.3s ease;
        }
        #map.panel-open {
            width: calc(100vw - 320px);
            margin-left: 320px;
        }

        /* Overlay shown in single-route mode: total distance, travel time, arrival */
        #duration-container {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        /* ── Side panel ─────────────────────────────────────────────────── */
        #table-panel {
            position: fixed;
            top: 0;
            left: -320px;         /* hidden off-screen by default */
            width: 320px;
            height: 100vh;
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            z-index: 2000;
            display: flex;
            flex-direction: column;
            transition: left 0.3s ease;
            overflow: hidden;
        }
        #table-panel.open {
            left: 0;
        }

        /* Panel header with title and close button */
        #panel-header {
            background: #1a2a3a;
            color: #fff;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        #panel-header h2 {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.03em;
        }
        #panel-close {
            background: none;
            border: none;
            color: #8fa8c0;
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        #panel-close:hover { color: #fff; }

        /* Scrollable content area */
        #panel-content {
            overflow-y: auto;
            flex: 1;
            padding: 12px;
        }

        /* Copy-URL button at the top of the panel */
        #copy-url-btn {
            width: 100%;
            background: #1a2a3a;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            padding: 9px;
            cursor: pointer;
            margin-bottom: 16px;
        }
        #copy-url-btn:hover { background: #243447; }

        /* Route section inside the panel */
        .route-section {
            margin-bottom: 20px;
        }
        .route-section h3 {
            font-size: 12px;
            font-weight: 700;
            color: #1a2a3a;
            margin: 0 0 6px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid #1a2a3a;
        }

        /* Point management list (reorder / move controls) */
        .points-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 10px;
        }
        .point-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            background: #f7f9fb;
            border: 1px solid #e8edf2;
            border-radius: 5px;
            padding: 5px 7px;
            font-size: 11px;
            color: #2c3e50;
        }
        .point-label {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .point-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .btn-icon {
            background: #e8edf2;
            color: #1a2a3a;
            border: none;
            border-radius: 4px;
            width: 22px;
            height: 22px;
            font-size: 10px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .btn-icon:hover:not(:disabled) { background: #d0dae4; }
        .btn-icon:disabled { opacity: .35; cursor: default; }
        .location-move select {
            font-size: 10px;
            padding: 2px 3px;
            border: 1px solid #c8d4e0;
            border-radius: 4px;
            background: #fff;
            max-width: 74px;
        }

        /* Segment table */
        .route-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .route-table th {
            background: #f0f4f8;
            color: #3a5068;
            text-align: left;
            padding: 5px 6px;
            font-weight: 700;
            border-bottom: 1px solid #d0dae4;
        }
        .route-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #eef1f4;
            color: #2c3e50;
            vertical-align: top;
        }
        .route-table tr:last-child td {
            border-bottom: none;
        }
        /* Total row at the bottom of each route table */
        .route-table tr.total-row td {
            font-weight: 700;
            background: #f0f4f8;
            border-top: 2px solid #d0dae4;
            border-bottom: none;
        }
        .route-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        /* Toggle button — always visible on the left edge of the map */
        #panel-toggle {
            position: fixed;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            background: #1a2a3a;
            color: #fff;
            border: none;
            width: 28px;
            height: 64px;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            z-index: 1500;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: left 0.3s ease;
            writing-mode: vertical-rl;
            letter-spacing: 0.05em;
        }
        #panel-toggle.panel-open {
            left: 320px;
        }
        #panel-toggle:hover { background: #243447; }
    </style>
</head>
<body>

<!-- Total distance and time overlay (single-route mode only) -->
<div id="duration-container"></div>

<!-- Side panel (shown when ?table is present in the URL) -->
<div id="table-panel">
    <div id="panel-header">
        <h2>Route details</h2>
        <button id="panel-close" title="Close panel">&#x2715;</button>
    </div>
    <div id="panel-content">
        <!-- Route tables are injected here by buildTablePanel() -->
    </div>
</div>

<!-- Toggle button to open/close the side panel -->
<button id="panel-toggle" title="Toggle route details">&#9776;</button>

<!-- Map -->
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="config.js"></script>
<script>
    /**
     * maprouter.php
     *
     * Unified map routing script — handles single and multiple routes,
     * standalone locations, selectable tile layers, and an optional
     * collapsible side panel with per-route segment tables (?table).
     *
     * When ?table is active, the side panel also lets you rearrange the
     * loaded data: move a route point out to a standalone location marker,
     * move a location marker into an existing route, and reorder points
     * within a route. Every change updates the browser URL in place (so the
     * result can be copied/shared or survives a refresh) and re-renders the
     * map. Already-geocoded coordinates are cached on each point, and each
     * route's computed geometry is cached by its point sequence, so editing
     * never re-queries Nominatim and only re-queries OpenRouteService for
     * routes whose composition actually changed.
     *
     * URL parameters:
     *   route    — JSON array of route points (repeat for multiple routes)
     *   location — JSON array of standalone location markers
     *   layer    — tile layer: 'topo', 'cycle', 'transport', or default OSM
     *   profile  — ORS routing profile (driving-car, cycling-regular, etc.)
     *   section  — 'true' to show per-segment stats in waypoint popups
     *   color    — JSON array of CSS colors, one array per route
     *   zoom     — integer zoom level override
     *   table    — (no value needed) show the collapsible segment table panel
     *
     * Libraries used:
     *   Leaflet.js        — interactive map rendering
     *   OpenRouteService  — route calculation (API key required)
     *   Nominatim         — geocoding of address strings (no key required)
     *   Tracestrack       — topographic tile layer (API key required)
     *   Thunderforest     — cycling/transport tile layers (API key required)
     */

    // ── API keys (loaded from config.js) ────────────────────────────────────
    const thunderforestApiKey    = config.thunderforestApiKey;
    const tracestrackApiKey      = config.tracestrackApiKey;
    const openRouteServiceApiKey = config.openRouteServiceApiKey;

    // ── Default icon URLs and dimensions ────────────────────────────────────
    const defaultIconUrl = 'https://code.webwings.nl/pinpoint.png';
    const startIconUrl   = 'https://code.webwings.nl/start.png';
    const endIconUrl     = 'https://code.webwings.nl/stop.png';
    const iconWidth      = 32;  // Icon width in pixels
    const iconHeight     = 37;  // Icon height in pixels
    const popupOffset    = -35; // Vertical offset to position popups above markers

    // ── Global state ────────────────────────────────────────────────────────
    let totalDistance     = 0;
    let totalDuration     = 0;
    let bounds            = L.latLngBounds(); // Bounding box of all rendered elements
    let notFoundLocations = [];               // Location names that could not be geocoded
    let apiErrors         = [];               // { provider, status } entries for rate-limit/quota errors
    let routeErrors       = [];               // { routeIndex, message } entries for other route failures

    /**
     * tableData holds the collected segment data for the side panel.
     * Structure: [ { routeLabel, segments: [ { from, to, distance, duration } ] } ]
     */
    let tableData = [];

    // ── Editable state (seeded once from the URL, then mutated by panel actions) ──
    let routesState    = []; // Array of routes; each route is an array of geocoded point objects
    let locationsState = []; // Array of geocoded point objects (standalone markers)
    let colorState      = []; // Array of color arrays, parallel to routesState

    // Leaflet layers added by renderAll() — cleared and rebuilt on every render
    let dynamicLayers = [];

    // Cache of computed route geometry, keyed by the route's ordered coordinate
    // sequence. A cache hit means the route's point order hasn't changed since
    // it was last computed, so no fresh OpenRouteService request is needed.
    const routeGeometryCache = new Map();

    // Options parsed once at load time; these don't change while editing.
    let sectionFlag      = false;
    let profileValue     = "driving-car";
    let requestedZoomVal  = null;
    let showTableFlag    = false;

    // HTTP statuses that typically indicate a provider rate limit or exhausted quota
    const API_LIMIT_STATUSES = new Set([429, 403]);

    /**
     * Builds and throws an Error for a failed fetch response. If the response status
     * indicates a rate limit or quota problem, the failure is also recorded in
     * apiErrors (deduplicated) and the thrown error is flagged with isApiLimit so
     * callers can avoid double-reporting it as a generic route failure.
     *
     * @param {string} provider   - Human-readable provider name, e.g. "OpenRouteService (routing)"
     * @param {Response} response - The fetch Response object
     * @param {string} label      - Prefix used for the generic error message
     */
    function throwFetchError(provider, response, label) {
        if (API_LIMIT_STATUSES.has(response.status)) {
            if (!apiErrors.some(e => e.provider === provider && e.status === response.status)) {
                apiErrors.push({ provider, status: response.status });
            }
            const err = new Error(`${provider} returned HTTP ${response.status}`);
            err.isApiLimit = true;
            throw err;
        }
        throw new Error(`${label}: ${response.status}`);
    }

    // ── Map initialisation ──────────────────────────────────────────────────
    var map = L.map('map').setView([52.3676, 4.9041], 10);

    // ── URL parameter parsing ───────────────────────────────────────────────
    const urlParams = new URLSearchParams(window.location.search);

    // Apply optional page title
    (function applyTitle() {
        const title = urlParams.get("title");
        if (title) document.title = title;
    })();

    /**
     * Selects and adds the correct tile layer based on the ?layer= URL parameter.
     * Supported values: 'topo', 'cycle', 'transport'. Defaults to OpenStreetMap.
     */
    (function initTileLayer() {
        const layer = urlParams.get("layer");
        let tileLayerUrl, tileLayerAttribution;

        switch (layer) {
            case "topo":
                tileLayerUrl         = `https://tile.tracestrack.com/topo__/{z}/{x}/{y}.png?key=${tracestrackApiKey}`;
                tileLayerAttribution = "&copy; Tracestrack contributors";
                break;
            case "cycle":
                tileLayerUrl         = `https://api.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "transport":
                tileLayerUrl         = `https://api.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "landscape":
                tileLayerUrl         = `https://api.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "outdoors":
                tileLayerUrl         = `https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "transport-dark":
                tileLayerUrl         = `https://api.thunderforest.com/transport-dark/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "spinal-map":
                tileLayerUrl         = `https://api.thunderforest.com/spinal-map/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "pioneer":
                tileLayerUrl         = `https://api.thunderforest.com/pioneer/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "mobile-atlas":
                tileLayerUrl         = `https://api.thunderforest.com/mobile-atlas/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "neighbourhood":
                tileLayerUrl         = `https://api.thunderforest.com/neighbourhood/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "atlas":
                tileLayerUrl         = `https://api.thunderforest.com/atlas/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            default:
                tileLayerUrl         = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
                tileLayerAttribution = "&copy; OpenStreetMap contributors";
                break;
        }

        L.tileLayer(tileLayerUrl, { attribution: tileLayerAttribution, crossOrigin: '' }).addTo(map);
    })();

    /**
     * Parses the optional ?zoom= URL parameter.
     * Returns an integer zoom level, or null if absent or invalid.
     *
     * @returns {number|null}
     */
    function getRequestedZoom() {
        const zoomParam = urlParams.get("zoom");
        if (!zoomParam) return null;
        const zoom = parseInt(zoomParam, 10);
        if (isNaN(zoom)) {
            console.warn("Invalid zoom value provided. Value will be ignored.");
            return null;
        }
        return zoom;
    }

    /**
     * Parses all ?color= URL parameters and returns an array of color arrays,
     * one per route. Falls back to ['navy'] if no color parameters are present.
     *
     * @returns {string[][]}
     */
    function getColorParams() {
        const colorParams = [];
        urlParams.forEach((value, key) => {
            if (key === "color") {
                try {
                    const parsed = JSON.parse(value);
                    if (Array.isArray(parsed) && parsed.every(c => typeof c === 'string')) {
                        colorParams.push(parsed);
                    } else {
                        console.warn("Invalid color parameter. Default colors will be used.");
                    }
                } catch {
                    console.warn("Error parsing color parameter. Default colors will be used.");
                }
            }
        });
        return colorParams.length > 0 ? colorParams : [['navy']];
    }

    /**
     * Returns the CSS color string for a given segment index within a route,
     * cycling through the route's color array.
     *
     * @param {string[]} colors     - Color array for this route
     * @param {number} segmentIndex - Zero-based segment index within the route
     * @returns {string}
     */
    function getSegmentColor(colors, segmentIndex) {
        return colors[segmentIndex % colors.length];
    }

    /**
     * Parses all ?route= URL parameters and returns an array of route arrays.
     * Supports multiple routes by repeating the parameter in the query string.
     * Each route is an array of point objects { point, text, icon }.
     *
     * @returns {Array[]}
     */
    function getRoutesFromQuery() {
        const routes = [];
        urlParams.forEach((value, key) => {
            if (key === "route") {
                try {
                    const route = JSON.parse(value);
                    if (!Array.isArray(route)) throw new Error("Route must be an array.");
                    routes.push(route.map(point => {
                        if (!point.point) throw new Error("Each point must have a 'point' property.");
                        return {
                            point: point.point,
                            text:  point.text || null,
                            icon:  point.icon || defaultIconUrl
                        };
                    }));
                } catch (error) {
                    console.error("Error processing route:", error.message);
                }
            }
        });
        return routes;
    }

    /**
     * Parses the ?location= URL parameter and returns an array of standalone markers.
     * Each point contains { point, text, icon }.
     * Returns an empty array if the parameter is missing or invalid.
     *
     * @returns {Object[]}
     */
    function getLocationsFromQuery() {
        const locationParam = urlParams.get("location");
        if (!locationParam) return [];
        try {
            const locations = JSON.parse(locationParam);
            if (!Array.isArray(locations)) throw new Error("Locations must be an array.");
            return locations.map(point => {
                if (!point.point) throw new Error("Each point must have a 'point' property.");
                return {
                    point: point.point,
                    text:  point.text || null,
                    icon:  point.icon || defaultIconUrl
                };
            });
        } catch (error) {
            alert("Error processing locations: " + error.message);
            return [];
        }
    }

    /**
     * Geocodes a flat list of point specs via Nominatim (one request each).
     * Points that can't be found are recorded in notFoundLocations and resolve
     * to null in the result array (same order as the input).
     *
     * @param {Object[]} specs - Array of { point, text, icon }
     * @returns {Promise<Array<Object|null>>}
     */
    function geocodeAll(specs) {
        return Promise.all(specs.map(spec => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(spec.point)}`;
            return fetch(url)
                .then(r => {
                    if (!r.ok) throwFetchError('Nominatim (geocoding)', r, 'Error fetching location');
                    return r.json();
                })
                .then(data => {
                    if (data.length === 0) {
                        console.error("Location not found: " + spec.point);
                        notFoundLocations.push(spec.point);
                        return null;
                    }
                    return {
                        point:       spec.point,
                        text:        spec.text,
                        icon:        spec.icon,
                        lat:         parseFloat(data[0].lat),
                        lon:         parseFloat(data[0].lon),
                        displayName: data[0].display_name.split(",")[0]
                    };
                })
                .catch(error => {
                    console.error("Error fetching location data:", error);
                    return null;
                });
        }));
    }

    // ── Side panel ────────────────────────────────────────────────────────────

    function fmtDuration(seconds) {
        const totalMins = Math.round(seconds / 60);
        if (totalMins < 60) return `${totalMins} min`;
        const h = Math.floor(totalMins / 60);
        const m = totalMins % 60;
        return m === 0 ? `${h}h` : `${h}h ${m}min`;
    }

    /**
     * Opens or closes the side panel and shifts the map accordingly.
     * Called by the toggle button and the close button inside the panel.
     *
     * @param {boolean} open - True to open, false to close
     */
    function setPanelOpen(open) {
        document.getElementById('table-panel').classList.toggle('open', open);
        document.getElementById('panel-toggle').classList.toggle('panel-open', open);
        document.getElementById('map').classList.toggle('panel-open', open);
        // Notify Leaflet that the map container size has changed
        setTimeout(() => map.invalidateSize(), 310);
    }

    document.getElementById('panel-toggle').addEventListener('click', () => {
        const isOpen = document.getElementById('table-panel').classList.contains('open');
        setPanelOpen(!isOpen);
    });
    document.getElementById('panel-close').addEventListener('click', () => setPanelOpen(false));

    /**
     * Rebuilds the current page URL (path + query string) from routesState,
     * locationsState and colorState, preserving the other option parameters
     * that were present on the original URL (title, profile, layer, zoom,
     * section, table).
     *
     * @returns {string}
     */
    function buildShareableUrl() {
        const parts = [];

        routesState.forEach((route, i) => {
            const pts = route.map(p => {
                const o = { point: p.point };
                if (p.text) o.text = p.text;
                if (p.icon && p.icon !== defaultIconUrl) o.icon = p.icon;
                return o;
            });
            parts.push(`route=${encodeURIComponent(JSON.stringify(pts))}`);
            const colors = (colorState[i] && colorState[i].length) ? colorState[i] : ['navy'];
            parts.push(`color=${encodeURIComponent(JSON.stringify(colors))}`);
        });

        if (locationsState.length > 0) {
            const locs = locationsState.map(p => {
                const o = { point: p.point };
                if (p.text) o.text = p.text;
                if (p.icon && p.icon !== defaultIconUrl) o.icon = p.icon;
                return o;
            });
            parts.push(`location=${encodeURIComponent(JSON.stringify(locs))}`);
        }

        ['title', 'profile', 'layer', 'zoom'].forEach(key => {
            const v = urlParams.get(key);
            if (v) parts.push(`${key}=${encodeURIComponent(v)}`);
        });
        if (urlParams.get('section') === 'true') parts.push('section=true');
        if (urlParams.has('table')) parts.push('table');

        return window.location.pathname + (parts.length ? '?' + parts.join('&') : '');
    }

    /**
     * Persists the current editable state into the browser URL (without
     * reloading the page) and re-renders the map to reflect it.
     */
    function syncUrlAndRerender() {
        history.replaceState(null, '', buildShareableUrl());
        renderAll();
    }

    /**
     * Copies the current (already up to date) page URL to the clipboard.
     */
    function copyShareableUrl() {
        const fullUrl = window.location.href;
        const btn = document.getElementById('copy-url-btn');

        function markCopied() {
            const original = btn.textContent;
            btn.textContent = '✓ Copied!';
            setTimeout(() => { btn.textContent = original; }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(fullUrl).then(markCopied).catch(() => alert('Could not copy URL:\n' + fullUrl));
        } else {
            alert(fullUrl);
        }
    }

    /**
     * Swaps a route point with its neighbour in the given direction, then
     * persists and re-renders.
     *
     * @param {number} routeIndex
     * @param {number} pointIndex
     * @param {number} direction - -1 to move up, +1 to move down
     */
    function moveRoutePoint(routeIndex, pointIndex, direction) {
        const route = routesState[routeIndex];
        if (!route) return;
        const target = pointIndex + direction;
        if (target < 0 || target >= route.length) return;
        [route[pointIndex], route[target]] = [route[target], route[pointIndex]];
        syncUrlAndRerender();
    }

    /**
     * Removes a point from a route and appends it to locationsState. If the
     * route drops below two points, any remaining point is also moved to
     * locationsState and the (now unroutable) route is discarded.
     *
     * @param {number} routeIndex
     * @param {number} pointIndex
     */
    function moveRoutePointToLocation(routeIndex, pointIndex) {
        const route = routesState[routeIndex];
        if (!route) return;

        const [point] = route.splice(pointIndex, 1);
        locationsState.push(point);

        if (route.length < 2) {
            route.forEach(p => locationsState.push(p));
            routesState.splice(routeIndex, 1);
            colorState.splice(routeIndex, 1);
        }

        syncUrlAndRerender();
    }

    /**
     * Moves a standalone location marker into an existing route, at the
     * start or the end.
     *
     * @param {number} locationIndex
     * @param {number} routeIndex
     * @param {'start'|'end'} position
     */
    function moveLocationToRoute(locationIndex, routeIndex, position) {
        const route = routesState[routeIndex];
        if (!route) return;

        const [point] = locationsState.splice(locationIndex, 1);
        if (position === 'start') {
            route.unshift(point);
        } else {
            route.push(point);
        }

        syncUrlAndRerender();
    }

    /**
     * Builds the HTML for the side panel: a "copy URL" action, one section
     * per route (point-management list + segment stats table), and a
     * "Location markers" section (if any) with controls to move each one
     * into an existing route.
     */
    function buildTablePanel() {
        const content = document.getElementById('panel-content');
        content.innerHTML = '';

        const copyBtn = document.createElement('button');
        copyBtn.id = 'copy-url-btn';
        copyBtn.textContent = '\u{1F4CB} Copy shareable URL';
        copyBtn.addEventListener('click', copyShareableUrl);
        content.appendChild(copyBtn);

        routesState.forEach((route, routeIndex) => {
            const entry = tableData[routeIndex];
            if (!entry) return; // route failed to compute — nothing to show

            const section = document.createElement('div');
            section.className = 'route-section';

            const heading = document.createElement('h3');
            heading.textContent = entry.routeLabel;
            section.appendChild(heading);

            // ── Point management list ──
            const pointsList = document.createElement('div');
            pointsList.className = 'points-list';
            route.forEach((point, pointIndex) => {
                const row = document.createElement('div');
                row.className = 'point-row';
                row.innerHTML = `
                    <span class="point-label">${pointIndex + 1}. ${pointLabel(point, pointIndex)}</span>
                    <div class="point-actions">
                        <button class="btn-icon" data-action="up" ${pointIndex === 0 ? 'disabled' : ''} title="Move up">&#9650;</button>
                        <button class="btn-icon" data-action="down" ${pointIndex === route.length - 1 ? 'disabled' : ''} title="Move down">&#9660;</button>
                        <button class="btn-icon" data-action="tolocation" title="Move to a standalone location marker">&#128205;</button>
                    </div>
                `;
                row.querySelector('[data-action="up"]').addEventListener('click', () => moveRoutePoint(routeIndex, pointIndex, -1));
                row.querySelector('[data-action="down"]').addEventListener('click', () => moveRoutePoint(routeIndex, pointIndex, 1));
                row.querySelector('[data-action="tolocation"]').addEventListener('click', () => moveRoutePointToLocation(routeIndex, pointIndex));
                pointsList.appendChild(row);
            });
            section.appendChild(pointsList);

            // ── Segment stats table ──
            const table = document.createElement('table');
            table.className = 'route-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th class="num">Distance</th>
                        <th class="num">Time</th>
                    </tr>
                </thead>
            `;

            const tbody = document.createElement('tbody');
            let totalDist = 0;
            let totalDur  = 0;

            entry.segments.filter(Boolean).forEach(seg => {
                totalDist += seg.distance;
                totalDur  += seg.duration;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${seg.from}</td>
                    <td>${seg.to}</td>
                    <td class="num">${(seg.distance / 1000).toFixed(2)} km</td>
                    <td class="num">${fmtDuration(seg.duration)}</td>
                `;
                tbody.appendChild(tr);
            });

            // Totals row
            const totalMins = Math.round(totalDur / 60);
            const totalHrs  = Math.floor(totalMins / 60);
            const totalMin  = (totalMins % 60).toString().padStart(2, '0');
            const totalTr   = document.createElement('tr');
            totalTr.className = 'total-row';
            totalTr.innerHTML = `
                <td colspan="2">Total</td>
                <td class="num">${(totalDist / 1000).toFixed(2)} km</td>
                <td class="num">${totalHrs > 0 ? totalHrs + ' hr ' : ''}${totalMin} min</td>
            `;
            tbody.appendChild(totalTr);

            table.appendChild(tbody);
            section.appendChild(table);
            content.appendChild(section);
        });

        // ── Location markers section ──
        if (locationsState.length > 0) {
            const locSection = document.createElement('div');
            locSection.className = 'route-section';

            const heading = document.createElement('h3');
            heading.textContent = 'Location markers';
            locSection.appendChild(heading);

            locationsState.forEach((loc, locIndex) => {
                const row = document.createElement('div');
                row.className = 'point-row';

                const routeOptions = routesState.map((_, i) => `<option value="${i}">Route ${i + 1}</option>`).join('');
                const hasRoutes = routesState.length > 0;

                row.innerHTML = `
                    <span class="point-label">${loc.text || loc.point}</span>
                    <div class="point-actions location-move">
                        <select class="target-route" ${hasRoutes ? '' : 'disabled'}>${routeOptions}</select>
                        <select class="target-position" ${hasRoutes ? '' : 'disabled'}>
                            <option value="end" selected>End</option>
                            <option value="start">Start</option>
                        </select>
                        <button class="btn-icon" data-action="toroute" ${hasRoutes ? '' : 'disabled'} title="Move into route">&#8617;</button>
                    </div>
                `;
                row.querySelector('[data-action="toroute"]').addEventListener('click', () => {
                    const targetRoute = parseInt(row.querySelector('.target-route').value, 10);
                    const position     = row.querySelector('.target-position').value;
                    moveLocationToRoute(locIndex, targetRoute, position);
                });
                locSection.appendChild(row);
            });

            content.appendChild(locSection);
        }

        // Open the panel automatically after building
        setPanelOpen(true);
    }

    /**
     * Returns a human-readable label for a route point.
     * Uses text if available, otherwise the displayName, otherwise a fallback number.
     *
     * @param {Object} coord  - Coordinate object with optional text and displayName
     * @param {number} index  - Zero-based index of the point in the route
     * @returns {string}
     */
    function pointLabel(coord, index) {
        return coord.text || coord.displayName || `Point ${index + 1}`;
    }

    // ── Main entry point ──────────────────────────────────────────────────────
    document.addEventListener("DOMContentLoaded", function () {
        notFoundLocations = [];
        apiErrors         = [];
        routeErrors       = [];
        tableData         = [];

        const initialRoutes    = getRoutesFromQuery();
        const initialLocations = getLocationsFromQuery();
        const colorParams      = getColorParams();

        sectionFlag     = urlParams.get("section") === "true";
        profileValue    = urlParams.get("profile") || "driving-car";
        requestedZoomVal = getRequestedZoom();
        showTableFlag   = urlParams.has("table");

        // Hide the panel toggle button when ?table is not requested
        if (!showTableFlag) {
            document.getElementById('panel-toggle').style.display = 'none';
            document.getElementById('table-panel').style.display  = 'none';
        }

        // Geocode every point (routes flattened + standalone locations) once, up front
        const allSpecs = [];
        initialRoutes.forEach(route => route.forEach(p => allSpecs.push(p)));
        initialLocations.forEach(p => allSpecs.push(p));

        geocodeAll(allSpecs).then(geocoded => {
            let cursor = 0;

            initialRoutes.forEach((route, i) => {
                const resolved = route.map(() => geocoded[cursor++]);
                if (resolved.length >= 2 && resolved.every(Boolean)) {
                    routesState.push(resolved);
                    colorState.push(colorParams[i % colorParams.length]);
                }
                // Routes with a missing point are dropped; the missing address
                // is already reported via notFoundLocations below.
            });

            initialLocations.forEach(() => {
                const resolved = geocoded[cursor++];
                if (resolved) locationsState.push(resolved);
            });

            if (notFoundLocations.length > 0) {
                const list = notFoundLocations.map(loc => `• ${loc}`).join('\n');
                alert(`The following location(s) could not be found:\n\n${list}`);
            }

            return renderAll();
        }).then(() => {
            // No input at all — fall back to geolocation or Amsterdam
            if (routesState.length === 0 && locationsState.length === 0) {
                useGeolocationFallback();
            }
        });
    });

    /**
     * Clears everything previously drawn on the map and redraws it from
     * routesState / locationsState. Called on initial load and after every
     * panel edit (move / reorder). Reports any provider errors encountered
     * while (re)computing routes, fits the map to the new bounds, and
     * rebuilds the side panel when ?table is active.
     *
     * @returns {Promise}
     */
    function renderAll() {
        dynamicLayers.forEach(layer => map.removeLayer(layer));
        dynamicLayers = [];

        bounds        = L.latLngBounds();
        tableData      = [];
        totalDistance = 0;
        totalDuration = 0;
        apiErrors     = [];
        routeErrors   = [];

        const multiRoute = routesState.length > 1;

        const durationContainer = document.getElementById("duration-container");
        if (durationContainer) durationContainer.style.display = "none";

        locationsState.forEach(loc => drawLocationMarker(loc));

        const routePromises = routesState.map((route, routeIndex) =>
            drawRoute(route, routeIndex, colorState[routeIndex] || ['navy'], sectionFlag, profileValue, multiRoute, showTableFlag)
        );

        return Promise.all(routePromises).then(() => {
            if (apiErrors.length > 0) {
                const list = apiErrors.map(e => {
                    const reason = e.status === 429
                        ? 'rate limit exceeded (too many requests per minute)'
                        : 'access denied — invalid API key or daily quota exceeded';
                    return `• ${e.provider}: HTTP ${e.status} — ${reason}`;
                }).join('\n');
                alert(
                    `One or more map data providers rejected a request, most likely because a usage limit was reached:\n\n${list}\n\n` +
                    `As a result, some routes or locations may be missing from the map. Try again later, or check the API key/quota for the provider(s) listed above.`
                );
            } else if (routeErrors.length > 0) {
                const list = routeErrors.map(e => `• Route ${e.routeIndex + 1}: ${e.message}`).join('\n');
                alert(`The following route(s) could not be calculated:\n\n${list}`);
            }

            fitMap(requestedZoomVal);

            if (showTableFlag) {
                buildTablePanel();
            }
        });
    }

    // ── Map fitting ───────────────────────────────────────────────────────────

    /**
     * Fits the map to the current bounds, or forces a specific zoom level.
     *
     * @param {number|null} requestedZoom - Zoom level to force, or null to fit bounds
     */
    function fitMap(requestedZoom) {
        if (!bounds.isValid()) {
            console.warn("Bounds are not valid. No valid coordinates may have been retrieved.");
            return;
        }
        if (requestedZoom !== null) {
            map.setView(bounds.getCenter(), requestedZoom);
        } else {
            map.fitBounds(bounds);
        }
    }

    // ── Geolocation fallback ──────────────────────────────────────────────────

    /**
     * Attempts to centre the map on the user's current position.
     * Falls back to Amsterdam if geolocation is unavailable or denied.
     */
    function useGeolocationFallback() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const { latitude, longitude } = position.coords;
                    map.setView([latitude, longitude], 14);
                    L.circle([latitude, longitude], {
                        color: 'blue', fillColor: '#3f83f8', fillOpacity: 0.5, radius: 50
                    }).addTo(map).bindPopup("Your current location").openPopup();
                },
                error => {
                    console.error("Error retrieving current location:", error);
                    fallbackToAmsterdam();
                }
            );
        } else {
            console.warn("Geolocation is not supported by this browser.");
            fallbackToAmsterdam();
        }
    }

    /**
     * Centres the map on Amsterdam and shows a marker.
     * Used as the final fallback when no input and no geolocation are available.
     */
    function fallbackToAmsterdam() {
        const coords = [52.3676, 4.9041];
        map.setView(coords, 12);
        L.circle(coords, {
            color: 'blue', fillColor: '#3f83f8', fillOpacity: 0.5, radius: 50
        }).addTo(map).bindPopup("Fallback: Amsterdam").openPopup();
    }

    // ── Standalone location rendering ─────────────────────────────────────────

    /**
     * Draws a single standalone location marker on the map. Coordinates are
     * assumed to already be geocoded (see geocodeAll). The lone/prominent
     * "start" icon is used only when this is literally the only location
     * marker currently on the map and no custom icon was set.
     *
     * @param {Object} loc - Geocoded location point { point, text, icon, lat, lon }
     */
    function drawLocationMarker(loc) {
        const iconUrl = (locationsState.length === 1 && !loc.icon) ? startIconUrl : (loc.icon || defaultIconUrl);

        const marker = L.marker([loc.lat, loc.lon], {
            icon: L.icon({
                iconUrl,
                iconSize:    [iconWidth, iconHeight],
                iconAnchor:  [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            })
        }).addTo(map);
        dynamicLayers.push(marker);

        marker.bindPopup(loc.text || loc.point);
        marker.on('mouseover', function () { this.openPopup(); });
        marker.on('mouseout',  function () { this.closePopup(); });
        bounds.extend([loc.lat, loc.lon]);
    }

    // ── Route rendering ───────────────────────────────────────────────────────

    /**
     * Returns a cache key that uniquely identifies a route's ordered sequence
     * of coordinates. Two routes (or the same route before/after an edit that
     * didn't change point order) with identical keys have identical geometry,
     * so a previously computed result can be reused without calling ORS again.
     *
     * @param {Object[]} route
     * @returns {string}
     */
    function routeCacheKey(route) {
        return route.map(p => `${p.lat.toFixed(6)},${p.lon.toFixed(6)}`).join('>');
    }

    /**
     * Draws one route on the map: fetches (or reuses cached) total route data
     * and per-segment geometry from OpenRouteService, then plots each segment.
     * Populates a tableData entry when table mode is active.
     *
     * @param {Object[]} route         - Array of geocoded point objects
     * @param {number} routeIndex      - Zero-based index of this route
     * @param {string[]} colors        - Color array for this route
     * @param {boolean} section        - Show per-segment stats in waypoint popups
     * @param {string} profile         - OpenRouteService routing profile
     * @param {boolean} multiRoute     - True when more than one route is being rendered
     * @param {boolean} showTable      - True when ?table is present in the URL
     * @returns {Promise}
     */
    function drawRoute(route, routeIndex, colors, section, profile, multiRoute, showTable) {
        if (route.length < 2) return Promise.resolve();

        let tableEntry = null;
        if (showTable) {
            const startLabel = pointLabel(route[0], 0);
            const routeLabel = multiRoute ? `Route ${routeIndex + 1} — ${startLabel}` : startLabel;
            tableEntry = { routeLabel, segments: [] };
            tableData[routeIndex] = tableEntry;
        }

        const cacheKey = routeCacheKey(route);
        const cached   = routeGeometryCache.get(cacheKey);

        const totalPromise = cached
            ? Promise.resolve(cached.total)
            : calculateTotalRouteData(route, profile);

        return totalPromise
            .then(({ totalDuration: routeDuration, totalDistance: routeDistance }) => {
                const segmentPromises = [];
                for (let i = 0; i < route.length - 1; i++) {
                    const color     = getSegmentColor(colors, i);
                    const cachedLeg = cached ? cached.legs[i] : null;
                    segmentPromises.push(plotSegment(
                        route[i],
                        route[i + 1],
                        color,
                        section,
                        profile,
                        i === 0,
                        i === route.length - 2,
                        routeDuration,
                        routeDistance,
                        multiRoute,
                        tableEntry,
                        i,
                        cachedLeg
                    ));
                }
                return Promise.all(segmentPromises).then(legs => {
                    routeGeometryCache.set(cacheKey, {
                        total: { totalDuration: routeDuration, totalDistance: routeDistance },
                        legs
                    });
                });
            })
            .catch(error => {
                console.error("Error processing route:", error);
                if (!error.isApiLimit) {
                    routeErrors.push({ routeIndex, message: error.message });
                }
            });
    }

    /**
     * Draws a single route segment on the map (polyline + start/end markers),
     * and appends its data to tableEntry when table mode is active. When
     * cachedLeg is provided, the previously fetched geometry/distance/duration
     * is reused and no OpenRouteService request is made.
     *
     * Start marker behaviour:
     *   - Multi-route: popup shows total distance and travel time for this route.
     *   - Single-route: popup shows the start label; totals go to the overlay.
     *
     * End/waypoint marker: optionally shows per-segment distance and time (section=true).
     *
     * @param {Object} start           - Start coordinate { lat, lon, text, icon, displayName }
     * @param {Object} end             - End coordinate
     * @param {string} color           - Polyline CSS color
     * @param {boolean} section        - Show per-segment stats in waypoint popups
     * @param {string} profile         - OpenRouteService routing profile
     * @param {boolean} isFirstSegment - Render the start marker
     * @param {boolean} isLastSegment  - Use end/finish icon; update single-route overlay
     * @param {number} routeDuration   - Pre-calculated total route duration in seconds
     * @param {number} routeDistance   - Pre-calculated total route distance in metres
     * @param {boolean} multiRoute     - True when more than one route is being rendered
     * @param {Object|null} tableEntry - tableData entry to append segment data to, or null
     * @param {number} segmentIndex    - Zero-based index of this segment within the route
     * @param {Object|null} cachedLeg  - Previously computed { distance, duration, coords }, if any
     * @returns {Promise<{distance: number, duration: number, coords: Array}>}
     */
    function plotSegment(start, end, color, section, profile, isFirstSegment, isLastSegment,
                         routeDuration, routeDistance, multiRoute, tableEntry, segmentIndex, cachedLeg) {
        const draw = (routeCoords, distance, duration) => {
            const startIcon = L.icon({
                iconUrl:     start.icon ? start.icon : (isFirstSegment ? startIconUrl : defaultIconUrl),
                iconSize:    [iconWidth, iconHeight],
                iconAnchor:  [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            });

            const endIcon = L.icon({
                iconUrl:     end.icon || (isLastSegment ? endIconUrl : defaultIconUrl),
                iconSize:    [iconWidth, iconHeight],
                iconAnchor:  [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            });

            const polyline = L.polyline(routeCoords, { color, weight: 5 }).addTo(map);
            dynamicLayers.push(polyline);

            routeCoords.forEach(c => bounds.extend(c));
            bounds.extend([start.lat, start.lon]);
            bounds.extend([end.lat, end.lon]);

            // Collect segment data for the table panel (indexed to preserve order)
            if (tableEntry) {
                tableEntry.segments[segmentIndex] = {
                    from:     pointLabel(start, segmentIndex),
                    to:       pointLabel(end,   segmentIndex + 1),
                    distance,
                    duration
                };
            }

            // Start marker (first segment only)
            if (isFirstSegment) {
                const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);
                dynamicLayers.push(startMarker);
                let startPopupText = start.text || `Start: ${start.displayName}`;

                if (multiRoute) {
                    const distKm   = (routeDistance / 1000).toFixed(2);
                    const mins     = Math.round(routeDuration / 60);
                    const timeText = `${Math.floor(mins / 60)} hr ${mins % 60} min`;
                    startPopupText += `
                        <br><hr>
                        <strong>Total distance:</strong> ${distKm} km<br>
                        <strong>Total travel time:</strong> ${timeText}
                    `;
                }

                startMarker.bindPopup(startPopupText);
                startMarker.on('mouseover', function () { this.openPopup(); });
                startMarker.on('mouseout',  function () { this.closePopup(); });
            }

            // Accumulate totals for the single-route overlay
            totalDistance += distance;
            totalDuration += duration;

            // End / waypoint marker
            let endPopupText = end.text || `Waypoint: ${end.displayName}`;
            if (section) {
                const distKm = (distance / 1000).toFixed(2);
                endPopupText += `
                    <br><hr>
                    <strong>Segment distance:</strong> ${distKm} km<br>
                    <strong>Segment time:</strong> ${fmtDuration(duration)}
                `;
            }

            const endMarker = L.marker([end.lat, end.lon], { icon: endIcon }).addTo(map);
            dynamicLayers.push(endMarker);
            endMarker.bindPopup(endPopupText);
            endMarker.on('mouseover', function () { this.openPopup(); });
            endMarker.on('mouseout',  function () { this.closePopup(); });

            // Single-route overlay: update on the last segment
            if (!multiRoute && isLastSegment) {
                const totalMins    = Math.round(totalDuration / 60);
                const totalHours   = Math.floor(totalMins / 60);
                const totalMinutes = (totalMins % 60).toString().padStart(2, '0');
                const timeText     = `${totalHours} hr ${totalMinutes} min`;
                const distKm       = (totalDistance / 1000).toFixed(2);

                const now         = new Date();
                const arrival     = new Date(now.getTime() + totalDuration * 1000);
                const arrivalText = `${arrival.getHours().toString().padStart(2,'0')}:${arrival.getMinutes().toString().padStart(2,'0')}`;

                const container = document.getElementById("duration-container");
                if (container) {
                    container.innerHTML = `
                        <div><strong>Total distance:</strong> ${distKm} km</div>
                        <div><strong>Travel time:</strong> ${timeText}</div>
                        <div><strong>Estimated arrival:</strong> ${arrivalText}</div>
                    `;
                    container.style.display = "block";
                }
            }

            return { distance, duration, coords: routeCoords };
        };

        if (cachedLeg) {
            return Promise.resolve(draw(cachedLeg.coords, cachedLeg.distance, cachedLeg.duration));
        }

        const routeUrl = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${start.lon},${start.lat}&end=${end.lon},${end.lat}`;

        return fetch(routeUrl)
            .then(r => {
                if (!r.ok) throwFetchError('OpenRouteService (routing)', r, 'HTTP error');
                return r.json();
            })
            .then(routeData => {
                const routeCoords = routeData.features[0].geometry.coordinates.map(c => [c[1], c[0]]);
                const duration    = routeData.features[0].properties.segments[0].duration;
                const distance    = routeData.features[0].properties.segments[0].distance;
                return draw(routeCoords, distance, duration);
            })
            .catch(error => {
                console.error("Error fetching route:", error);
                throw error;
            });
    }

    /**
     * Fetches total distance and duration for a route by querying ORS directly
     * from the first to the last coordinate (ignoring intermediate waypoints).
     * Used to pre-populate start marker popups in multi-route mode.
     *
     * @param {Object[]} route  - Array of coordinate objects with lat/lon
     * @param {string} profile  - OpenRouteService routing profile
     * @returns {Promise<{totalDuration: number, totalDistance: number}>}
     */
    function calculateTotalRouteData(route, profile) {
        const first = route[0];
        const last  = route[route.length - 1];
        const url   = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${first.lon},${first.lat}&end=${last.lon},${last.lat}`;

        return fetch(url)
            .then(r => {
                if (!r.ok) throwFetchError('OpenRouteService (routing)', r, 'Error fetching total route data');
                return r.json();
            })
            .then(data => {
                const segments      = data.features[0].properties.segments;
                const totalDuration = segments.reduce((sum, s) => sum + s.duration, 0);
                const totalDistance = segments.reduce((sum, s) => sum + s.distance, 0);
                return { totalDuration, totalDistance };
            });
    }
</script>

</body>
</html>
