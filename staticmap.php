<?php
/**
 * staticmap.php — Webwings Maprouter static image proxy
 *
 * Geocodes route/location parameters via Nominatim, builds a
 * Mapbox Static API URL, fetches the image server-side via cURL
 * and streams it as PNG.
 *
 * URL parameters:
 *   route    — JSON array of route points (repeat for multiple routes)
 *   location — JSON array of standalone markers
 *   color    — JSON array of colors per route (CSS names or hex)
 *   width    — image width in pixels (default: 800, max: 1280)
 *   height   — image height in pixels (default: 500, max: 1280)
 *   style    — mapbox style: streets, outdoors, satellite, satellite-streets (default: streets)
 */

require_once __DIR__ . '/config.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function geocode(string $query): ?array
{
    if (preg_match('/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/', trim($query), $m)) {
        return ['lat' => (float)$m[1], 'lon' => (float)$m[2]];
    }
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'Webwings-Maprouter/1.0',
        CURLOPT_TIMEOUT        => 5,
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    if (!$data) return null;
    $results = json_decode($data, true);
    if (empty($results)) return null;
    return ['lat' => (float)$results[0]['lat'], 'lon' => (float)$results[0]['lon']];
}

function parseJsonParam(string $raw): array
{
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function toHex(string $color): string
{
    $named = [
        'navy' => '000080', 'blue' => '0000ff', 'red' => 'ff0000',
        'green' => '008000', 'orange' => 'ffa500', 'purple' => '800080',
        'black' => '000000', 'teal' => '008080', 'coral' => 'ff7f50',
        'gray' => '808080', 'grey' => '808080', 'brown' => 'a52a2a',
    ];
    $lower = strtolower(trim($color));
    if (isset($named[$lower])) return $named[$lower];
    $hex = ltrim($lower, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (preg_match('/^[0-9a-f]{6}$/i', $hex)) return $hex;
    return '000080';
}

// ── Input parsing ─────────────────────────────────────────────────────────────

$width  = max(200, min(1280, (int)($_GET['width']  ?? 800)));
$height = max(150, min(1280, (int)($_GET['height'] ?? 500)));
$style  = in_array($_GET['style'] ?? '', ['streets', 'outdoors', 'satellite', 'satellite-streets'])
    ? $_GET['style'] : 'streets';

$rawRoutes = [];
$rawColors = [];
foreach (explode('&', $_SERVER['QUERY_STRING'] ?? '') as $part) {
    if (strpos($part, '=') === false) continue;
    [$key, $val] = explode('=', $part, 2);
    $key = urldecode($key);
    $val = urldecode($val);
    if ($key === 'route') $rawRoutes[] = $val;
    if ($key === 'color') $rawColors[] = $val;
}
$locationParam = $_GET['location'] ?? null;

// ── Geocoding ─────────────────────────────────────────────────────────────────

$allCoords = [];
$routes    = [];
$locations = [];

foreach ($rawRoutes as $i => $rawRoute) {
    $points     = parseJsonParam($rawRoute);
    $colorArray = isset($rawColors[$i]) ? parseJsonParam($rawColors[$i]) : ['navy'];
    $hex        = toHex($colorArray[0] ?? 'navy');
    $coords     = [];
    foreach ($points as $p) {
        $c = geocode($p['point'] ?? '');
        if ($c) { $coords[] = $c; $allCoords[] = $c; }
    }
    if (!empty($coords)) $routes[] = ['coords' => $coords, 'color' => $hex];
}

if ($locationParam) {
    foreach (parseJsonParam($locationParam) as $p) {
        $c = geocode($p['point'] ?? '');
        if ($c) { $locations[] = $c; $allCoords[] = $c; }
    }
}

if (empty($allCoords)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Geen geldige locaties gevonden.';
    exit;
}

// ── Mapbox overlays opbouwen ──────────────────────────────────────────────────

$overlays = [];

foreach ($routes as $i => $route) {
    $coords = $route['coords'];
    $hex    = $route['color'];
    $count  = count($coords);

    // Startmarker (groen)
    $overlays[] = 'pin-s-s+00aa00(' . $coords[0]['lon'] . ',' . $coords[0]['lat'] . ')';

    // Tussenstops
    for ($j = 1; $j < $count - 1; $j++) {
        $overlays[] = 'pin-s+' . $hex . '(' . $coords[$j]['lon'] . ',' . $coords[$j]['lat'] . ')';
    }

    // Eindmarker (rood)
    if ($count > 1) {
        $overlays[] = 'pin-s-f+cc0000(' . $coords[$count-1]['lon'] . ',' . $coords[$count-1]['lat'] . ')';
    }
}

// Losse locaties (blauw)
foreach ($locations as $loc) {
    $overlays[] = 'pin-s-star+0057b8(' . $loc['lon'] . ',' . $loc['lat'] . ')';
}

// ── Positie bepalen ───────────────────────────────────────────────────────────

$lats = array_column($allCoords, 'lat');
$lons = array_column($allCoords, 'lon');

if (count($allCoords) === 1) {
    $position = $allCoords[0]['lon'] . ',' . $allCoords[0]['lat'] . ',13,0';
} else {
    $position = 'auto';
}

// ── Mapbox URL samenstellen ───────────────────────────────────────────────────

$styleId    = 'mapbox/' . $style . '-v12';
$overlayStr = implode(',', $overlays);
$mapboxUrl  = "https://api.mapbox.com/styles/v1/{$styleId}/static/{$overlayStr}/{$position}/{$width}x{$height}@2x"
            . "?access_token={$mapboxToken}";

// ── Fetch via cURL & proxy ────────────────────────────────────────────────────

$ch = curl_init($mapboxUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'Webwings-Maprouter/1.0',
    CURLOPT_TIMEOUT        => 10,
]);
$img  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($code === 200 && $img && strlen($img) > 100) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    echo $img;
} else {
    http_response_code(502);
    header('Content-Type: text/plain');
    echo "Kon kaartafbeelding niet ophalen (HTTP {$code}) — {$err}";
}
