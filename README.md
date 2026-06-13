# Webwings Route Maps

A web application for displaying interactive routes and locations on a map, built on [OpenStreetMap](https://www.openstreetmap.org/) and [Leaflet.js](https://leafletjs.com/). Routes are calculated via the [OpenRouteService](https://openrouteservice.org/) API; locations are geocoded via [Nominatim](https://nominatim.org/). Some tile layers require a free API key.

## Files in this project

| File | Description |
|---|---|
| `example-config.js` | Configuration template — copy to `config.js` and fill in your API keys |
| `maprouter.php` | **Main application** — handles single and multiple routes, standalone locations, all tile layers and all URL parameters |
| `openroute.py` | Python helper script to interactively build and open a route URL |
| `mapicons.html` | Visual reference sheet of all 854 available map icons with live filter search |
| `differences.md` | Comparison between the legacy `getroute.php` and `multiroute.php` (superseded by `maprouter.php`) |

## Features

### 1. Map display
The map is rendered with Leaflet.js. The default tile layer is OpenStreetMap; additional layers are available via the `layer` URL parameter.

### 2. Routes
Routes are calculated by the OpenRouteService API. Pass one or more `route` parameters in the URL as JSON arrays. Each point supports:
- `point` *(required)*: address or coordinates
- `text` *(optional)*: popup text (supports full HTML, including `<img>` tags)
- `icon` *(optional)*: URL of a custom marker icon

Example:
```json
[
    {"point": "Amsterdam", "text": "Start", "icon": "https://example.com/start.png"},
    {"point": "Rotterdam", "text": "End"},
    {"point": "Utrecht"}
]
```

### 3. Single-route mode vs. multi-route mode
`maprouter.php` detects automatically how many routes are passed:

- **Single route** (`?route=[...]`): total distance, travel time, and estimated arrival are shown in an overlay in the bottom-right corner of the map.
- **Multiple routes** (`?route=[...]&route=[...]&...`): each route shows its total distance and travel time in the start marker popup. No arrival time is shown.

### 4. Locations
Standalone markers (not connected by a route) are passed via the `location` parameter, using the same format as route points.

Example:
```json
[
    {"point": "Utrecht", "text": "Point of interest"}
]
```

### 5. Current location fallback
If no routes or locations are provided, the app attempts to use the browser's geolocation API. If that fails, the map falls back to Amsterdam.

### 6. Available icons
The `mapicons/` folder contains 854 icons for use as custom markers. Open `mapicons.html` in a browser for a searchable visual overview.

Icons can be referenced by their full URL, for example:
```
https://yourdomain.com/mapicons/campfire.png
```

Custom icons can be created with the included `.pxd` files (compatible with Pixelmator Pro, GIMP, or Photoshop).

### 7. Route colours
Route lines can be customised with the `color` parameter. Pass a single colour or a JSON array; multiple colours are applied per segment in rotation. In multi-route mode, each `?color=` parameter applies to the corresponding route.

Example:
```json
["navy", "orange", "green", "purple"]
```

---

## Setup

### 1. Requirements
The scripts are PHP, so a PHP-capable web server is required (e.g. Apache, Nginx, or XAMPP for local development). External API calls are made from the browser, so no server-side proxy is needed.

### 2. Place the files
Ensure `config.js` and `maprouter.php` are in the same directory on your server.

### 3. Configure API keys
Copy `example-config.js` to `config.js` and fill in your keys:
```js
const config = {
    thunderforestApiKey: "YOUR_THUNDERFOREST_API_KEY",
    tracestrackApiKey: "YOUR_TRACESTRACK_API_KEY",
    openRouteServiceApiKey: "YOUR_OPENROUTESERVICE_API_KEY"
};
```

### 4. Open the application
Navigate to `maprouter.php` in your browser and add URL parameters as needed:
```
https://yourdomain.com/maprouter.php?route=[...]&location=[...]&layer=[...]&section=[...]&profile=[...]
```

---

## URL parameters

### `route`
JSON array of route points. Repeat the parameter for multiple routes.

```json
[{"point":"Amsterdam","text":"Start"},{"point":"Rotterdam","text":"End"}]
```

### `location`
JSON array of standalone location markers. Same format as `route`.

```json
[{"point":"Utrecht","text":"Point of interest"}]
```

### `layer`
Map tile layer. Options:

| Value | Description |
|---|---|
| *(default)* | OpenStreetMap standard layer |
| `topo` | Tracestrack topographic layer *(API key required)* |
| `cycle` | Thunderforest cycling layer *(API key required)* |
| `transport` | Thunderforest transport layer *(API key required)* |

### `section`
Set to `true` to show per-segment distance and travel time in waypoint popups. Default: `false`.

### `profile`
OpenRouteService routing profile. Options:

| Value | Description |
|---|---|
| `driving-car` | Car routing *(default)* |
| `cycling-regular` | Cycling |
| `cycling-road` | Road cycling |
| `cycling-mountain` | Mountain biking |
| `cycling-electric` | E-bike |
| `foot-hiking` | Hiking |
| `foot-walking` | Walking (footpath focus) |
| `wheelchair` | Wheelchair accessible |

### `color`
JSON array of one or more CSS colour strings. Multiple colours rotate per segment. In multi-route mode, repeat the parameter — one per route.

```json
["navy", "orange"]
```

### `table`
Add `&table` (no value needed) to show a collapsible side panel with a detailed segment breakdown for each route. The panel lists every segment with its start point, end point, distance, and travel time, plus a totals row per route. In multi-route mode all routes appear as separate sections in the same panel.

Point labels use the `text` value if provided, otherwise the geocoded place name, otherwise a numbered fallback ("Point 1", "Point 2", …).

The panel can be toggled open and closed at any time using the button on the left edge of the map.

### `zoom`
Integer zoom level (1–20). Overrides the automatic fit-to-bounds behaviour.

---

## Example URLs

**Map only (current location or Amsterdam fallback):**
```
https://yourdomain.com/maprouter.php
```

**Single route with topo layer and cycling profile:**
```
https://yourdomain.com/maprouter.php?route=[{"point":"Amsterdam","text":"Start"},{"point":"Rotterdam","text":"End"}]&layer=topo&profile=cycling-regular
```

**Standalone location marker:**
```
https://yourdomain.com/maprouter.php?location=[{"point":"Utrecht","text":"Point of interest"}]
```

**Route combined with a location marker:**
```
https://yourdomain.com/maprouter.php?route=[{"point":"Amsterdam","text":"Start"},{"point":"Rotterdam","text":"End"}]&location=[{"point":"Utrecht","text":"Point of interest"}]
```

**Route with segment table panel:**
```
https://yourdomain.com/maprouter.php?route=[{"point":"Amsterdam","text":"Start"},{"point":"Utrecht","text":"Via"},{"point":"Rotterdam","text":"End"}]&table
```

**Multiple routes with individual colours:**
```
https://yourdomain.com/maprouter.php?route=[{"point":"Amsterdam"},{"point":"Utrecht"}]&color=["navy"]&route=[{"point":"Rotterdam"},{"point":"Den Haag"}]&color=["orange"]
```

---

## External dependencies

| Library / API | Purpose | API key required |
|---|---|---|
| [Leaflet.js](https://leafletjs.com/) | Map rendering | No |
| [OpenStreetMap](https://www.openstreetmap.org/) | Default tile layer | No |
| [Nominatim](https://nominatim.org/) | Geocoding (address → coordinates) | No |
| [OpenRouteService](https://openrouteservice.org/) | Route calculation | Yes (free tier available) |
| [Tracestrack](https://tracestrack.com/) | Topographic tile layer | Yes (free tier available) |
| [Thunderforest](https://www.thunderforest.com/) | Cycling/transport tile layers | Yes (free tier available) |

---

## Changelog

| Date | Change |
|---|---|
| 2025 | Initial release |
| 2026-06-13 | All code, comments, and UI strings translated to English |
| 2026-06-13 | Added `mapicons.html` — searchable visual reference for all 854 map icons |
| 2026-06-13 | Added `differences.md` — detailed comparison between `getroute.php` and `multiroute.php` |
| 2026-06-13 | Added `maprouter.php` — unified script merging `getroute.php` and `multiroute.php` |
| 2026-06-13 | Added `?table` parameter — collapsible side panel with per-route segment breakdown |

---

## Licence

This project is licensed under the MIT Licence.

Copyright © Richard, Webwings 2025

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
