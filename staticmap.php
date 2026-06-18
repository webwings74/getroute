<?php
/**
 * staticmap.php — Webwings Maprouter static image endpoint
 *
 * Accepts the same route/location/color URL parameters as maprouter.php
 * and returns a PNG image with OSM background and markers.
 *
 * Requirements:
 *   composer require dantsu/php-osm-static-api
 *
 * URL parameters:
 *   route    — JSON array of route points: [{"point":"Amsterdam","text":"Start"}, ...]
 *              Repeat for multiple routes. Points can be place names or "lat,lon".
 *   location — JSON array of standalone markers: [{"point":"Utrecht","text":"POI"}]
 *   color    — JSON array of CSS color names per route: ["navy"] (one per route)
 *   width    — Image width in pixels (default: 800)
 *   height   — Image height in pixels (default: 500)
 *   zoom     — Zoom level override (default: auto-fit)
 *
 * Example:
 *   staticmap.php?route=[{"point":"Amsterdam"},{"point":"Rotterdam"}]&color=["navy"]
 */

require_once __DIR__ . '/vendor/autoload.php';

use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\Markers;
use DantSu\OpenStreetMapStaticAPI\DrawingForm;
use DantSu\OpenStreetMapStaticAPI\Line;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Geocode a place name via Nominatim.
 * Returns ['lat' => float, 'lon' => float] or null when not found.
 */
function geocode(string $query): ?array
{
    // Accept raw coordinates: "52.1234,4.5678"
    if (preg_match('/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/', trim($query), $m)) {
        return ['lat' => (float)$m[1], 'lon' => (float)$m[2]];
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);
    $opts = ['http' => ['header' => "User-Agent: Webwings-Maprouter/1.0\r\n"]];
    $data = @file_get_contents($url, false, stream_context_create($opts));
    if (!$data) return null;

    $results = json_decode($data, true);
    if (empty($results)) return null;

    return ['lat' => (float)$results[0]['lat'], 'lon' => (float)$results[0]['lon']];
}

/**
 * Parse a JSON array from a URL parameter, return [] on failure.
 */
function parseJsonParam(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Convert a CSS color name or hex (#rgb / #rrggbb / #rrggbbaa) to [r, g, b, a].
 */
function cssColorToRgba(string $color): array
{
    $named = [
        'navy'    => [0,   0,   128],
        'blue'    => [0,   0,   255],
        'red'     => [255, 0,   0],
        'green'   => [0,   128, 0],
        'orange'  => [255, 165, 0],
        'purple'  => [128, 0,   128],
        'black'   => [0,   0,   0],
        'white'   => [255, 255, 255],
        'gray'    => [128, 128, 128],
        'grey'    => [128, 128, 128],
        'teal'    => [0,   128, 128],
        'maroon'  => [128, 0,   0],
        'olive'   => [128, 128, 0],
        'coral'   => [255, 127, 80],
        'crimson' => [220, 20,  60],
        'indigo'  => [75,  0,   130],
        'violet'  => [238, 130, 238],
    ];

    $lower = strtolower(trim($color));

    if (isset($named[$lower])) {
        return array_merge($named[$lower], [255]);
    }

    // Hex notation
    $hex = ltrim($lower, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) >= 6) {
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) : 255,
        ];
    }

    return [0, 0, 128, 255]; // fallback: navy
}

// ── Input parsing ─────────────────────────────────────────────────────────────

$width  = max(200, min(1200, (int)($_GET['width']  ?? 800)));
$height = max(150, min(900,  (int)($_GET['height'] ?? 500)));
$zoom   = isset($_GET['zoom']) ? (int)$_GET['zoom'] : null;

// Collect all route= parameters (may be repeated)
$rawRoutes = [];
foreach ($_GET as $key => $val) {
    if ($key === 'route') {
        // PHP collapses duplicate keys; handle both string and array
        if (is_array($val)) {
            foreach ($val as $v) $rawRoutes[] = $v;
        } else {
            $rawRoutes[] = $val;
        }
    }
}
// Also support route[] notation
if (isset($_GET['route']) && is_array($_GET['route'])) {
    $rawRoutes = $_GET['route'];
}

// Collect color= parameters
$rawColors = [];
foreach ($_GET as $key => $val) {
    if ($key === 'color') {
        if (is_array($val)) {
            foreach ($val as $v) $rawColors[] = $v;
        } else {
            $rawColors[] = $val;
        }
    }
}

$locationParam = $_GET['location'] ?? null;

// ── Geocoding ─────────────────────────────────────────────────────────────────

$errors     = [];
$allCoords  = []; // All points for bounding-box calculation
$routes     = []; // [ [ ['lat'=>, 'lon'=>, 'text'=>, 'color'=>], ... ], ... ]
$locations  = []; // [ ['lat'=>, 'lon'=>, 'text'=>], ... ]

