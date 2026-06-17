# Differences between `getroute.php` and `multiroute.php`
This file is actually outdated, as that both files have been merged into maprouter.php, containing all and even more functions and options. The
file will be removed from the repository in future updates.

## Overview

Both files share the same foundation: an interactive map application built with Leaflet.js, using OpenRouteService for routing and Nominatim for geocoding. The core distinction is that `getroute.php` handles a **single route**, while `multiroute.php` is designed to handle **multiple simultaneous routes**.

---

## 1. Route Input Handling

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| Route parsing function | `getRouteFromQuery()` — reads a single `?route=` parameter | `getRoutesFromQuery()` — iterates all `?route=` parameters, collecting them into an array of routes |
| Duplicate function | `getRouteFromQuery()` still exists but is unused | Both `getRouteFromQuery()` (unused/leftover) and `getRoutesFromQuery()` are present |
| Input variable | `const route = getRouteFromQuery()` — single array | `const routes = getRoutesFromQuery()` — array of arrays |
| Route loop | `plotRoutes(route, section, profile)` called once | `routes.forEach((route, index) => plotRoutes(..., index))` — called per route |

---

## 2. Duration / Distance Display

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| `#duration-container` HTML element | Present and active (`<div id="duration-container"></div>`) | Present in CSS but **commented out** in HTML (`<!-- div id="duration-container">`) |
| Total distance/time display | Shown in an overlay in the bottom-right corner of the map after route calculation | Shown **inside the start marker popup** of each individual route |
| Arrival time | Calculated and displayed in the overlay | Not calculated or displayed |
| DOM removal | n/a | If more than one route is present, `durationContainer` is removed from the DOM entirely |

---

## 3. `plotRoutes()` function signature

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| Parameters | `(route, section, profile)` | `(route, section, profile, routeIndex)` — adds a route index for color selection |
| Return value | No explicit return (implicit `undefined`) | Returns the `Promise.all()` chain explicitly |
| Location resolution | Throws error and aborts on missing location | Collects missing locations in `localNotFound[]` and `notFoundLocations[]` arrays, then throws |
| `displayName` | Uses only first part: `display_name.split(",")[0]` | Uses full `display_name` |
| Pre-calculation | Draws segments, then updates totals inline | Calls `calculateTotalRouteData()` first to get totals, then draws segments |

---

## 4. Color Parameter Handling

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| Variable | `let colors = ['navy']` — single array | `const colorParams = []` — array of color arrays, one per route |
| Parsing | Reads a single `?color=` parameter | Iterates all `?color=` parameters, one array per route |
| Color selection | `colors[i % colors.length]` — cycles through one array | `getSegmentColor(routeIndex, segmentIndex)` — picks color per route and per segment |
| Helper function | None | `getSegmentColor(routeIndex, segmentIndex)` |

---

## 5. `plotRoute()` function signature

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| Parameters | `(start, end, color, section, profile, isFirstSegment, isLastSegment)` | `(start, end, color, section, profile, isFirstSegment, isLastSegment, totalDuration, totalDistance)` — adds pre-calculated totals |
| Resolve value | `resolve()` — no value | `resolve({ distance, duration })` — returns segment data |
| Start marker popup | Shows `start.text` or `"Startpunt: {displayName}"` | Shows `start.text` or `displayName`, plus total distance and duration of the whole route |
| Section popup labels | `"Tussenafstand"` / `"Tussentijd"` | `"Afstand"` / `"Rijtijd"` (shorter labels) |

---

## 6. Extra Function: `calculateTotalRouteData()`

Only present in `multiroute.php`. Makes a separate API call from start to end of a route (ignoring waypoints) to calculate the total distance and duration for display in the start marker popup.

```javascript
function calculateTotalRouteData(coordinates, profile) {
    // Fetches ORS directions from first to last coordinate only
    // Returns { totalDuration, totalDistance }
}
```

`getroute.php` accumulates distance/duration incrementally by summing each segment as it is drawn.

---

## 7. Error Handling and Debugging

| Aspect | `getroute.php` | `multiroute.php` |
|---|---|---|
| Missing locations after all promises | Silent (only console errors) | Shows an `alert()` listing all not-found locations |
| Global tracking of not-found locations | None | `let notFoundLocations = []` — reset on each `DOMContentLoaded` |
| Debug logging | None | `console.log()` for bounds, routes, locations, and duration container |
| Bounds validity check | `map.fitBounds(bounds)` called unconditionally | Checks `bounds.isValid()` before fitting |

---

## 8. Geolocation Fallback (no input given)

Both files fall back to the user's current browser location, and then to Amsterdam if geolocation is unavailable. The logic is identical. The only difference is the condition checked:

- `getroute.php`: `if (route.length === 0 && locations.length === 0)`
- `multiroute.php`: `if (routes.length === 0 && locations.length === 0)`

---

## 9. Code Quality Notes

- **Dead code in `multiroute.php`**: The function `getRouteFromQuery()` (single-route version) is defined but never called. It is a leftover from copying `getroute.php` as a base.
- **CSS dead code in `multiroute.php`**: The `#duration-container` CSS rule remains but the element is commented out in HTML.
- **`requestedZoom` declared twice in `getroute.php`**: Once inside `DOMContentLoaded` and once in the outer scope, which can cause a `let` redeclaration conflict depending on browser handling.
- **Both files**: Comments and UI strings are in Dutch; code uses English variable/function names inconsistently.

---

## Summary Table

| Feature | `getroute.php` | `multiroute.php` |
|---|---|---|
| Multiple routes | ✗ | ✓ |
| Duration overlay (bottom-right) | ✓ | ✗ (commented out) |
| Arrival time display | ✓ | ✗ |
| Stats in start popup | ✗ | ✓ |
| Per-route color arrays | ✗ | ✓ |
| `calculateTotalRouteData()` | ✗ | ✓ |
| Not-found location alert | ✗ | ✓ |
| `notFoundLocations` tracking | ✗ | ✓ |
| Debug console logs | ✗ | ✓ |
| `bounds.isValid()` check | ✗ | ✓ |
| Dead/unused code | Minor | `getRouteFromQuery()` unused |
