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

<!-- Total distance and time container -->
<div id="duration-container"></div>

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
     * Parses the ?route= URL parameter and returns an array of route points.
     * Each point contains a location string, optional popup text, and optional icon URL.
     * Returns an empty array if the parameter is missing or invalid.
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
        const route = getRouteFromQuery();
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

        // Process route
        if (route.length > 0) {
            promises.push(plotRoutes(route, section, profile));
        }

        // Wait for all locations and routes to finish processing
        Promise.all(promises).then(() => {
            if (requestedZoom !== null) {
                // Force the map to the requested zoom level
                map.setView(bounds.getCenter(), requestedZoom);
            } else {
                // Fit the map to the bounds of all markers and route segments
                map.fitBounds(bounds);
            }
        });

        // If no route or locations are provided, try to use the browser's geolocation
        if (route.length === 0 && locations.length === 0) {
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

    // Parse the ?color= parameter — can be a JSON array of color strings
    const colorParam = urlParams.get("color");
    let colors = ['navy']; // Default color; can be an array for per-segment colors

    if (colorParam) {
        try {
            const parsedColors = JSON.parse(colorParam);
            if (Array.isArray(parsedColors) && parsedColors.every(color => typeof color === 'string')) {
                colors = parsedColors;
            } else {
                console.warn("Invalid color parameter. Default colors will be used.");
            }
        } catch (error) {
            console.warn("Error parsing color parameter. Default colors will be used.");
        }
    }

    // Example of multi-segment colors (uncomment to use):
    // const colors = ['navy', 'orange'];

    let totalDistance = 0;
    let totalDuration = 0;
    let bounds = L.latLngBounds(); // Tracks the bounding box of all rendered elements

    /**
     * Geocodes all route points via Nominatim, then draws route segments between them.
     * Updates the duration/distance overlay after all segments are rendered.
     *
     * @param {Array} route   - Array of route point objects (point, text, icon)
     * @param {boolean} section - Whether to show per-segment distance/time in popups
     * @param {string} profile  - OpenRouteService routing profile (e.g. 'driving-car')
     */
    function plotRoutes(route, section, profile) {
        const promises = route.map(point => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(point.point)}`;
            return fetch(url).then(response => {
                if (!response.ok) {
                    throw new Error(`Error fetching location: ${response.status}`);
                }
                return response.json();
            });
        });

        Promise.all(promises)
            .then(locations => {
                const coordinates = locations.map((location, index) => {
                    if (location.length > 0) {
                        return {
                            lat: parseFloat(location[0].lat),
                            lon: parseFloat(location[0].lon),
                            displayName: location[0].display_name.split(",")[0], // Use only the primary name
                            text: route[index].text,
                            icon: route[index].icon
                        };
                    } else {
                        throw new Error("Location not found: " + route[index].point);
                    }
                });

                const routePromises = [];
                for (let i = 0; i < coordinates.length - 1; i++) {
                    const start = coordinates[i];
                    const end = coordinates[i + 1];
                    const color = colors[i % colors.length];

                    routePromises.push(plotRoute(start, end, color, section, profile, i === 0, i === coordinates.length - 2));
                }

                // Fit the map after all segments and locations have been rendered
                Promise.all(routePromises).then(() => {
                    if (requestedZoom !== null) {
                        // Force the map to the requested zoom level
                        map.setView(bounds.getCenter(), requestedZoom);
                    } else {
                        // Fit the map to all rendered elements
                        map.fitBounds(bounds);
                    }

                    // Update the overlay with total distance, travel time, and estimated arrival
                    const totalDurationMinutes = totalDuration / 60;
                    const totalHours = Math.floor(totalDurationMinutes / 60);
                    const totalMinutes = Math.floor(totalDurationMinutes % 60).toString().padStart(2, '0');
                    const totalDurationText = `${totalHours} hr ${totalMinutes} min`;
                    const totalDistanceKm = (totalDistance / 1000).toFixed(2);

                    const now = new Date();
                    const arrivalTime = new Date(now.getTime() + totalDuration * 1000);
                    const arrivalHours = arrivalTime.getHours().toString().padStart(2, '0');
                    const arrivalMinutes = arrivalTime.getMinutes().toString().padStart(2, '0');
                    const arrivalText = `${arrivalHours}:${arrivalMinutes}`;

                    const durationContainer = document.getElementById("duration-container");
                    durationContainer.innerHTML = `
                        <div><strong>Total distance:</strong> ${totalDistanceKm} km</div>
                        <div><strong>Travel time:</strong> ${totalDurationText}</div>
                        <div><strong>Estimated arrival:</strong> ${arrivalText}</div>
                    `;
                    durationContainer.style.display = "block";
                });
            })
            .catch(error => console.error("Error fetching location data:", error));
    }

    /**
     * Fetches a route segment from OpenRouteService and draws it on the map.
     * Adds start and end markers with popups. Optionally shows per-segment stats.
     *
     * @param {Object} start          - Start coordinate object (lat, lon, text, icon, displayName)
     * @param {Object} end            - End coordinate object
     * @param {string} color          - Polyline color
     * @param {boolean} section       - Whether to show segment distance/time in the end marker popup
     * @param {string} profile        - OpenRouteService routing profile
     * @param {boolean} isFirstSegment - Whether this is the first segment (shows start marker)
     * @param {boolean} isLastSegment  - Whether this is the last segment (shows end/finish marker)
     */
    function plotRoute(start, end, color, section, profile, isFirstSegment, isLastSegment) {
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

                    totalDistance += distance;
                    totalDuration += duration;

                    // Add the start marker on the first segment only
                    if (isFirstSegment) {
                        const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);
                        startMarker.bindPopup(start.text || `Start: ${start.displayName}`);
                        startMarker.on('mouseover', function () {
                            this.openPopup();
                        });
                        startMarker.on('mouseout', function () {
                            this.closePopup();
                        });
                    }

                    // Build the end/waypoint marker popup text
                    let endPopupText = end.text || `Waypoint: ${end.displayName}`;
                    if (section) {
                        const distanceKm = (distance / 1000).toFixed(2); // Distance in kilometres
                        const durationMinutes = Math.round(duration / 60); // Duration in minutes
                        endPopupText += `
                            <br><hr>
                            <strong>Segment distance:</strong> ${distanceKm} km<br>
                            <strong>Segment time:</strong> ${durationMinutes} min
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

                    resolve();
                })
                .catch(error => {
                    console.error("Error fetching route:", error);
                    reject(error);
                });
        });
    }
</script>

</body>
</html>
