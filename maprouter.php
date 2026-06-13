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
        #map { height: 100vh; width: 100vw; }

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
            font-weight: regular;
        }
    </style>
</head>
<body>

<!-- Total distance and time overlay (single-route mode only) -->
<div id="duration-container"></div>

<!-- Map -->
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="config.js"></script>
<script>
    /**
     * maprouter.php
     *
     * Unified map routing script — merges the functionality of getroute.php
     * and multiroute.php into a single file.
     *
     * Single route  : shows a duration/distance overlay with estimated arrival time.
     * Multiple routes: shows per-route distance and travel time in each start marker popup.
     *
     * Libraries used:
     *   Leaflet.js          — interactive map rendering
     *   OpenRouteService    — route calculation (API key required)
     *   Nominatim           — geocoding of address strings (no key required)
     *   Tracestrack         — topographic tile layer (API key required)
     *   Thunderforest       — cycling/transport tile layers (API key required)
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
    let notFoundLocations = [];               // Accumulates location names that could not be geocoded

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
                tileLayerUrl         = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"; // Default OSM layer
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
     * one per route. Each color array cycles through per-segment colors.
     * Falls back to ['navy'] if no color parameters are present.
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
     * @param {string[][]} colorParams  - Array of color arrays
     * @param {number} routeIndex       - Zero-based route index
     * @param {number} segmentIndex     - Zero-based segment index within the route
     * @returns {string}
     */
    function getSegmentColor(colorParams, routeIndex, segmentIndex) {
        const colors = colorParams[routeIndex % colorParams.length];
        return colors[segmentIndex % colors.length];
    }

    /**
     * Parses all ?route= URL parameters and returns an array of route arrays.
     * Supports multiple routes by repeating the route parameter in the query string.
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
     * Parses the ?location= URL parameter and returns an array of standalone location points.
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

    // ── Main entry point ─────────────────────────────────────────────────────
    document.addEventListener("DOMContentLoaded", function () {
        notFoundLocations = [];

        const routes        = getRoutesFromQuery();
        const locations     = getLocationsFromQuery();
        const section       = urlParams.get("section") === "true";
        const profile       = urlParams.get("profile") || "driving-car";
        const requestedZoom = getRequestedZoom();
        const colorParams   = getColorParams();
        const multiRoute    = routes.length > 1;

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
                promises.push(plotRoutes(route, section, profile, index, colorParams, multiRoute));
            }
        });

        // After all rendering is done, fit the map and report missing locations
        Promise.all(promises).then(() => {
            if (notFoundLocations.length > 0) {
                const list = notFoundLocations.map(loc => `• ${loc}`).join('\n');
                alert(`The following location(s) could not be found:\n\n${list}`);
            }
            fitMap(requestedZoom);
        });

        // No input at all — fall back to geolocation or Amsterdam
        if (routes.length === 0 && locations.length === 0) {
            useGeolocationFallback();
        }
    });

    // ── Map fitting ──────────────────────────────────────────────────────────

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

    // ── Geolocation fallback ─────────────────────────────────────────────────

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

    // ── Standalone location rendering ────────────────────────────────────────

    /**
     * Geocodes an array of standalone locations via Nominatim and adds markers to the map.
     * Missing locations are added to notFoundLocations for reporting after all rendering.
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

    // ── Route rendering ──────────────────────────────────────────────────────

    /**
     * Geocodes all points in a route, pre-calculates totals, then draws each segment.
     * In multi-route mode, totals are shown in the start marker popup.
     * In single-route mode, totals are shown in the bottom-right overlay.
     *
     * @param {Object[]} route       - Array of route point objects { point, text, icon }
     * @param {boolean} section      - Show per-segment stats in waypoint popups
     * @param {string} profile       - OpenRouteService routing profile
     * @param {number} routeIndex    - Zero-based index of this route (for color selection)
     * @param {string[][]} colorParams - Parsed color arrays
     * @param {boolean} multiRoute   - True when more than one route is being rendered
     * @returns {Promise}
     */
    function plotRoutes(route, section, profile, routeIndex, colorParams, multiRoute) {
        const fetches = route.map(point => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(point.point)}`;
            return fetch(url).then(r => {
                if (!r.ok) throw new Error(`Error fetching location: ${r.status}`);
                return r.json();
            });
        });

        return Promise.all(fetches)
            .then(locations => {
                const coordinates = [];
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

                // Pre-calculate total distance/duration for the start marker popup (multi-route)
                // or for the overlay (single-route)
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
                                multiRoute
                            ));
                        }
                        return Promise.all(segmentPromises);
                    });
            })
            .catch(error => console.error("Error processing route:", error));
    }

    /**
     * Fetches a single route segment from OpenRouteService and draws it on the map.
     *
     * Start marker behaviour:
     *   - Multi-route mode: popup shows total distance and travel time for this route.
     *   - Single-route mode: popup shows the start label only; totals go to the overlay.
     *
     * End/waypoint marker: optionally shows per-segment distance and time when section=true.
     *
     * @param {Object} start           - Start coordinate { lat, lon, text, icon, displayName }
     * @param {Object} end             - End coordinate
     * @param {string} color           - Polyline CSS color
     * @param {boolean} section        - Show per-segment stats in waypoint popups
     * @param {string} profile         - OpenRouteService routing profile
     * @param {boolean} isFirstSegment - Render the start marker
     * @param {boolean} isLastSegment  - Use the end/finish icon on the final marker
     * @param {number} routeDuration   - Pre-calculated total route duration in seconds
     * @param {number} routeDistance   - Pre-calculated total route distance in metres
     * @param {boolean} multiRoute     - True when more than one route is being rendered
     * @returns {Promise<{distance: number, duration: number}>}
     */
    function plotSegment(start, end, color, section, profile, isFirstSegment, isLastSegment, routeDuration, routeDistance, multiRoute) {
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

                    // Start marker (first segment only)
                    if (isFirstSegment) {
                        const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);

                        let startPopupText = start.text || `Start: ${start.displayName}`;

                        if (multiRoute) {
                            // Multi-route: embed total stats in the start popup
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

                    // Single-route mode: update the bottom-right overlay on the last segment
                    if (!multiRoute && isLastSegment) {
                        const totalMins    = Math.round(totalDuration / 60);
                        const totalHours   = Math.floor(totalMins / 60);
                        const totalMinutes = (totalMins % 60).toString().padStart(2, '0');
                        const timeText     = `${totalHours} hr ${totalMinutes} min`;
                        const distKm       = (totalDistance / 1000).toFixed(2);

                        const now          = new Date();
                        const arrival      = new Date(now.getTime() + totalDuration * 1000);
                        const arrivalText  = `${arrival.getHours().toString().padStart(2,'0')}:${arrival.getMinutes().toString().padStart(2,'0')}`;

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
                const segments     = data.features[0].properties.segments;
                const totalDuration = segments.reduce((sum, s) => sum + s.duration, 0);
                const totalDistance = segments.reduce((sum, s) => sum + s.distance, 0);
                return { totalDuration, totalDistance };
            });
    }
</script>

</body>
</html>
