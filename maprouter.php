<!DOCTYPE html>
<html lang="en">
<head>
    <!-- (c) Webwings 2025 -->
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

    /**
     * tableData holds the collected segment data for the side panel.
     * Structure: [ { routeLabel, segments: [ { from, to, distance, duration } ] } ]
     */
    let tableData = [];

    // ── Map initialisation ──────────────────────────────────────────────────
    var map = L.map('map').setView([52.3676, 4.9041], 10);

    // ── URL parameter parsing ───────────────────────────────────────────────
    const urlParams = new URLSearchParams(window.location.search);

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
                tileLayerUrl         = `https://tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            case "transport":
                tileLayerUrl         = `https://tile.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
                tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
                break;
            default:
                tileLayerUrl         = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
                tileLayerAttribution = "&copy; OpenStreetMap contributors";
                break;
        }

        L.tileLayer(tileLayerUrl, { attribution: tileLayerAttribution }).addTo(map);
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
     * Returns the CSS color string for a given route and segment index.
     * Cycles through color arrays (per route) and colors within each array (per segment).
     *
     * @param {string[][]} colorParams - Array of color arrays
     * @param {number} routeIndex      - Zero-based route index
     * @param {number} segmentIndex    - Zero-based segment index within the route
     * @returns {string}
     */
    function getSegmentColor(colorParams, routeIndex, segmentIndex) {
        const colors = colorParams[routeIndex % colorParams.length];
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

    // ── Side panel ────────────────────────────────────────────────────────────

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
     * Builds the HTML for the side panel from the collected tableData and injects it.
     * Called after all routes have been rendered.
     * Each route gets its own section with a segment table and a totals row.
     */
    function buildTablePanel() {
        const content = document.getElementById('panel-content');
        content.innerHTML = '';

        tableData.forEach(route => {
            const section = document.createElement('div');
            section.className = 'route-section';

            const heading = document.createElement('h3');
            heading.textContent = route.routeLabel;
            section.appendChild(heading);

            const table = document.createElement('table');
            table.className = 'route-table';

            // Header row
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

            route.segments.forEach(seg => {
                totalDist += seg.distance;
                totalDur  += seg.duration;

                const mins = Math.round(seg.duration / 60);
                const tr   = document.createElement('tr');
                tr.innerHTML = `
                    <td>${seg.from}</td>
                    <td>${seg.to}</td>
                    <td class="num">${(seg.distance / 1000).toFixed(2)} km</td>
                    <td class="num">${mins} min</td>
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
        tableData         = [];

        const routes        = getRoutesFromQuery();
        const locations     = getLocationsFromQuery();
        const section       = urlParams.get("section") === "true";
        const profile       = urlParams.get("profile") || "driving-car";
        const requestedZoom = getRequestedZoom();
        const colorParams   = getColorParams();
        const multiRoute    = routes.length > 1;
        const showTable     = urlParams.has("table");

        // Hide the panel toggle button when ?table is not requested
        if (!showTable) {
            document.getElementById('panel-toggle').style.display = 'none';
            document.getElementById('table-panel').style.display  = 'none';
        }

        // In multi-route mode, remove the single-route overlay from the DOM
        if (multiRoute) {
            const container = document.getElementById("duration-container");
            if (container) container.remove();
        }

        const promises = [];

        // Process standalone location markers
        if (locations.length > 0) {
            promises.push(renderLocations(locations));
        }

        // Process each route
        routes.forEach((route, index) => {
            if (route.length > 0) {
                promises.push(plotRoutes(route, section, profile, index, colorParams, multiRoute, showTable));
            }
        });

        // After all rendering: report errors, fit map, build table panel if requested
        Promise.all(promises).then(() => {
            if (notFoundLocations.length > 0) {
                const list = notFoundLocations.map(loc => `• ${loc}`).join('\n');
                alert(`The following location(s) could not be found:\n\n${list}`);
            }
            fitMap(requestedZoom);

            if (showTable && tableData.length > 0) {
                buildTablePanel();
            }
        });

        // No input at all — fall back to geolocation or Amsterdam
        if (routes.length === 0 && locations.length === 0) {
            useGeolocationFallback();
        }
    });

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
     * Geocodes an array of standalone locations via Nominatim and adds markers to the map.
     * Missing locations are recorded in notFoundLocations.
     *
     * @param {Object[]} locations - Array of location point objects { point, text, icon }
     * @returns {Promise}
     */
    function renderLocations(locations) {
        return new Promise((resolve) => {
            const fetches = locations.map(location => {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location.point)}`;
                return fetch(url).then(r => {
                    if (!r.ok) throw new Error(`Error fetching location: ${r.status}`);
                    return r.json();
                });
            });

            Promise.all(fetches)
                .then(results => {
                    results.forEach((result, index) => {
                        if (result.length > 0) {
                            const coord    = { lat: parseFloat(result[0].lat), lon: parseFloat(result[0].lon) };
                            const location = locations[index];
                            const iconUrl  = (locations.length === 1 && !location.icon) ? startIconUrl : (location.icon || defaultIconUrl);

                            const marker = L.marker([coord.lat, coord.lon], {
                                icon: L.icon({
                                    iconUrl:     iconUrl,
                                    iconSize:    [iconWidth, iconHeight],
                                    iconAnchor:  [iconWidth / 2, iconHeight],
                                    popupAnchor: [0, popupOffset]
                                })
                            }).addTo(map);

                            marker.bindPopup(location.text || location.point);
                            marker.on('mouseover', function () { this.openPopup(); });
                            marker.on('mouseout',  function () { this.closePopup(); });
                            bounds.extend([coord.lat, coord.lon]);
                        } else {
                            console.error("Location not found: " + locations[index].point);
                            notFoundLocations.push(locations[index].point);
                        }
                    });
                    resolve();
                })
                .catch(error => {
                    console.error("Error fetching location data:", error);
                    resolve();
                });
        });
    }

    // ── Route rendering ───────────────────────────────────────────────────────

    /**
     * Geocodes all points in a route, pre-calculates totals, then draws each segment.
     * Also initialises a tableData entry for this route if ?table is active.
     *
     * @param {Object[]} route         - Array of route point objects { point, text, icon }
     * @param {boolean} section        - Show per-segment stats in waypoint popups
     * @param {string} profile         - OpenRouteService routing profile
     * @param {number} routeIndex      - Zero-based index of this route
     * @param {string[][]} colorParams - Parsed color arrays
     * @param {boolean} multiRoute     - True when more than one route is being rendered
     * @param {boolean} showTable      - True when ?table is present in the URL
     * @returns {Promise}
     */
    function plotRoutes(route, section, profile, routeIndex, colorParams, multiRoute, showTable) {
        const fetches = route.map(point => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(point.point)}`;
            return fetch(url).then(r => {
                if (!r.ok) throw new Error(`Error fetching location: ${r.status}`);
                return r.json();
            });
        });

        return Promise.all(fetches)
            .then(locations => {
                const coordinates  = [];
                const localNotFound = [];

                locations.forEach((location, index) => {
                    if (location.length > 0) {
                        coordinates.push({
                            lat:         parseFloat(location[0].lat),
                            lon:         parseFloat(location[0].lon),
                            displayName: location[0].display_name.split(",")[0],
                            text:        route[index].text,
                            icon:        route[index].icon
                        });
                    } else {
                        console.error("Location not found: " + route[index].point);
                        localNotFound.push(route[index].point);
                        notFoundLocations.push(route[index].point);
                    }
                });

                if (localNotFound.length > 0) {
                    throw new Error("Some locations could not be found: " + localNotFound.join(", "));
                }

                // Prepare a tableData slot for this route (filled per segment below)
                let tableEntry = null;
                if (showTable) {
                    const startLabel = pointLabel(coordinates[0], 0);
                    const routeLabel = multiRoute
                        ? `Route ${routeIndex + 1} — ${startLabel}`
                        : startLabel;
                    tableEntry = { routeLabel, segments: [] };
                    tableData.push(tableEntry);
                }

                return calculateTotalRouteData(coordinates, profile)
                    .then(({ totalDuration: routeDuration, totalDistance: routeDistance }) => {
                        const segmentPromises = [];
                        for (let i = 0; i < coordinates.length - 1; i++) {
                            const color = getSegmentColor(colorParams, routeIndex, i);
                            segmentPromises.push(plotSegment(
                                coordinates[i],
                                coordinates[i + 1],
                                color,
                                section,
                                profile,
                                i === 0,
                                i === coordinates.length - 2,
                                routeDuration,
                                routeDistance,
                                multiRoute,
                                tableEntry,
                                i
                            ));
                        }
                        return Promise.all(segmentPromises);
                    });
            })
            .catch(error => console.error("Error processing route:", error));
    }

    /**
     * Fetches a single route segment from OpenRouteService and draws it on the map.
     * Appends segment data to tableEntry when table mode is active.
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
     * @returns {Promise<{distance: number, duration: number}>}
     */
    function plotSegment(start, end, color, section, profile, isFirstSegment, isLastSegment,
                         routeDuration, routeDistance, multiRoute, tableEntry, segmentIndex) {
        return new Promise((resolve, reject) => {
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

            const routeUrl = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${start.lon},${start.lat}&end=${end.lon},${end.lat}`;

            fetch(routeUrl)
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP error! Status: ${r.status}`);
                    return r.json();
                })
                .then(routeData => {
                    const routeCoords = routeData.features[0].geometry.coordinates.map(c => [c[1], c[0]]);
                    L.polyline(routeCoords, { color, weight: 5 }).addTo(map);

                    routeCoords.forEach(c => bounds.extend(c));
                    bounds.extend([start.lat, start.lon]);
                    bounds.extend([end.lat, end.lon]);

                    const duration = routeData.features[0].properties.segments[0].duration;
                    const distance = routeData.features[0].properties.segments[0].distance;

                    // Collect segment data for the table panel
                    if (tableEntry) {
                        tableEntry.segments.push({
                            from:     pointLabel(start, segmentIndex),
                            to:       pointLabel(end,   segmentIndex + 1),
                            distance,
                            duration
                        });
                    }

                    // Start marker (first segment only)
                    if (isFirstSegment) {
                        const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);
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
                        const mins   = Math.round(duration / 60);
                        endPopupText += `
                            <br><hr>
                            <strong>Segment distance:</strong> ${distKm} km<br>
                            <strong>Segment time:</strong> ${mins} min
                        `;
                    }

                    const endMarker = L.marker([end.lat, end.lon], { icon: endIcon }).addTo(map);
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

                    resolve({ distance, duration });
                })
                .catch(error => {
                    console.error("Error fetching route:", error);
                    reject(error);
                });
        });
    }

    /**
     * Fetches total distance and duration for a route by querying ORS directly
     * from the first to the last coordinate (ignoring intermediate waypoints).
     * Used to pre-populate start marker popups in multi-route mode.
     *
     * @param {Object[]} coordinates - Array of coordinate objects with lat/lon
     * @param {string} profile       - OpenRouteService routing profile
     * @returns {Promise<{totalDuration: number, totalDistance: number}>}
     */
    function calculateTotalRouteData(coordinates, profile) {
        const first = coordinates[0];
        const last  = coordinates[coordinates.length - 1];
        const url   = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${first.lon},${first.lat}&end=${last.lon},${last.lat}`;

        return fetch(url)
            .then(r => {
                if (!r.ok) throw new Error(`Error fetching total route data: ${r.status}`);
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