// Process routes
foreach ($rawRoutes as $routeIndex => $rawRoute) {
    $points     = parseJsonParam($rawRoute);
    $colorArray = isset($rawColors[$routeIndex])
        ? parseJsonParam($rawColors[$routeIndex])
        : ['navy'];
    $color      = $colorArray[0] ?? 'navy';

    $routeCoords = [];
    foreach ($points as $point) {
        $name   = $point['point'] ?? '';
        $text   = $point['text']  ?? $name;
        $coords = geocode($name);
        if ($coords === null) {
            $errors[] = $name;
            continue;
        }
        $coords['text']  = $text;
        $coords['color'] = $color;
        $routeCoords[]   = $coords;
        $allCoords[]     = $coords;
    }
    if (!empty($routeCoords)) {
        $routes[] = $routeCoords;
    }
}

// Process standalone locations
if ($locationParam) {
    $points = parseJsonParam($locationParam);
    foreach ($points as $point) {
        $name   = $point['point'] ?? '';
        $text   = $point['text']  ?? $name;
        $coords = geocode($name);
        if ($coords === null) {
            $errors[] = $name;
            continue;
        }
        $coords['text'] = $text;
        $locations[]    = $coords;
        $allCoords[]    = $coords;
    }
}

// Nothing to render
if (empty($allCoords)) {
    header('Content-Type: image/png');
    // Return a blank 1x1 PNG
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
}

// ── Bounding box & centre ─────────────────────────────────────────────────────

$lats = array_column($allCoords, 'lat');
$lons = array_column($allCoords, 'lon');
$minLat = min($lats); $maxLat = max($lats);
$minLon = min($lons); $maxLon = max($lons);
$centerLat = ($minLat + $maxLat) / 2;
$centerLon = ($minLon + $maxLon) / 2;

// Auto zoom when not specified: fit all points
if ($zoom === null) {
    if (count($allCoords) === 1) {
        $zoom = 13;
    } else {
        $latDiff = $maxLat - $minLat;
        $lonDiff = $maxLon - $minLon;
        $diff    = max($latDiff, $lonDiff);
        if      ($diff > 10)  $zoom = 6;
        elseif  ($diff > 5)   $zoom = 7;
        elseif  ($diff > 2)   $zoom = 8;
        elseif  ($diff > 1)   $zoom = 9;
        elseif  ($diff > 0.5) $zoom = 10;
        elseif  ($diff > 0.2) $zoom = 11;
        elseif  ($diff > 0.1) $zoom = 12;
        else                  $zoom = 13;
    }
}

// ── Build the map ─────────────────────────────────────────────────────────────

$map = new OpenStreetMap(new LatLng($centerLat, $centerLon), $zoom, $width, $height);

// Route markers and connecting lines
$routeColors = ['navy', 'red', 'green', 'orange', 'purple'];

foreach ($routes as $routeIndex => $routeCoords) {
    $rgba  = cssColorToRgba($routeCoords[0]['color'] ?? $routeColors[$routeIndex % count($routeColors)]);
    $count = count($routeCoords);

    // Draw straight lines between route points (no ORS — static map only)
    if ($count >= 2) {
        for ($i = 0; $i < $count - 1; $i++) {
            $line = (new Line())
                ->addPoint(new LatLng($routeCoords[$i]['lat'],     $routeCoords[$i]['lon']))
                ->addPoint(new LatLng($routeCoords[$i+1]['lat'],   $routeCoords[$i+1]['lon']))
                ->setStrokeColor($rgba[0], $rgba[1], $rgba[2], $rgba[3])
                ->setStrokeWeight(4);
            $map->addLine($line);
        }
    }

    // Start marker (green circle)
    $startMarkers = (new Markers(__DIR__ . '/mapicons/flag.png'))
        ->setIconSize(32, 37)
        ->setIconAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
        ->addMarker(new LatLng($routeCoords[0]['lat'], $routeCoords[0]['lon']));
    $map->addMarkers($startMarkers);

    // End marker (red flag)
    $endMarkers = (new Markers(__DIR__ . '/mapicons/flag-finish.png'))
        ->setIconSize(32, 37)
        ->setIconAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
        ->addMarker(new LatLng($routeCoords[$count - 1]['lat'], $routeCoords[$count - 1]['lon']));
    $map->addMarkers($endMarkers);

    // Intermediate waypoints
    for ($i = 1; $i < $count - 1; $i++) {
        $waypointMarkers = (new Markers(__DIR__ . '/mapicons/pinpoint.png'))
            ->setIconSize(32, 37)
            ->setIconAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
            ->addMarker(new LatLng($routeCoords[$i]['lat'], $routeCoords[$i]['lon']));
        $map->addMarkers($waypointMarkers);
    }
}

// Standalone location markers
foreach ($locations as $loc) {
    $poiMarkers = (new Markers(__DIR__ . '/mapicons/pinpoint.png'))
        ->setIconSize(32, 37)
        ->setIconAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
        ->addMarker(new LatLng($loc['lat'], $loc['lon']));
    $map->addMarkers($poiMarkers);
}

// ── Output ────────────────────────────────────────────────────────────────────

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
$map->sendPng();
