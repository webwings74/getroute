"""
maprouter-builder.py — Interactive URL builder for Webwings maprouter.php

Guides the user through building a complete maprouter.php URL by prompting
for routes, locations, and optional parameters. Opens the result in the
default web browser.

Libraries used:
    json          - Serialise route/location data to JSON for the query string
    webbrowser    - Open the generated URL in the default browser
    urllib.parse  - URL-encode the query string parameters
"""

import json
import webbrowser
from urllib.parse import urlencode, quote


# ── Configuration ────────────────────────────────────────────────────────────

BASE_URL = "https://mydomain.url/maprouter.php"  # Replace with your domain

# Available routing profiles (OpenRouteService)
PROFILES = [
    "driving-car",
    "cycling-regular",
    "cycling-road",
    "cycling-mountain",
    "cycling-electric",
    "foot-hiking",
    "foot-walking",
    "wheelchair",
]

# Available map tile layers
LAYERS = ["default (OSM)", "topo", "cycle", "transport"]


# ── Helpers ───────────────────────────────────────────────────────────────────

def prompt_choice(prompt, options, default=0):
    """
    Presents a numbered list of options and returns the user's choice.
    Pressing Enter without input selects the default option.

    :param prompt:  Question to display above the list
    :param options: List of option strings
    :param default: Zero-based index of the default option
    :returns:       The selected option string
    """
    print(f"\n{prompt}")
    for i, option in enumerate(options):
        marker = " (default)" if i == default else ""
        print(f"  {i + 1}. {option}{marker}")
    while True:
        raw = input(f"Choose 1–{len(options)} [Enter = {default + 1}]: ").strip()
        if raw == "":
            return options[default]
        if raw.isdigit() and 1 <= int(raw) <= len(options):
            return options[int(raw) - 1]
        print(f"  Please enter a number between 1 and {len(options)}.")


def prompt_yes_no(question, default=False):
    """
    Asks a yes/no question and returns a boolean.

    :param question: Question to display
    :param default:  Default answer when Enter is pressed without input
    :returns:        True for yes, False for no
    """
    hint = "[Y/n]" if default else "[y/N]"
    raw = input(f"{question} {hint}: ").strip().lower()
    if raw == "":
        return default
    return raw in ("y", "yes")


def collect_points(label="point"):
    """
    Prompts the user to enter one or more route/location points interactively.
    Each point can have an optional popup text and optional icon URL.

    :param label: Display name for the type of point being collected
    :returns:     List of point dicts { point, text?, icon? }
    """
    points = []
    print(f"\n  Enter {label}s one by one. Leave the address blank to stop.")
    index = 1
    while True:
        address = input(f"  {label.capitalize()} {index} address (blank to stop): ").strip()
        if not address:
            break
        point = {"point": address}

        text = input(f"  Popup text for '{address}' (optional, blank to skip): ").strip()
        if text:
            point["text"] = text

        icon = input(f"  Icon URL for '{address}' (optional, blank to skip): ").strip()
        if icon:
            point["icon"] = icon

        points.append(point)
        index += 1
    return points


def collect_colors():
    """
    Prompts the user to enter one or more CSS color strings for a route.
    Multiple colors are cycled through per segment.

    :returns: List of color strings, or empty list if none entered
    """
    print("  Enter colors for this route's segments (e.g. navy, orange, #e74c3c).")
    print("  Multiple colors rotate per segment. Leave blank to use the default (navy).")
    colors = []
    while True:
        color = input(f"  Color {len(colors) + 1} (blank to stop): ").strip()
        if not color:
            break
        colors.append(color)
    return colors


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    """
    Main entry point. Guides the user through all options, builds the URL,
    prints it, and opens it in the default web browser.
    """
    print("=" * 60)
    print("  Webwings maprouter-builder")
    print("=" * 60)

    params = []  # List of (key, value) tuples — allows repeated keys for multi-route

    # ── Routes ───────────────────────────────────────────────────────────────
    route_count = 0
    while True:
        if route_count == 0:
            add_route = prompt_yes_no("\nDo you want to add a route?", default=True)
        else:
            add_route = prompt_yes_no(f"\nAdd another route (route {route_count + 1})?", default=False)
        if not add_route:
            break

        print(f"\n── Route {route_count + 1} ──")
        points = collect_points("stop")
        if not points:
            print("  No stops entered for this route — skipping.")
        else:
            params.append(("route", json.dumps(points, ensure_ascii=False)))

            colors = collect_colors()
            if colors:
                params.append(("color", json.dumps(colors)))

            route_count += 1

    # ── Standalone locations ──────────────────────────────────────────────────
    if prompt_yes_no("\nDo you want to add standalone location markers (not part of a route)?", default=False):
        locations = collect_points("location")
        if locations:
            params.append(("location", json.dumps(locations, ensure_ascii=False)))

    # ── Optional parameters ───────────────────────────────────────────────────
    print("\n── Optional parameters ──")

    # Routing profile
    profile = prompt_choice("Routing profile:", PROFILES, default=0)
    if profile != PROFILES[0]:  # Only add if not the default
        params.append(("profile", profile))

    # Tile layer
    layer_choice = prompt_choice("Map tile layer:", LAYERS, default=0)
    if layer_choice != LAYERS[0]:
        params.append(("layer", layer_choice))

    # Section stats in waypoint popups
    if route_count > 0 and prompt_yes_no("Show per-segment distance and time in waypoint popups? (?section)", default=False):
        params.append(("section", "true"))

    # Segment table panel
    if route_count > 0 and prompt_yes_no("Show collapsible segment table panel? (?table)", default=False):
        params.append(("table", ""))

    # Zoom override
    zoom_raw = input("\nForce a specific zoom level? (1–20, blank to skip): ").strip()
    if zoom_raw.isdigit() and 1 <= int(zoom_raw) <= 20:
        params.append(("zoom", zoom_raw))

    # ── Build URL ─────────────────────────────────────────────────────────────
    if not params:
        print("\nNo parameters entered. Opening maprouter.php without parameters.")
        full_url = BASE_URL
    else:
        # Build query string manually to support repeated keys and value-less ?table
        query_parts = []
        for key, value in params:
            if value == "":
                query_parts.append(key)  # e.g. &table with no value
            else:
                query_parts.append(f"{key}={quote(value, safe='')}")
        full_url = BASE_URL + "?" + "&".join(query_parts)

    print("\n" + "=" * 60)
    print("Generated URL:")
    print(f"\n  {full_url}\n")
    print("=" * 60)

    if prompt_yes_no("\nOpen this URL in your browser?", default=True):
        webbrowser.open(full_url)
        print("Browser opened.")
    else:
        print("URL copied above — open it manually in your browser.")


if __name__ == "__main__":
    main()
