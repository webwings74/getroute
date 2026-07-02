<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Mapbuilder to create URL for maprouter.php -- (c) Webwings 2025 -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webwings maprouter — URL Builder</title>
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Verdana, Geneva, sans-serif;
            font-size: 13px;
            background: #f0f4f8;
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page header ── */
        header {
            background: #1a2a3a;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-shrink: 0;
        }
        header h1 { font-size: 16px; font-weight: 700; letter-spacing: .04em; }
        header span { font-size: 11px; color: #8fa8c0; }

        /* ── Main layout: sidebar + preview ── */
        main {
            display: flex;
            flex: 1;
            gap: 0;
            overflow: hidden;
        }

        /* ── Form sidebar ── */
        #sidebar {
            width: 420px;
            flex-shrink: 0;
            overflow-y: auto;
            padding: 16px;
            border-right: 1px solid #d0dae4;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── Section blocks ── */
        .section-block {
            border: 1px solid #d0dae4;
            border-radius: 7px;
            overflow: hidden;
        }
        .section-block-header {
            background: #f0f4f8;
            padding: 9px 12px;
            font-weight: 700;
            font-size: 12px;
            color: #1a2a3a;
            letter-spacing: .03em;
            border-bottom: 1px solid #d0dae4;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-block-body { padding: 12px; display: flex; flex-direction: column; gap: 10px; }

        /* ── Route card ── */
        .route-card {
            border: 1px solid #d0dae4;
            border-radius: 7px;
            overflow: hidden;
            position: relative;
        }
        .route-card-bar {
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 5px;
            background: navy; /* updated dynamically */
            border-radius: 7px 0 0 7px;
        }
        .route-card-header {
            background: #f7f9fb;
            padding: 8px 12px 8px 18px;
            font-weight: 700;
            font-size: 12px;
            color: #1a2a3a;
            border-bottom: 1px solid #e8edf2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .route-card-body { padding: 10px 12px 10px 18px; display: flex; flex-direction: column; gap: 8px; }

        /* ── Stop row inside a route ── */
        .stop-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            background: #f7f9fb;
            border: 1px solid #e8edf2;
            border-radius: 5px;
            padding: 7px 8px;
        }
        .stop-number {
            background: #1a2a3a;
            color: #fff;
            border-radius: 50%;
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .stop-fields { flex: 1; display: flex; flex-direction: column; gap: 4px; }

        /* Move/reorder buttons on a stop row */
        .stop-actions {
            display: flex;
            flex-wrap: wrap;
            align-content: flex-start;
            gap: 3px;
            width: 44px;
            flex-shrink: 0;
        }
        .btn-move {
            background: #e8edf2;
            color: #1a2a3a;
            border: none;
            border-radius: 4px;
            width: 20px;
            height: 20px;
            font-size: 10px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-move:hover:not(:disabled) { background: #d0dae4; }
        .btn-move:disabled { opacity: .35; cursor: default; }
        .stop-actions .btn-danger {
            width: 20px;
            height: 20px;
            font-size: 14px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Location marker row ── */
        .location-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            background: #f7f9fb;
            border: 1px solid #e8edf2;
            border-radius: 5px;
            padding: 7px 8px;
        }
        .location-icon {
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .location-fields { flex: 1; display: flex; flex-direction: column; gap: 4px; }

        /* "Move into route" controls on a location row */
        .location-move-row {
            display: flex;
            gap: 4px;
            align-items: center;
            margin-top: 2px;
        }
        .location-move-row select {
            font-size: 10px;
            padding: 2px 3px;
            flex: 1;
            min-width: 0;
        }
        .btn-move-into {
            background: #e8edf2;
            color: #1a2a3a;
            border: none;
            border-radius: 4px;
            font-size: 10px;
            padding: 3px 6px;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-move-into:hover:not(:disabled) { background: #d0dae4; }
        .btn-move-into:disabled { opacity: .35; cursor: default; }

        /* ── Form controls ── */
        label {
            font-size: 10px;
            color: #5a7a9a;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }
        input[type="text"], input[type="url"], input[type="number"], select {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #c8d4e0;
            border-radius: 4px;
            font-family: inherit;
            font-size: 12px;
            color: #2c3e50;
            background: #fff;
            outline: none;
            transition: border-color .15s;
        }
        input[type="text"]:focus,
        input[type="url"]:focus,
        input[type="number"]:focus,
        select:focus { border-color: #5b9bd5; }

        /* Color swatches + input */
        .color-row {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
        }
        .color-swatch {
            width: 22px; height: 22px;
            border-radius: 4px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform .1s, border-color .1s;
            flex-shrink: 0;
        }
        .color-swatch:hover { transform: scale(1.15); }
        .color-swatch.selected { border-color: #1a2a3a; transform: scale(1.15); }
        .color-custom {
            flex: 1;
            min-width: 80px;
        }

        /* Options grid */
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .option-full { grid-column: 1 / -1; }

        /* Checkbox rows */
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .checkbox-row input[type="checkbox"] { width: 15px; height: 15px; cursor: pointer; accent-color: #1a2a3a; }
        .checkbox-row span { font-size: 12px; color: #2c3e50; }

        /* ── Buttons ── */
        button {
            font-family: inherit;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            transition: background .15s, opacity .15s;
        }
        .btn-primary {
            background: #1a2a3a;
            color: #fff;
        }
        .btn-primary:hover { background: #243447; }
        .btn-secondary {
            background: #e8edf2;
            color: #1a2a3a;
        }
        .btn-secondary:hover { background: #d0dae4; }
        .btn-danger {
            background: none;
            color: #c0392b;
            font-size: 16px;
            padding: 2px 6px;
            line-height: 1;
        }
        .btn-danger:hover { background: #fdecea; }
        .btn-add {
            background: none;
            color: #5b9bd5;
            font-size: 12px;
            padding: 4px 0;
            text-align: left;
        }
        .btn-add:hover { color: #1a2a3a; }
        .btn-open {
            background: #27ae60;
            color: #fff;
            font-size: 13px;
            padding: 10px 18px;
            width: 100%;
        }
        .btn-open:hover { background: #219a52; }
        .btn-staticmap {
            background: #1a6bbf;
            color: #fff;
            font-size: 13px;
            padding: 10px 18px;
            width: 100%;
        }
        .btn-staticmap:hover { background: #155a9e; }
        .btn-copy {
            background: #e8edf2;
            color: #1a2a3a;
            font-size: 12px;
            padding: 7px 14px;
            width: 100%;
        }
        .btn-copy:hover { background: #d0dae4; }
        .btn-copy.copied { background: #d5f0e0; color: #1a7a40; }

        /* ── URL preview panel ── */
        #preview-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            gap: 14px;
            overflow-y: auto;
        }
        #preview-panel h2 {
            font-size: 13px;
            font-weight: 700;
            color: #1a2a3a;
            letter-spacing: .03em;
        }
        #url-box {
            background: #1a2a3a;
            color: #a8d8f0;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 14px;
            border-radius: 7px;
            word-break: break-all;
            line-height: 1.6;
            min-height: 80px;
            flex-shrink: 0;
        }
        #url-box .url-base { color: #fff; font-weight: 700; }
        #url-box .url-key  { color: #f9ca24; }
        #url-box .url-val  { color: #a8d8f0; }
        #url-box .url-sep  { color: #5b9bd5; }

        /* Action buttons */
        #action-buttons { display: flex; flex-direction: column; gap: 8px; }

        /* Summary */
        #summary {
            background: #fff;
            border: 1px solid #d0dae4;
            border-radius: 7px;
            padding: 12px;
            font-size: 12px;
            color: #5a7a9a;
            line-height: 1.7;
        }
        #summary strong { color: #1a2a3a; }

        /* ── Import URL section ── */
        #import-section { display: flex; flex-direction: column; gap: 8px; }
        #import-section h2 { font-size: 13px; font-weight: 700; color: #1a2a3a; letter-spacing: .03em; }
        #import-row { display: flex; gap: 8px; }
        #import-url-input { flex: 1; }
        #import-feedback {
            font-size: 11px;
            padding: 6px 10px;
            border-radius: 5px;
            display: none;
        }
        #import-feedback.ok  { background: #d5f0e0; color: #1a7a40; }
        #import-feedback.err { background: #fdecea; color: #c0392b; }

        /* ── Responsive ── */
        @media (max-width: 700px) {
            main { flex-direction: column; overflow: visible; }
            #sidebar { width: 100%; border-right: none; border-bottom: 1px solid #d0dae4; }
        }
    </style>
</head>
<body>

<header>
    <h1>maprouter — URL Builder</h1>
    <span>Webwings Route Maps</span>
</header>

<main>
    <!-- ── Form sidebar ── -->
    <div id="sidebar">

        <!-- Routes -->
        <div class="section-block">
            <div class="section-block-header">
                Routes
                <button class="btn-secondary" onclick="addRoute()">+ Add route</button>
            </div>
            <div class="section-block-body" id="routes-container">
                <!-- Route cards injected here -->
            </div>
        </div>

        <!-- Standalone locations -->
        <div class="section-block">
            <div class="section-block-header">
                Location markers
                <button class="btn-secondary" onclick="addLocation()">+ Add marker</button>
            </div>
            <div class="section-block-body" id="locations-container">
                <!-- Location rows injected here -->
            </div>
        </div>

        <!-- Options -->
        <div class="section-block">
            <div class="section-block-header">Options</div>
            <div class="section-block-body">
                <div class="options-grid">
                    <div class="option-full">
                        <label for="opt-title">Page title</label>
                        <input type="text" id="opt-title" placeholder="Optional — sets the browser tab title" oninput="updatePreview()">
                    </div>
                    <div>
                        <label for="opt-profile">Routing profile</label>
                        <select id="opt-profile" onchange="updatePreview()">
                            <option value="driving-car">🚗 Driving (car)</option>
                            <option value="cycling-regular">🚲 Cycling</option>
                            <option value="cycling-road">🚴 Road cycling</option>
                            <option value="cycling-mountain">⛰️ Mountain bike</option>
                            <option value="cycling-electric">⚡ E-bike</option>
                            <option value="foot-hiking">🥾 Hiking</option>
                            <option value="foot-walking">🚶 Walking</option>
                            <option value="wheelchair">♿ Wheelchair</option>
                        </select>
                    </div>
                    <div>
                        <label for="opt-layer">Map layer</label>
                        <select id="opt-layer" onchange="updatePreview()">
                            <option value="">Default (OSM)</option>
                            <option value="topo">Tracestrack Topographic</option>
                            <option value="cycle">Thunderforest Cycling</option>
                            <option value="transport">Thunderforest Transport</option>
                            <option value="landscape">Thunderforest Landscape</option>
                            <option value="outdoors">Thunderforest Outdoors</option>
                            <option value="transport-dark">Thunderforest Transport Dark</option>
                            <option value="spinal-map">Thunderforest Spinal Map</option>
                            <option value="pioneer">Thunderforest Pioneer</option>
                            <option value="mobile-atlas">Thunderforest Mobile Atlas</option>
                            <option value="neighbourhood">Thunderforest Neighbourhood</option>
                            <option value="atlas">Thunderforest Atlas</option>
                        </select>
                    </div>
                    <div>
                        <label for="opt-zoom">Zoom override</label>
                        <input type="number" id="opt-zoom" min="1" max="20" placeholder="1–20 (optional)" oninput="updatePreview()">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:7px;padding-top:2px;">
                        <label class="checkbox-row">
                            <input type="checkbox" id="opt-section" onchange="updatePreview()">
                            <span>Segment stats in popups</span>
                        </label>
                        <label class="checkbox-row">
                            <input type="checkbox" id="opt-table" onchange="updatePreview()">
                            <span>Route table panel</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /#sidebar -->

    <!-- ── URL preview panel ── -->
    <div id="preview-panel">
        <h2>Generated URL</h2>
        <div id="url-box"><span class="url-base">https://</span><span style="color:#8fa8c0">— add a route or location to get started —</span></div>
        <div id="action-buttons">
            <button class="btn-open" onclick="openMap()">Open in maprouter ↗</button>
            <button class="btn-staticmap" onclick="openStaticMap()">Open in Staticmap ↗</button>
            <button class="btn-copy" id="copy-btn" onclick="copyUrl()">Copy URL</button>
        </div>
        <div id="summary">Add routes and locations using the form on the left.</div>

        <div id="import-section">
            <h2>Import URL</h2>
            <div id="import-row">
                <input type="text" id="import-url-input" placeholder="Paste a maprouter.php URL here…"
                    oninput="clearImportFeedback()">
                <button class="btn-secondary" onclick="importUrl()">Load</button>
            </div>
            <div id="import-feedback"></div>
        </div>
    </div>

</main>

<script>
    /**
     * maprouter-builder.php
     *
     * Client-side URL builder for maprouter.php.
     * All logic runs in the browser — no server-side PHP is used.
     *
     * State is kept in two arrays: routes[] and locations[].
     * Every user interaction calls updatePreview() to rebuild the URL display.
     */

    // ── Configuration ────────────────────────────────────────────────────────

    /** Base URL of maprouter.php — adjust to match your server. */
    const MAPROUTER_URL = "maprouter.php";

    /** Preset color swatches shown in the route color picker. */
    const COLOR_SWATCHES = [
        { hex: "#000080", name: "navy"    },
        { hex: "#e74c3c", name: "red"     },
        { hex: "#27ae60", name: "green"   },
        { hex: "#f39c12", name: "orange"  },
        { hex: "#8e44ad", name: "purple"  },
        { hex: "#2980b9", name: "blue"    },
        { hex: "#1abc9c", name: "teal"    },
        { hex: "#e67e22", name: "amber"   },
    ];

    // ── State ────────────────────────────────────────────────────────────────

    /** @type {Array<{stops: Array<{address,text,icon}>, colors: string[]}>} */
    let routes = [];

    /** @type {Array<{address: string, text: string, icon: string}>} */
    let locations = [];

    // ── Route management ─────────────────────────────────────────────────────

    /**
     * Adds a new empty route card to the sidebar and the routes[] state array.
     */
    function addRoute() {
        const index = routes.length;
        routes.push({ stops: [{ address: "", text: "", icon: "" }], colors: ["#000080"] });
        renderRoute(index);
        refreshLocationsPanel(); // a new route becomes available as a "move into route" target
        updatePreview();
    }

    /**
     * Renders (or re-renders) a route card at the given index.
     *
     * @param {number} routeIndex - Zero-based index into routes[]
     */
    function renderRoute(routeIndex) {
        const container = document.getElementById("routes-container");
        const route     = routes[routeIndex];
        const color     = route.colors[0] || "#000080";

        // Remove existing card if re-rendering
        const existing = document.getElementById(`route-card-${routeIndex}`);
        if (existing) existing.remove();

        const card = document.createElement("div");
        card.className = "route-card";
        card.id = `route-card-${routeIndex}`;

        card.innerHTML = `
            <div class="route-card-bar" id="route-bar-${routeIndex}" style="background:${color}"></div>
            <div class="route-card-header">
                <span>Route ${routeIndex + 1}</span>
                <button class="btn-danger" onclick="removeRoute(${routeIndex})" title="Remove route">✕</button>
            </div>
            <div class="route-card-body" id="route-body-${routeIndex}">
                <div id="stops-container-${routeIndex}"></div>
                <button class="btn-add" onclick="addStop(${routeIndex})">+ Add stop</button>
                <div>
                    <label>Route colour</label>
                    ${renderColorPicker(routeIndex)}
                </div>
            </div>
        `;

        const nextSibling = container.children[routeIndex];
        if (nextSibling) {
            container.insertBefore(card, nextSibling);
        } else {
            container.appendChild(card);
        }

        // Render stops
        route.stops.forEach((_, stopIndex) => renderStop(routeIndex, stopIndex));
    }

    /**
     * Builds the HTML string for the color picker of a route.
     *
     * @param {number} routeIndex
     * @returns {string} HTML string
     */
    function renderColorPicker(routeIndex) {
        const currentColor = routes[routeIndex].colors[0] || "#000080";
        const swatches = COLOR_SWATCHES.map(s => {
            const selected = s.hex.toLowerCase() === currentColor.toLowerCase() ? " selected" : "";
            return `<div class="color-swatch${selected}" style="background:${s.hex}" title="${s.name}"
                        onclick="selectColor(${routeIndex}, '${s.hex}', this)"></div>`;
        }).join("");
        return `
            <div class="color-row">
                ${swatches}
                <input type="text" class="color-custom" id="color-custom-${routeIndex}"
                    value="${currentColor}" placeholder="#rrggbb or name"
                    oninput="onCustomColor(${routeIndex}, this.value)"
                    style="border-left: 4px solid ${currentColor}">
            </div>
        `;
    }

    /**
     * Handles swatch click: updates the route color and refreshes the UI.
     *
     * @param {number} routeIndex
     * @param {string} hex        - Selected hex color
     * @param {HTMLElement} el    - The clicked swatch element
     */
    function selectColor(routeIndex, hex, el) {
        routes[routeIndex].colors = [hex];
        // Update swatch selection visuals
        el.closest(".color-row").querySelectorAll(".color-swatch").forEach(s => s.classList.remove("selected"));
        el.classList.add("selected");
        // Update custom input and left border
        const customInput = document.getElementById(`color-custom-${routeIndex}`);
        customInput.value = hex;
        customInput.style.borderLeft = `4px solid ${hex}`;
        // Update card bar
        document.getElementById(`route-bar-${routeIndex}`).style.background = hex;
        updatePreview();
    }

    /**
     * Handles typing in the custom color input field.
     *
     * @param {number} routeIndex
     * @param {string} value - Current input value
     */
    function onCustomColor(routeIndex, value) {
        routes[routeIndex].colors = [value];
        document.getElementById(`route-bar-${routeIndex}`).style.background = value;
        document.getElementById(`color-custom-${routeIndex}`).style.borderLeft = `4px solid ${value}`;
        // Deselect all swatches
        document.querySelectorAll(`#route-card-${routeIndex} .color-swatch`).forEach(s => s.classList.remove("selected"));
        updatePreview();
    }

    /**
     * Removes a route from state and re-renders all route cards.
     *
     * @param {number} routeIndex
     */
    function removeRoute(routeIndex) {
        routes.splice(routeIndex, 1);
        document.getElementById("routes-container").innerHTML = "";
        routes.forEach((_, i) => renderRoute(i));
        refreshLocationsPanel(); // route indices shifted — refresh "move into route" dropdowns
        updatePreview();
    }

    // ── Stop management ──────────────────────────────────────────────────────

    /**
     * Adds a new empty stop to a route and renders it.
     *
     * @param {number} routeIndex
     */
    function addStop(routeIndex) {
        routes[routeIndex].stops.push({ address: "", text: "", icon: "" });
        // Re-render every row (not just append) so the previous last row's
        // "remove"/"move down" controls reflect that it's no longer last.
        rerenderStops(routeIndex);
        updatePreview();
    }

    /**
     * Renders a stop row inside a route card.
     *
     * @param {number} routeIndex
     * @param {number} stopIndex
     */
    function renderStop(routeIndex, stopIndex) {
        const container  = document.getElementById(`stops-container-${routeIndex}`);
        const stopsCount = routes[routeIndex].stops.length;
        const stop       = routes[routeIndex].stops[stopIndex];

        const row = document.createElement("div");
        row.className = "stop-row";
        row.id = `stop-${routeIndex}-${stopIndex}`;

        row.innerHTML = `
            <div class="stop-number">${stopIndex + 1}</div>
            <div class="stop-fields">
                <input type="text" placeholder="Address or place name" value="${esc(stop.address)}"
                    oninput="updateStop(${routeIndex}, ${stopIndex}, 'address', this.value)">
                <input type="text" placeholder="Popup text (optional)" value="${esc(stop.text)}"
                    oninput="updateStop(${routeIndex}, ${stopIndex}, 'text', this.value)">
                <input type="url" placeholder="Icon URL (optional)" value="${esc(stop.icon)}"
                    oninput="updateStop(${routeIndex}, ${stopIndex}, 'icon', this.value)">
            </div>
            <div class="stop-actions">
                <button class="btn-move" onclick="moveStopUp(${routeIndex}, ${stopIndex})" title="Move up" ${stopIndex === 0 ? "disabled" : ""}>&#9650;</button>
                <button class="btn-move" onclick="moveStopDown(${routeIndex}, ${stopIndex})" title="Move down" ${stopIndex === stopsCount - 1 ? "disabled" : ""}>&#9660;</button>
                <button class="btn-move" onclick="moveStopToLocation(${routeIndex}, ${stopIndex})" title="Move to a standalone location marker">&#128205;</button>
                ${stopsCount > 1
                    ? `<button class="btn-danger" onclick="removeStop(${routeIndex}, ${stopIndex})" title="Remove stop">✕</button>`
                    : ""}
            </div>
        `;

        container.appendChild(row);
    }

    /**
     * Clears and re-renders every stop row of a route from current state.
     * Used after reordering, removing, or moving a stop out.
     *
     * @param {number} routeIndex
     */
    function rerenderStops(routeIndex) {
        const body = document.getElementById(`stops-container-${routeIndex}`);
        body.innerHTML = "";
        routes[routeIndex].stops.forEach((_, i) => renderStop(routeIndex, i));
    }

    /**
     * Swaps a stop with its neighbour in the given direction.
     *
     * @param {number} routeIndex
     * @param {number} stopIndex
     * @param {number} direction - -1 to move up, +1 to move down
     */
    function moveStop(routeIndex, stopIndex, direction) {
        const stops  = routes[routeIndex].stops;
        const target = stopIndex + direction;
        if (target < 0 || target >= stops.length) return;
        [stops[stopIndex], stops[target]] = [stops[target], stops[stopIndex]];
        rerenderStops(routeIndex);
        updatePreview();
    }

    function moveStopUp(routeIndex, stopIndex)   { moveStop(routeIndex, stopIndex, -1); }
    function moveStopDown(routeIndex, stopIndex) { moveStop(routeIndex, stopIndex, 1); }

    /**
     * Removes a stop from a route and moves it into the standalone location
     * markers list. If the route drops below two stops, any remaining stop
     * is also moved to locations and the (now unroutable) route is removed.
     *
     * @param {number} routeIndex
     * @param {number} stopIndex
     */
    function moveStopToLocation(routeIndex, stopIndex) {
        const route = routes[routeIndex];
        if (!route) return;

        const [stop] = route.stops.splice(stopIndex, 1);
        locations.push({ address: stop.address, text: stop.text, icon: stop.icon });

        if (route.stops.length < 2) {
            route.stops.forEach(s => locations.push({ address: s.address, text: s.text, icon: s.icon }));
            routes.splice(routeIndex, 1);
            document.getElementById("routes-container").innerHTML = "";
            routes.forEach((_, i) => renderRoute(i));
        } else {
            rerenderStops(routeIndex);
        }

        refreshLocationsPanel();
        updatePreview();
    }

    /**
     * Updates a single field on a stop in state and triggers URL preview refresh.
     *
     * @param {number} routeIndex
     * @param {number} stopIndex
     * @param {string} field   - 'address', 'text', or 'icon'
     * @param {string} value
     */
    function updateStop(routeIndex, stopIndex, field, value) {
        routes[routeIndex].stops[stopIndex][field] = value;
        updatePreview();
    }

    /**
     * Removes a stop from a route and re-renders the route card.
     *
     * @param {number} routeIndex
     * @param {number} stopIndex
     */
    function removeStop(routeIndex, stopIndex) {
        routes[routeIndex].stops.splice(stopIndex, 1);
        rerenderStops(routeIndex);
        updatePreview();
    }

    // ── Location management ──────────────────────────────────────────────────

    /**
     * Adds a new empty standalone location marker and renders it.
     */
    function addLocation() {
        const index = locations.length;
        locations.push({ address: "", text: "", icon: "" });
        renderLocation(index);
        updatePreview();
    }

    /**
     * Renders a location marker row.
     *
     * @param {number} index
     */
    function renderLocation(index) {
        const container = document.getElementById("locations-container");
        const loc       = locations[index];
        const hasRoutes = routes.length > 0;
        const routeOptions = routes.map((_, i) => `<option value="${i}">Route ${i + 1}</option>`).join("");

        const row = document.createElement("div");
        row.className = "location-row";
        row.id = `location-row-${index}`;

        row.innerHTML = `
            <div class="location-icon">📍</div>
            <div class="location-fields">
                <input type="text" placeholder="Address or place name" value="${esc(loc.address)}"
                    oninput="updateLocation(${index}, 'address', this.value)">
                <input type="text" placeholder="Popup text (optional)" value="${esc(loc.text)}"
                    oninput="updateLocation(${index}, 'text', this.value)">
                <input type="url" placeholder="Icon URL (optional)" value="${esc(loc.icon)}"
                    oninput="updateLocation(${index}, 'icon', this.value)">
                <div class="location-move-row">
                    <select id="loc-target-route-${index}" ${hasRoutes ? "" : "disabled"}>
                        <option value="" selected disabled>${hasRoutes ? "Choose route…" : "No routes yet"}</option>
                        ${routeOptions}
                    </select>
                    <select id="loc-target-pos-${index}" ${hasRoutes ? "" : "disabled"}>
                        <option value="end" selected>End</option>
                        <option value="start">Start</option>
                    </select>
                    <button class="btn-move-into" onclick="moveLocationToRoute(${index})" ${hasRoutes ? "" : "disabled"} title="Move into route">&#8617; To route</button>
                </div>
            </div>
            <button class="btn-danger" onclick="removeLocation(${index})" title="Remove marker">✕</button>
        `;

        container.appendChild(row);
    }

    /**
     * Clears and re-renders every location row from current state. Also used
     * to refresh each row's "move into route" dropdown after routes[] changes.
     */
    function refreshLocationsPanel() {
        document.getElementById("locations-container").innerHTML = "";
        locations.forEach((_, i) => renderLocation(i));
    }

    /**
     * Moves a standalone location marker into the route/position selected in
     * its row's dropdowns.
     *
     * @param {number} locationIndex
     */
    function moveLocationToRoute(locationIndex) {
        const routeValue = document.getElementById(`loc-target-route-${locationIndex}`).value;
        if (routeValue === "") {
            alert("Please choose which route to move this location into first.");
            return;
        }
        const routeIndex = parseInt(routeValue, 10);
        const position    = document.getElementById(`loc-target-pos-${locationIndex}`).value;
        const route       = routes[routeIndex];
        if (!route) return;

        const [loc] = locations.splice(locationIndex, 1);
        const stop  = { address: loc.address, text: loc.text, icon: loc.icon };
        if (position === "start") {
            route.stops.unshift(stop);
        } else {
            route.stops.push(stop);
        }

        refreshLocationsPanel();
        rerenderStops(routeIndex);
        updatePreview();
    }

    /**
     * Updates a field on a location in state and refreshes the URL preview.
     *
     * @param {number} index
     * @param {string} field
     * @param {string} value
     */
    function updateLocation(index, field, value) {
        locations[index][field] = value;
        updatePreview();
    }

    /**
     * Removes a location marker and re-renders all location rows.
     *
     * @param {number} index
     */
    function removeLocation(index) {
        locations.splice(index, 1);
        refreshLocationsPanel();
        updatePreview();
    }

    // ── URL building ─────────────────────────────────────────────────────────

    /**
     * Builds and returns the complete maprouter.php URL based on current state.
     * Returns null if no routes or locations have been filled in.
     *
     * @returns {string|null}
     */
    function buildUrl() {
        const parts = [];
        let hasContent = false;

        // Routes and auto-promoted single-stop location markers
        const autoLocations = []; // Single-stop route blocks are promoted to location markers

        routes.forEach(route => {
            const stops = route.stops
                .filter(s => s.address.trim())
                .map(s => {
                    const p = { point: s.address.trim() };
                    if (s.text.trim()) p.text = s.text.trim();
                    if (s.icon.trim()) p.icon = s.icon.trim();
                    return p;
                });

            if (stops.length === 1) {
                // Only one filled stop — treat it as a standalone location marker instead
                autoLocations.push(stops[0]);
                hasContent = true;
            } else if (stops.length > 1) {
                parts.push(`route=${encodeURIComponent(JSON.stringify(stops))}`);
                if (route.colors.length > 0 && route.colors[0]) {
                    parts.push(`color=${encodeURIComponent(JSON.stringify(route.colors))}`);
                }
                hasContent = true;
            }
        });

        // Locations (manually added + auto-promoted single-stop routes)
        const manualLocs = locations
            .filter(l => l.address.trim())
            .map(l => {
                const p = { point: l.address.trim() };
                if (l.text.trim()) p.text = l.text.trim();
                if (l.icon.trim()) p.icon = l.icon.trim();
                return p;
            });

        const allLocs = [...manualLocs, ...autoLocations];
        if (allLocs.length > 0) {
            parts.push(`location=${encodeURIComponent(JSON.stringify(allLocs))}`);
            hasContent = true;
        }
        if (!hasContent) return null;

        // Options
        const title = document.getElementById("opt-title").value.trim();
        if (title) parts.push(`title=${encodeURIComponent(title)}`);

        const profile = document.getElementById("opt-profile").value;
        if (profile && profile !== "driving-car") parts.push(`profile=${encodeURIComponent(profile)}`);

        const layer = document.getElementById("opt-layer").value;
        if (layer) parts.push(`layer=${encodeURIComponent(layer)}`);

        const zoom = document.getElementById("opt-zoom").value.trim();
        if (zoom) parts.push(`zoom=${encodeURIComponent(zoom)}`);

        if (document.getElementById("opt-section").checked) parts.push("section=true");
        if (document.getElementById("opt-table").checked)   parts.push("table");

        return MAPROUTER_URL + (parts.length ? "?" + parts.join("&") : "");
    }

    /**
     * Rebuilds the URL display and summary panel.
     * Called on every user interaction.
     */
    function updatePreview() {
        const url    = buildUrl();
        const urlBox = document.getElementById("url-box");
        const summary = document.getElementById("summary");

        if (!url) {
            urlBox.innerHTML = `<span class="url-base">${MAPROUTER_URL}</span><span style="color:#8fa8c0"> — add a route or location to get started —</span>`;
            summary.innerHTML = "Add routes and locations using the form on the left.";
            return;
        }

        // Syntax-highlight the URL in the preview box
        const [base, query] = url.split("?");
        let html = `<span class="url-base">${base}</span>`;
        if (query) {
            html += `<span class="url-sep">?</span>`;
            html += query.split("&").map((part, i) => {
                const [k, v] = part.split("=");
                const sep    = i > 0 ? `<span class="url-sep">&amp;</span>` : "";
                return v !== undefined
                    ? `${sep}<span class="url-key">${k}</span><span class="url-sep">=</span><span class="url-val">${v}</span>`
                    : `${sep}<span class="url-key">${k}</span>`;
            }).join("");
        }
        urlBox.innerHTML = html;

        // Build summary text
        const fullRouteCount  = routes.filter(r => r.stops.filter(s => s.address.trim()).length > 1).length;
        const autoPromoCount  = routes.filter(r => r.stops.filter(s => s.address.trim()).length === 1).length;
        const locationCount   = locations.filter(l => l.address.trim()).length;
        const totalMarkers    = locationCount + autoPromoCount;
        const profile         = document.getElementById("opt-profile").options[document.getElementById("opt-profile").selectedIndex].text;
        const layer           = document.getElementById("opt-layer").options[document.getElementById("opt-layer").selectedIndex].text;

        let summaryParts = [];
        if (fullRouteCount > 0) summaryParts.push(`<strong>${fullRouteCount} route${fullRouteCount > 1 ? "s" : ""}</strong>`);
        if (totalMarkers > 0)   summaryParts.push(`<strong>${totalMarkers} location marker${totalMarkers > 1 ? "s" : ""}</strong>${autoPromoCount > 0 ? ` <em>(${autoPromoCount} auto-promoted)</em>` : ""}`);
        summaryParts.push(`profile: <strong>${profile}</strong>`);
        summaryParts.push(`layer: <strong>${layer}</strong>`);
        if (document.getElementById("opt-section").checked) summaryParts.push("<strong>segment stats</strong> in popups");
        if (document.getElementById("opt-table").checked)   summaryParts.push("<strong>table panel</strong> enabled");

        summary.innerHTML = summaryParts.join(" &middot; ");
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /**
     * Opens the generated URL in a new browser tab.
     */
    function openMap() {
        const url = buildUrl();
        if (!url) { alert("Please add at least one route stop or location marker first."); return; }
        window.open(url, "_blank");
    }

    function openStaticMap() {
        const url = buildUrl();
        if (!url) { alert("Please add at least one route stop or location marker first."); return; }
        window.open(url.replace(/^maprouter\.php/, "staticmap.php"), "_blank");
    }

    /**
     * Copies the generated URL to the clipboard and briefly shows a confirmation.
     */
    function copyUrl() {
        const url = buildUrl();
        if (!url) { alert("Please add at least one route stop or location marker first."); return; }
        const fullUrl = window.location.origin + "/" + url;
        const btn = document.getElementById("copy-btn");

        function markCopied() {
            btn.textContent = "✓ Copied!";
            btn.classList.add("copied");
            setTimeout(() => { btn.textContent = "Copy URL"; btn.classList.remove("copied"); }, 2000);
        }

        function fallbackCopy() {
            const ta = document.createElement("textarea");
            ta.value = fullUrl;
            ta.style.cssText = "position:fixed;opacity:0;top:0;left:0";
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { document.execCommand("copy"); markCopied(); } catch (e) {}
            document.body.removeChild(ta);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(fullUrl).then(markCopied).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }
    }

    // ── URL import ───────────────────────────────────────────────────────────

    function clearImportFeedback() {
        const fb = document.getElementById("import-feedback");
        fb.style.display = "none";
        fb.className = "";
        fb.textContent = "";
    }

    /**
     * Parses a pasted maprouter.php URL and populates the sidebar form.
     * Processes pairs in order to correctly associate each route with its color.
     */
    function importUrl() {
        const raw = document.getElementById("import-url-input").value.trim();
        const fb  = document.getElementById("import-feedback");

        const showFeedback = (msg, type) => {
            fb.textContent  = msg;
            fb.className    = type;
            fb.style.display = "block";
        };

        if (!raw) { showFeedback("Paste a URL first.", "err"); return; }

        const qmark = raw.indexOf("?");
        if (qmark === -1) { showFeedback("No query parameters found in this URL.", "err"); return; }

        const query = raw.slice(qmark + 1);
        const pairs = query.split("&");

        const newRoutes    = [];
        const newLocations = [];
        let   currentRoute = null;
        let   parseErrors  = 0;

        // Reset options to defaults
        document.getElementById("opt-title").value        = "";
        document.getElementById("opt-profile").value      = "driving-car";
        document.getElementById("opt-layer").value        = "";
        document.getElementById("opt-zoom").value         = "";
        document.getElementById("opt-section").checked    = false;
        document.getElementById("opt-table").checked      = false;

        pairs.forEach(pair => {
            const eqIdx = pair.indexOf("=");
            const key   = eqIdx !== -1 ? pair.slice(0, eqIdx) : pair;
            const val   = eqIdx !== -1 ? (() => { try { return decodeURIComponent(pair.slice(eqIdx + 1).replace(/\+/g, '%20')); } catch(e) { parseErrors++; return ""; } })() : "";

            switch (key) {
                case "route":
                    try {
                        const stops = JSON.parse(val);
                        currentRoute = {
                            stops: stops.map(s => ({
                                address: s.point || "",
                                text:    s.text  || "",
                                icon:    s.icon  || "",
                            })),
                            colors: ["#000080"],
                        };
                        newRoutes.push(currentRoute);
                    } catch(e) { parseErrors++; }
                    break;

                case "color":
                    try {
                        const colors = JSON.parse(val);
                        if (currentRoute) currentRoute.colors = colors;
                    } catch(e) { parseErrors++; }
                    break;

                case "location":
                    try {
                        JSON.parse(val).forEach(l => newLocations.push({
                            address: l.point || "",
                            text:    l.text  || "",
                            icon:    l.icon  || "",
                        }));
                    } catch(e) { parseErrors++; }
                    break;

                case "title":
                    document.getElementById("opt-title").value = val;
                    break;
                case "profile":
                    document.getElementById("opt-profile").value = val;
                    break;
                case "layer":
                    document.getElementById("opt-layer").value = val;
                    break;
                case "zoom":
                    document.getElementById("opt-zoom").value = val;
                    break;
                case "section":
                    document.getElementById("opt-section").checked = true;
                    break;
                case "table":
                    document.getElementById("opt-table").checked = true;
                    break;
            }
        });

        if (newRoutes.length === 0 && newLocations.length === 0) {
            showFeedback("No routes or locations found in this URL.", "err");
            return;
        }

        // Apply parsed state
        routes    = newRoutes;
        locations = newLocations;

        document.getElementById("routes-container").innerHTML    = "";
        routes.forEach((_, i) => renderRoute(i));

        refreshLocationsPanel();

        updatePreview();

        const routeWord    = newRoutes.length    === 1 ? "route"    : "routes";
        const locationWord = newLocations.length === 1 ? "location" : "locations";
        const parts = [];
        if (newRoutes.length > 0)    parts.push(`${newRoutes.length} ${routeWord}`);
        if (newLocations.length > 0) parts.push(`${newLocations.length} ${locationWord}`);
        const errorNote = parseErrors > 0 ? ` (${parseErrors} parse error${parseErrors > 1 ? "s" : ""})` : "";
        showFeedback(`Loaded: ${parts.join(" and ")}${errorNote}.`, parseErrors > 0 ? "err" : "ok");
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Escapes a string for safe use in an HTML attribute value.
     *
     * @param {string} str
     * @returns {string}
     */
    function esc(str) {
        return (str || "").replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    // Start with one empty route ready to fill in
    addRoute();
</script>

</body>
</html>
