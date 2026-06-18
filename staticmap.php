<?php
/**
 * staticmap.php — Webwings Maprouter static image endpoint
 *
 * Accepts the same route/location/color URL parameters as maprouter.php
 * and returns a PNG image with OSM background and markers.
 *
 * Requirements:
 *   composer require dantsu/php-osm-static-api:^0.6
 *
 * URL parameters:
 *   route    — JSON array of route points: [{"point":"Amsterdam","text":"Start"}, ...]
 *              Repeat for multiple routes. Points can be place names or "lat,lon".
 *   location — JSON array of standalone markers: [{"point":"Utrecht","text":"POI"}]
 *   color    — JSON array of CSS color names per route: ["navy"] (one per route)
 *   width    — Image width in pixels (default: 800)
 *   height   — Image height in pixels (default: 500)
 *   zoom     — Zoom level override (default: auto-fit)
 */

require_once __DIR__ . '/vendor/autoload.php';

use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\Markers;
use DantSu\OpenStreetMapStaticAPI\Line;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Geocode a place name via Nominatim.
 * Accepts raw coordinates "lat,lon" directly.
 * Returns ['lat' => float, 'lon' => float] or null when not found.
 */
function geocode(string $query): ?array
{
    if (preg_match('/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/', trim($query), $m)) {
        return ['lat' => (float)$m[1], 'lon' => (float)$m[2]];
    }
    $url  = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);
    $opts = ['http' => ['header' => "User-Agent: Webwings-Maprouter/1.0\r\n"]];
    $data = @file_get_contents($url, false, stream_context_create($opts));
    if (!$data) return null;
    $results = json_decode($data, true);
    if (empty($results)) return null;
    return ['lat' => (float)$results[0]['lat'], 'lon' => (float)$results[0]['lon']];
}

/**
 * Parse a JSON array from a URL parameter string. Returns [] on failure.
 */
