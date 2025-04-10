<!DOCTYPE html>
<html lang="nl">
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

<!-- Totale afstand en tijd container -->
<div id="duration-container"></div>

<!-- Kaart -->
<div id="map"></div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="config.js"></script>
<script>
    // Gebruik de geladen API-sleutels
    const thunderforestApiKey = config.thunderforestApiKey;
    const tracestrackApiKey = config.tracestrackApiKey;
    const openRouteServiceApiKey = config.openRouteServiceApiKey;

    // Standaard icoon-URL's en afmetingen
    const defaultIconUrl = 'https://code.webwings.nl/pinpoint.png';
    const startIconUrl = 'https://code.webwings.nl/start.png';
    const endIconUrl = 'https://code.webwings.nl/stop.png';
    const iconWidth = 32; // Breedte van de iconen
    const iconHeight = 37; // Hoogte van de iconen
    const popupOffset = -35; // Offset om de popup hoger te plaatsen

    function getRouteFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const routeParam = urlParams.get("route");
        if (!routeParam) {
            return []; // Geen alert meer, gewoon een lege array retourneren
        }

        try {
            const route = JSON.parse(routeParam);
            if (!Array.isArray(route)) {
                throw new Error("Route moet een array zijn.");
            }
            return route.map(point => {
                if (!point.point) {
                    throw new Error("Elk punt moet een 'point' eigenschap hebben.");
                }
                return {
                    point: point.point,
                    text: point.text || null,
                    icon: point.icon || defaultIconUrl
                };
            });
        } catch (error) {
            alert("Fout bij het verwerken van de route: " + error.message);
            return [];
        }
    }

    function getRoutesFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const routes = [];

        // Verzamel alle 'route' parameters
        urlParams.forEach((value, key) => {
            if (key === "route") {
                try {
                    const route = JSON.parse(value);
                    if (!Array.isArray(route)) {
                        throw new Error("Route moet een array zijn.");
                    }
                    routes.push(
                        route.map(point => {
                            if (!point.point) {
                                throw new Error("Elk punt moet een 'point' eigenschap hebben.");
                            }
                            return {
                                point: point.point,
                                text: point.text || null,
                                icon: point.icon || defaultIconUrl
                            };
                        })
                    );
                } catch (error) {
                    console.error("Fout bij het verwerken van een route:", error.message);
                }
            }
        });

        return routes;
    }

    function getLocationsFromQuery() {
        const urlParams = new URLSearchParams(window.location.search);
        const locationParam = urlParams.get("location");
        if (!locationParam) {
            return []; // Geen locaties opgegeven, retourneer een lege array
        }

        try {
            const locations = JSON.parse(locationParam);
            if (!Array.isArray(locations)) {
                throw new Error("Locaties moeten een array zijn.");
            }
            return locations.map(point => {
                if (!point.point) {
                    throw new Error("Elk punt moet een 'point' eigenschap hebben.");
                }
                return {
                    point: point.point,
                    text: point.text || null,
                    icon: point.icon || defaultIconUrl
                };
            });
        } catch (error) {
            alert("Fout bij het verwerken van de locaties: " + error.message);
            return [];
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const routes = getRoutesFromQuery(); // Haal alle routes op
        const locations = getLocationsFromQuery();
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get("section") === "true";
        const profile = urlParams.get("profile") || "driving-car";
        const zoomParam = urlParams.get("zoom");
        let requestedZoom = null;

        if (zoomParam) {
            requestedZoom = parseInt(zoomParam, 10);
            if (isNaN(requestedZoom)) {
                console.warn("Ongeldige zoomwaarde opgegeven. De waarde wordt genegeerd.");
                requestedZoom = null;
            }
        }

        const promises = [];

        // Controleer het aantal routes en verwijder de container indien nodig
        const durationContainer = document.getElementById("duration-container");
        if (routes.length > 1) {
            durationContainer.remove(); // Verwijder de container volledig uit de DOM
        }

        // Verwerk locaties
        if (locations.length > 0) {
            promises.push(
                new Promise((resolve) => {
                    const locationPromises = locations.map(location => {
                        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location.point)}`;
                        return fetch(url).then(response => {
                            if (!response.ok) {
                                throw new Error(`Fout bij ophalen locatie: ${response.status}`);
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
                                    console.error("Locatie niet gevonden: " + locations[index].point);
                                }
                            });
                            resolve();
                        })
                        .catch(error => {
                            console.error("Fout bij het ophalen van locatiegegevens:", error);
                            resolve();
                        });
                })
            );
        }

        // Verwerk routes
        routes.forEach(route => {
            if (route.length > 0) {
                promises.push(plotRoutes(route, section, profile));
            }
        });

        // Wacht tot alle locaties en routes zijn verwerkt
        Promise.all(promises).then(() => {
            if (bounds.isValid()) {
                if (requestedZoom !== null) {
                    // Forceer de zoom naar het opgegeven niveau
                    map.setView(bounds.getCenter(), requestedZoom);
                } else {
                    // Zoom naar de bounds als er geen expliciete zoomwaarde is
                    map.fitBounds(bounds);
                }
            } else {
                console.warn("Bounds zijn niet geldig. Er zijn mogelijk geen geldige coördinaten opgehaald.");
            }
        });

        // Als er geen route of locaties zijn, probeer huidige locatie te gebruiken
        if (routes.length === 0 && locations.length === 0) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const { latitude, longitude } = position.coords;
                        map.setView([latitude, longitude], 14);

                        // Voeg een blauw cirkeltje toe op de huidige locatie
                        L.circle([latitude, longitude], {
                            color: 'blue',
                            fillColor: '#3f83f8',
                            fillOpacity: 0.5,
                            radius: 50
                        }).addTo(map).bindPopup("Uw huidige locatie").openPopup();
                    },
                    error => {
                        console.error("Fout bij ophalen van huidige locatie:", error);
                        // Fallback naar Amsterdam
                        fallbackToAmsterdam();
                    }
                );
            } else {
                console.warn("Geolocatie niet ondersteund door de browser.");
                // Fallback naar Amsterdam
                fallbackToAmsterdam();
            }
        }

        console.log("Bounds:", bounds);
        console.log("Routes:", routes);
        console.log("Locations:", locations);
        console.log("Bestaat durationContainer?", !!document.getElementById("duration-container"));
    });

    function fallbackToAmsterdam() {
        const amsterdamCoords = [52.3676, 4.9041];
        map.setView(amsterdamCoords, 12);

        // Voeg een blauw cirkeltje toe op Amsterdam
        L.circle(amsterdamCoords, {
            color: 'blue',
            fillColor: '#3f83f8',
            fillOpacity: 0.5,
            radius: 50
        }).addTo(map).bindPopup("Fallback: Amsterdam").openPopup();
    }

    var map = L.map('map').setView([52.3676, 4.9041], 10);

    // Kies de kaartlaag op basis van de 'layer' parameter
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
            tileLayerUrl = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"; // Default OSM
            tileLayerAttribution = "&copy; OpenStreetMap contributors";
            break;
    }

    L.tileLayer(tileLayerUrl, {
        attribution: tileLayerAttribution
    }).addTo(map);

    // Controleer of er een ?zoom= parameter is opgegeven
    const zoomParam = urlParams.get("zoom");
    let requestedZoom = null;

    if (zoomParam) {
        requestedZoom = parseInt(zoomParam, 10);
        if (isNaN(requestedZoom)) {
            console.warn("Ongeldige zoomwaarde opgegeven. De waarde wordt genegeerd.");
            requestedZoom = null;
        }
    }

    // Verwerk de ?color= parameter
    const colorParam = urlParams.get("color");
    let colors = ['navy']; // Standaard kleuren kan een array zijn per segment.

    if (colorParam) {
        try {
            const parsedColors = JSON.parse(colorParam);
            if (Array.isArray(parsedColors) && parsedColors.every(color => typeof color === 'string')) {
                colors = parsedColors;
            } else {
                console.warn("Ongeldige kleurparameter opgegeven. De standaardkleuren worden gebruikt.");
            }
        } catch (error) {
            console.warn("Fout bij het verwerken van de kleurparameter. De standaardkleuren worden gebruikt.");
        }
    }

    // Standaard kleuren
    // const colors = ['navy', 'orange']; // Standaard kleuren per segment

    let totalDistance = 0;
    let totalDuration = 0;
    let bounds = L.latLngBounds(); // Om de volledige route te berekenen

    function plotRoutes(route, section, profile) {
        const promises = route.map(point => {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(point.point)}`;
            return fetch(url).then(response => {
                if (!response.ok) {
                    throw new Error(`Fout bij ophalen locatie: ${response.status}`);
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
                            displayName: location[0].display_name,
                            text: route[index].text,
                            icon: route[index].icon
                        };
                    } else {
                        throw new Error("Locatie niet gevonden: " + route[index].point);
                    }
                });

                // Bereken totale afstand en tijd voor deze specifieke route
                calculateTotalRouteData(coordinates, profile)
                    .then(({ totalDuration, totalDistance }) => {
                        const routePromises = [];
                        for (let i = 0; i < coordinates.length - 1; i++) {
                            const start = coordinates[i];
                            const end = coordinates[i + 1];
                            const color = colors[i % colors.length];

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
                    .catch(error => console.error("Fout bij het berekenen van totale routegegevens:", error));
            })
            .catch(error => console.error("Fout bij het ophalen van locatiegegevens:", error));
    }

    function plotRoute(start, end, color, section, profile, isFirstSegment, isLastSegment, totalDuration, totalDistance) {
        return new Promise((resolve, reject) => {
            const startIcon = L.icon({
                iconUrl: start.icon ? start.icon : (isFirstSegment ? startIconUrl : defaultIconUrl),
                iconSize: [iconWidth, iconHeight],
                iconAnchor: [iconWidth / 2, iconHeight],
                popupAnchor: [0, popupOffset]
            });

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
                        throw new Error(`HTTP-fout! Status: ${response.status}`);
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

                    // Voeg de startmarker toe als het het eerste segment is
                    if (isFirstSegment) {
                        const startMarker = L.marker([start.lat, start.lon], { icon: startIcon }).addTo(map);

                        // Bereken afstand en tijd in leesbare formaten
                        const distanceKm = (totalDistance / 1000).toFixed(2); // Afstand in kilometers
                        const durationMinutes = Math.round(totalDuration / 60); // Tijd in minuten
                        const durationText = `${Math.floor(durationMinutes / 60)} uur ${durationMinutes % 60} min`;

                        // Voeg afstand en tijd toe aan de popuptekst
                        let startPopupText = `${start.text || start.displayName}`;
                        startPopupText += `
                            <br><hr>
                            <strong>Totale afstand:</strong> ${distanceKm} km<br>
                            <strong>Totale rijtijd:</strong> ${durationText}
                        `;

                        startMarker.bindPopup(startPopupText);
                        startMarker.on('mouseover', function () {
                            this.openPopup();
                        });
                        startMarker.on('mouseout', function () {
                            this.closePopup();
                        });
                    }

                    // Voeg de eindmarker toe
                    let endPopupText = end.text || `${end.displayName}`;
                    if (section) {
                        const distanceKm = (distance / 1000).toFixed(2); // Afstand in kilometers
                        const durationMinutes = Math.round(duration / 60); // Tijd in minuten
                        endPopupText += `
                            <br><hr>
                            <strong>Afstand:</strong> ${distanceKm} km<br>
                            <strong>Rijtijd:</strong> ${durationMinutes} min
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
                    console.error("Fout bij het ophalen van de route:", error);
                    reject(error);
                });
        });
    }

    function calculateTotalRouteData(coordinates, profile) {
        const totalRouteUrl = `https://api.openrouteservice.org/v2/directions/${profile}?api_key=${openRouteServiceApiKey}&start=${coordinates[0].lon},${coordinates[0].lat}&end=${coordinates[coordinates.length - 1].lon},${coordinates[coordinates.length - 1].lat}`;

        return fetch(totalRouteUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Fout bij ophalen totale routegegevens: ${response.status}`);
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