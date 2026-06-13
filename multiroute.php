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

<!-- Total distance and time container (used for single-route mode only) -->
<!-- div id="duration-container"></div -->

<!-- Map -->
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="config.js"></script>
<script>
    // Load API keys from config
    const thunderforestApiKey = config.thunderforestApiKey;
    const tracestrackApiKey = config.tracestrackApiKey;
    const openRouteServiceApiKey = config.openRouteServiceApiKey;

    // Default icon URLs and dimensions
    const defaultIconUrl = 'https://code.webwings.nl/pinpoint.png';
    const startIconUrl = 'https://code.webwings.nl/start.png';
    const endIconUrl = 'https://code.webwings.nl/stop.png';
    const iconWidth = 32;  // Icon width in pixels
    const iconHeight = 37; // Icon height in pixels
    const popupOffset = -35; // Vertical offset to position the popup above the marker

    /**
     * Legacy single-route parser — kept for reference but not used in multiroute.php.
     * Use getRoutesFromQuery() instead.
     */
    function getRouteFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const routeParam = urlParams.get("route");
        if (!routeParam) {
            return []; // No route parameter present, return empty array silently
        }

        try {
            const route = JSON.parse(routeParam);
            if (!Array.isArray(route)) {
                throw new Error("Route must be an array.");
            }
            return route.map(point => {
                if (!point.point) {
                    throw new Error("Each point must have a 'point' property.");
                }
                return {
                    point: point.point,
                    text: point.text || null,
                    icon: point.icon || defaultIconUrl
                };
            });
        } catch (error) {
            alert("Error processing route: " + error.message);
            return [];
        }
    }

    /**
     * Parses all ?route= URL parameters and returns an array of route arrays.
     * Supports multiple routes by repeating the route parameter in the query string.
     * Each route is an array of point objects (point, text, icon).
     */
    function getRoutesFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const routes = [];

        // Collect all 'route' parameters from the query string
        urlParams.forEach((value, key) => {
            if (key === "route") {
                try {
                    const route = JSON.parse(value);
                    if (!Array.isArray(route)) {
                        throw new Error("Route must be an array.");
                    }
                    routes.push(
                        route.map(point => {
                            if (!point.point) {
                                throw new Error("Each point must have a 'point' property.");
                            }
                            return {
                                point: point.point,
                                text: point.text || null,
                                icon: point.icon || defaultIconUrl
                            };
                        })
                    );
                } catch (error) {
                    console.error("Error processing route:", error.message);
                }
            }
        });

        return routes;
    }

    /**
     * Parses the ?location= URL parameter and returns an array of standalone location points.
     * Each point contains a location string, optional popup text, and optional icon URL.
     * Returns an empty array if the parameter is missing or invalid.
     */
    function getLocationsFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const locationParam = urlParams.get("location");
        if (!locationParam) {
            return []; // No location parameter present, return empty array
        }

        try {
            const locations = JSON.parse(locationParam);
            if (!Array.isArray(locations)) {
                throw new Error("Locations must be an array.");
            }
            return locations.map(point => {
                if (!point.point) {
                    throw new Error("Each point must have a 'point' property.");
                }
                return {
                    point: point.point,
                    text: point.text || null,
                    icon: point.icon || defaultIconUrl
                };
            });
        } catch (error) {
            alert("Error processing locations: " + error.message);
            return [];
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Reset the not-found locations list on each page load
        notFoundLocations = [];

        const routes = getRoutesFromQuery(); // Retrieve all routes from the query string
        const locations = getLocationsFromQuery();
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get("section") === "true";
        const profile = urlParams.get("profile") || "driving-car";
        const zoomParam = urlParams.get("zoom");
        let requestedZoom = null;

        if (zoomParam) {
            requestedZoom = parseInt(zoomParam, 10);
            if (isNaN(requestedZoom)) {
                console.warn("Invalid zoom value provided. Value will be ignored.");
                requestedZoom = null;
            }
        }

        const promises = [];

        // Remove the duration container from the DOM when multiple routes are shown
        const durationContainer = document.getElementById("duration-container");
        if (durationContainer && routes.length > 1) {
            durationContainer.remove();
        }

        // Process standalone locations
        if (locations.length > 0) {
            promises.push(
                new Promise((resolve) => {
                    const locationPromises = locations.map(location => {
                        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location.point)}`;
                        return fetch(url).then(response => {
                            if (!response.ok) {
                                throw new Error(`Error fetching location: ${response.status}`);
                            }
                            return response.json();
                        });
                    });

                    Promise.all(locationPromises)
                        .then(results => {
                            results.forEach((result, index) => {
                                if (result.length > 0) {
                                    const coord = {
                                        lat: parseFloat(result[0].lat),
                                        lon: parseFloat(result[0].lon)
                                    };
                                    const location = locations[index];

                                    const iconUrl = (locations.length === 1 && !location.icon) ? startIconUrl : (location.icon || defaultIconUrl);

                                    const marker = L.marker([coord.lat, coord.lon], {
                                        icon: L.icon({
                                            iconUrl: iconUrl,
                                            iconSize: [iconWidth, iconHeight],
                                            iconAnchor: [iconWidth / 2, iconHeight],
                                            popupAnchor: [0, popupOffset]
                                        })
                                    }).addTo(map);

                                    marker.bindPopup(location.text || location.point);
                                    marker.on('mouseover', function () {
                                        this.openPopup();
                                    });
                                    marker.on('mouseout', function () {
                                        this.closePopup();
                                    });

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
                })
            );
        }

        // Process each route, passing its index for per-route color selection
        routes.forEach((route, index) => {
            if (route.length > 0) {
                promises.push(plotRoutes(route, section, profile, index));
            }
        });

        // Wait for all locations and routes to finish, then fit the map
        Promise.all(promises).then(() => {
            // Alert the user about any locations that could not be found
            if (notFoundLocations.length > 0) {
                const locationList = notFoundLocations.map(loc => `• ${loc}`).join('\n');
                alert(`The following location(s) could not be found:\n\n${locationList}`);
            }

            if (bounds.isValid()) {
                if (requestedZoom !== null) {
                    // Force the map to the requested zoom level
                    map.setView(bounds.getCenter(), requestedZoom);
                } else {
                    // Fit the map to the bounds of all rendered elements
                    map.fitBounds(bounds);
                }
            } else {
                console.warn("Bounds are not valid. No valid coordinates may have been retrieved.");
            }
        });

        // If no routes or locations are provided, try to use the browser's geolocation
        if (routes.length === 0 && locations.length === 0) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const { latitude, longitude } = position.coords;
                        map.setView([latitude, longitude], 14);

                        // Show a blue circle marker at the current location
                        L.circle([latitude, longitude], {
                            color: 'blue',
                            fillColor: '#3f83f8',
                            fillOpacity: 0.5,
                            radius: 50
                        }).addTo(map).bindPopup("Your current location").openPopup();
                    },
                    error => {
                        console.error("Error retrieving current location:", error);
                        // Fall back to Amsterdam
                        fallbackToAmsterdam();
                    }
                );
            } else {
                console.warn("Geolocation is not supported by this browser.");
                // Fall back to Amsterdam
                fallbackToAmsterdam();
            }
        }

        console.log("Bounds:", bounds);
        console.log("Routes:", routes);
        console.log("Locations:", locations);
        console.log("Duration container present?", !!document.getElementById("duration-container"));
    });

    /**
     * Falls back to Amsterdam when geolocation is unavailable or fails.
     * Centers the map on Amsterdam and shows a marker.
     */
    function fallbackToAmsterdam() {
        const amsterdamCoords = [52.3676, 4.9041];
        map.setView(amsterdamCoords, 12);

        // Show a blue circle marker at Amsterdam
        L.circle(amsterdamCoords, {
            color: 'blue',
            fillColor: '#3f83f8',
            fillOpacity: 0.5,
            radius: 50
        }).addTo(map).bindPopup("Fallback: Amsterdam").openPopup();
    }

    var map = L.map('map').setView([52.3676, 4.9041], 10);

    // Select the tile layer based on the ?layer= URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const layer = urlParams.get("layer");

    let tileLayerUrl, tileLayerAttribution;

    switch (layer) {
        case "topo":
            tileLayerUrl = `https://tile.tracestrack.com/topo__/{z}/{x}/{y}.png?key=${tracestrackApiKey}`;
            tileLayerAttribution = "&copy; Tracestrack contributors";
            break;
        case "cycle":
            tileLayerUrl = `https://tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
            tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
            break;
        case "transport":
            tileLayerUrl = `https://tile.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=${thunderforestApiKey}`;
            tileLayerAttribution = "&copy; Thunderforest, OpenStreetMap contributors";
            break;
        default:
            tileLayerUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"; // Default OSM layer
            tileLayerAttribution = "&copy; OpenStreetMap contributors";
            break;
    }

    L.tileLayer(tileLayerUrl, {
        attribution: tileLayerAttribution
    }).addTo(map);

    // Check for an optional ?zoom= URL parameter
    const zoomParam = urlParams.get("zoom");
    let requestedZoom = null;

    if (zoomParam) {
        requestedZoom = parseInt(zoomParam, 10);
        if (isNaN(requestedZoom)) {
            console.warn("Invalid zoom value provided. Value will be ignored.");
            requestedZoom = null;
        }
    }

    // Parse all ?color= parameters — one color array per route
    const colorParams = [];
    urlParams.forEach((value, key) => {
        if (key === "color") {
            try {
                const parsedColors = JSON.parse(value);
                if (Array.isArray(parsedColors) && parsedColors.every(color => typeof color === 'string')) {
                    colorParams.push(parsedColors);
                } else {
                    console.warn("Invalid color parameter. Default colors will be used.");
                }
            } catch (error) {
                console.warn("Error parsing color parameter. Default colors will be used.");
            }
        }
    });

    // Fall back to a default color array if no colors were specified
    if (colorParams.length === 0) {
        // colorParams.push(['navy', 'orange', 'green', 'purple']); // Example multi-color default
        colorParams.push(['navy']); // Default: navy
    }

    /**
     * Returns the color for a given route segment.
     * Cycles through the color arrays and through colors within each array.
     *
     * @param {number} routeIndex   - Index of the route (0-based)
     * @param {number} segmentIndex - Index of the segment within the route (0-based)
     * @returns {string} CSS color string
     */
    function getSegmentColor(routeIndex, segmentIndex) {
        const colors = colorParams[routeIndex % colorParams.length]; // Cycle through color arrays per route
        return colors[segmentIndex % colors.length]; // Cycle through colors within the array
    }

    let totalDistance = 0;
    let totalDuration = 0;
    let bounds = L.latLngBounds(); // Tracks the bounding box of all rendered elements
    let notFoundLocations = []; // Global list of location names that could not be geocoded

    /**
     * Geocodes all points in a route via Nominatim, pre-calculates total distance/duration,
     * then draws each segment on the map.
     *
     * @param {Array} route      - Array of route point objects (point, text, icon)
     * @param {boolean} section  - Whether to show per-segment stats in waypoint popups
     * @param {string} profile   - OpenRouteService routing profile
     * @param {number} routeIndex - Index of this route (used for color selection)
     */
    function plotRoutes(route, section, profile, routeIndex) {
        const promises = route.map(point => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(point.point)}`;
            return fetch(url).then(response => {
                if (!response.ok) {
                    throw new Error(`Error fetching location: ${response.status}`);
                }
                return response.json();
            });
        });

        return Promise.all(promises)
            .then(locations => {
                const coordinates = [];
                const localNotFound = [];

                locations.forEach((location, index) => {
                    if (location.length > 0) {
                        coordinates.push({
                            lat: parseFloat(location[0].lat),
                            lon: parseFloat(location[0].lon),
                            displayName: location[0].display_name,
                            text: route[index].text,
                            icon: route[index].icon
                        });
                    } else {
                        console.error("Location not found: " + route[index].point);
                        localNotFound.push(route[index].point);
                        notFoundLocations.push(route[index].point);
                    }
                });

                if (localNotFound.length > 0) {
                    // Abort rendering this route if any point could not be geocoded
                    throw new Error("Some locations could not be found: " + localNotFound.join(", "));
                }

                // Pre-calculate total distance and duration for the start marker popup
                calculateTotalRouteData(coordinates, profile)
                    .then(({ totalDuration, totalDistance }) => {
                        const routePromises = [];
                        for (let i = 0; i < coordinates.length - 1; i++) {
                            const start = coordinates[i];
                            const end = coordinates[i + 1];
                            const color = getSegmentColor(routeIndex, i);

                            routePromises.push(plotRoute(start, end, color, section, profile, i === 0, i === coordinates.length - 2, totalDuration, totalDistance));
                        }

                        Promise.all(routePromises).then(() => {
                            if (requestedZoom !== null) {
                                map.setView(bounds.getCenter(), requestedZoom);
                            } else {
                                map.fitBounds(bounds);
                            }
                        });
                    })
                    .catch(error => console.error("Error calculating total route data:", error));
            })
            .catch(error => console.error("Error fetching location data:", error));
    }

    /**
     * Fetches a route segment from OpenRouteService and draws it on the map.
     * On the first segment, adds a start marker showing total route distance and time.
     * On all segments, adds an end/waypoint marker with optional per-segment stats.
     *
     * @param {Object} start           - Start coordinate object (lat, lon, text, icon, displayName)
     * @param {Object} end             - End coordinate object
     * @param {string} color           - Polyline color
     * @param {boolean} section        - Whether to show segment distance/time in the end marker popup
     * @param {string} profile         - OpenRouteService routing profile
     * @param {boolean} isFirstSegment - Whether this is the first segment (shows start marker)
     * @param {boolean} isLastSegment  - Whether this is the last segment (shows end/finish marker)
     * @param {number} totalDuration   - Pre-calculated total route duration in seconds
     * @param {number} totalDistance   - Pre-calculated total route distance in metres
     */
    function plotRoute(start, end, color, section, profile, isFirstSegment, isLastSegment, totalDuration, totalDistance) {
        return new Promise((resolve, reject) => {
            // Start marker: use custom icon if provided, otherwise use start or default icon
            const startIcon = L.icon({
                iconUrl: start.icon ? start.icon : (isFirstSegment ? startIconUrl : defaultIconUrl),
                iconSize: [iconWidth, iconHeight],
                iconAnchor: [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            });

            // End marker: use custom icon if provided, otherwise use end or default icon
            const endIcon = L.icon({
                iconUrl: end.icon || (isLastSegment ? endIconUrl : defaultIconUrl),
                iconSize: [iconWidth, iconHeight],
                iconAnchor: [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            });

            const routeUrl = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${start.lon},${start.lat}&end=${end.lon},${end.lat}`;

            fetch(routeUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(routeData => {
                    const routeCoords = routeData.features[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                    const routeLine = L.polyline(routeCoords, { color: color, weight: 5 }).addTo(map);

                    routeCoords.forEach(coord => bounds.extend(coord));
                    bounds.extend([start.lat, start.lon]);
                    bounds.extend([end.lat, end.lon]);

                    const duration = routeData.features[0].properties.segments[0].duration;
                    const distance = routeData.features[0].properties.segments[0].distance;

                    // Add the start marker on the first segment only, showing total route stats
                    if (isFirstSegment) {
                        const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);

                        // Format total distance and travel time for the popup
                        const distanceKm = (totalDistance / 1000).toFixed(2); // Distance in kilometres
                        const durationMinutes = Math.round(totalDuration / 60); // Duration in minutes
                        const durationText = `${Math.floor(durationMinutes / 60)} hr ${durationMinutes % 60} min`;

                        let startPopupText = `${start.text || start.displayName}`;
                        startPopupText += `
                            <br><hr>
                            <strong>Total distance:</strong> ${distanceKm} km<br>
                            <strong>Total travel time:</strong> ${durationText}
                        `;

                        startMarker.bindPopup(startPopupText);
                        startMarker.on('mouseover', function () {
                            this.openPopup();
                        });
                        startMarker.on('mouseout', function () {
                            this.closePopup();
                        });
                    }

                    // Build the end/waypoint marker popup text
                    let endPopupText = end.text || `${end.displayName}`;
                    if (section) {
                        const distanceKm = (distance / 1000).toFixed(2); // Distance in kilometres
                        const durationMinutes = Math.round(duration / 60); // Duration in minutes
                        endPopupText += `
                            <br><hr>
                            <strong>Distance:</strong> ${distanceKm} km<br>
                            <strong>Travel time:</strong> ${durationMinutes} min
                        `;
                    }

                    const endMarker = L.marker([end.lat, end.lon], { icon: endIcon }).addTo(map);
                    endMarker.bindPopup(endPopupText);
                    endMarker.on('mouseover', function () {
                        this.openPopup();
                    });
                    endMarker.on('mouseout', function () {
                        this.closePopup();
                    });

                    resolve({ distance, duration });
                })
                .catch(error => {
                    console.error("Error fetching route:", error);
                    reject(error);
                });
        });
    }

    /**
     * Fetches the total distance and duration for a route by querying ORS
     * from the first to the last coordinate only (ignoring intermediate waypoints).
     * Used to populate the start marker popup with overall route statistics.
     *
     * @param {Array} coordinates - Array of coordinate objects with lat/lon
     * @param {string} profile    - OpenRouteService routing profile
     * @returns {Promise<{totalDuration: number, totalDistance: number}>}
     */
    function calculateTotalRouteData(coordinates, profile) {
        const totalRouteUrl = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${coordinates[0].lon},${coordinates[0].lat}&end=${coordinates[coordinates.length - 1].lon},${coordinates[coordinates.length - 1].lat}`;

        return fetch(totalRouteUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error fetching total route data: ${response.status}`);
                }
                return response.json();
            })
            .then(routeData => {
                const totalDuration = routeData.features[0].properties.segments.reduce((sum, segment) => sum + segment.duration, 0);
                const totalDistance = routeData.features[0].properties.segments.reduce((sum, segment) => sum + segment.distance, 0);

                return { totalDuration, totalDistance };
            });
    }
</script>

</body>
</html>