function parseJsonParam(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Convert a CSS color name or hex (#rgb / #rrggbb) to a 6-char hex string.
 */
function toHex(string $color): string
{
    $named = [
        'navy'    => '000080', 'blue'    => '0000ff', 'red'     => 'ff0000',
        'green'   => '008000', 'orange'  => 'ffa500', 'purple'  => '800080',
        'black'   => '000000', 'white'   => 'ffffff', 'gray'    => '808080',
        'grey'    => '808080', 'teal'    => '008080', 'maroon'  => '800000',
        'olive'   => '808000', 'coral'   => 'ff7f50', 'crimson' => 'dc143c',
        'indigo'  => '4b0082', 'violet'  => 'ee82ee', 'brown'   => 'a52a2a',
    ];
    $lower = strtolower(trim($color));
    if (isset($named[$lower])) return $named[$lower];
    $hex = ltrim($lower, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (preg_match('/^[0-9a-f]{6}$/i', $hex)) return $hex;
    return '000080'; // fallback: navy
}

// ── Input parsing ─────────────────────────────────────────────────────────────

$width  = max(200, min(1200, (int)($_GET['width']  ?? 800)));
$height = max(150, min(900,  (int)($_GET['height'] ?? 500)));
$zoom   = isset($_GET['zoom']) ? (int)$_GET['zoom'] : null;

// PHP collapses duplicate keys — use raw query string to get all route= params
$rawRoutes = [];
$rawColors = [];
parse_str($_SERVER['QUERY_STRING'] ?? '', $parsed);

// Build list from repeated params (PHP gives last value for duplicates)
// So we parse the raw query string manually
$queryParts = explode('&', $_SERVER['QUERY_STRING'] ?? '');
foreach ($queryParts as $part) {
    if (strpos($part, '=') === false) continue;
    [$key, $val] = explode('=', $part, 2);
    $key = urldecode($key);
    $val = urldecode($val);
    if ($key === 'route') $rawRoutes[] = $val;
    if ($key === 'color') $rawColors[] = $val;
}

$locationParam = $_GET['location'] ?? null;

// ── Geocoding ─────────────────────────────────────────────────────────────────

$errors    = [];
$allCoords = [];
$routes    = [];
$locations = [];

$defaultColors = ['000080', 'cc0000', '006600', 'cc6600', '660066'];

foreach ($rawRoutes as $routeIndex => $rawRoute) {
    $points     = parseJsonParam($rawRoute);
    $colorArray = isset($rawColors[$routeIndex]) ? parseJsonParam($rawColors[$routeIndex]) : ['navy'];
    $hexColor   = toHex($colorArray[0] ?? 'navy');

    $routeCoords = [];
    foreach ($points as $point) {
        $name   = $point['point'] ?? '';
        $text   = $point['text']  ?? $name;
        $coords = geocode($name);
        if ($coords === null) { $errors[] = $name; continue; }
        $coords['text']  = $text;
        $routeCoords[]   = $coords;
        $allCoords[]     = $coords;
    }
    if (!empty($routeCoords)) {
        $routes[] = ['coords' => $routeCoords, 'color' => $hexColor];
    }
}

if ($locationParam) {
    foreach (parseJsonParam($locationParam) as $point) {
        $name   = $point['point'] ?? '';
        $text   = $point['text']  ?? $name;
        $coords = geocode($name);
        if ($coords === null) { $errors[] = $name; continue; }
        $coords['text'] = $text;
        $locations[]    = $coords;
        $allCoords[]    = $coords;
    }
}

if (empty($allCoords)) {
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
}

// ── Centre & zoom ─────────────────────────────────────────────────────────────

$lats   = array_column($allCoords, 'lat');
$lons   = array_column($allCoords, 'lon');
$minLat = min($lats); $maxLat = max($lats);
$minLon = min($lons); $maxLon = max($lons);

if ($zoom === null) {
    if (count($allCoords) === 1) {
        $zoom = 13;
    } else {
        $diff = max($maxLat - $minLat, $maxLon - $minLon);
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

// ── Build map using bounding box ──────────────────────────────────────────────

$map = OpenStreetMap::createFromBoundingBox(
    new LatLng($maxLat, $minLon),
    new LatLng($minLat, $maxLon),
    40,
    $width,
    $height
);

// ── Draw routes ───────────────────────────────────────────────────────────────

$startIcon = __DIR__ . '/mapicons/start.png';
$endIcon   = __DIR__ . '/mapicons/stop.png';
$pinIcon   = __DIR__ . '/mapicons/pinpoint.png';

// Fallback to a built-in marker if custom icons don't exist
$hasStart  = file_exists($startIcon);
$hasEnd    = file_exists($endIcon);
$hasPin    = file_exists($pinIcon);

foreach ($routes as $route) {
    $coords = $route['coords'];
    $color  = $route['color'];
    $count  = count($coords);

    // Draw line through all route points
    if ($count >= 2) {
        $line = new Line('#' . $color, 4);
        foreach ($coords as $c) {
            $line->addPoint(new LatLng($c['lat'], $c['lon']));
        }
        $map->addLine($line);
    }

    // Start marker
    $icon = $hasStart ? $startIcon : $pinIcon;
    if ($hasStart || $hasPin) {
        $map->addMarkers(
            (new Markers($icon))
                ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                ->addMarker(new LatLng($coords[0]['lat'], $coords[0]['lon']))
        );
    }

    // Intermediate waypoints
    for ($i = 1; $i < $count - 1; $i++) {
        if ($hasPin) {
            $map->addMarkers(
                (new Markers($pinIcon))
                    ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                    ->addMarker(new LatLng($coords[$i]['lat'], $coords[$i]['lon']))
            );
        }
    }

    // End marker
    $icon = $hasEnd ? $endIcon : $pinIcon;
    if ($hasEnd || $hasPin) {
        $map->addMarkers(
            (new Markers($icon))
                ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                ->addMarker(new LatLng($coords[$count - 1]['lat'], $coords[$count - 1]['lon']))
        );
    }
}

// ── Draw standalone locations ─────────────────────────────────────────────────

foreach ($locations as $loc) {
    if ($hasPin) {
        $map->addMarkers(
            (new Markers($pinIcon))
                ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                ->addMarker(new LatLng($loc['lat'], $loc['lon']))
        );
    }
}

// ── Output PNG ────────────────────────────────────────────────────────────────

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
$map->sendPng();
