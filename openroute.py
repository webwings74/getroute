"""
openroute.py — Route URL generator for Webwings getroute / multiroute

Prompts the user to enter one or more addresses, then constructs a
URL for multiroute.php and opens it in the default web browser.

Libraries used:
    json        - Serialise route point data to JSON for the query string
    webbrowser  - Open the generated URL in the default browser
    urllib.parse - URL-encode the query string parameters
"""

import json
import webbrowser
from urllib.parse import urlencode


def main():
    """
    Main entry point. Collects addresses interactively, builds the route URL,
    and opens it in the browser.
    """
    mydomain = "mydomain.url"                               # Replace with your domain name
    base_url = "https://" + mydomain + "/multiroute.php"   # Full URL to multiroute.php
    locations = []

    print("Enter addresses one by one. Leave blank and press Enter to stop.")
    while True:
        address = input("Address (or leave blank to stop): ").strip()
        if not address:
            break

        text = input("Optional popup text for this address: ").strip()
        icon = input("Optional icon URL for this address: ").strip()

        location = {"point": address}
        if text:
            location["text"] = text
        if icon:
            location["icon"] = icon

        locations.append(location)

    if not locations:
        print("No locations entered. Exiting.")
        return

    # Build the query string and full URL
    query_params = {
        "route": json.dumps(locations)
    }
    full_url = f"{base_url}?{urlencode(query_params)}"

    print(f"Generated URL: {full_url}")

    # Open the URL in the default web browser
    webbrowser.open(full_url)


if __name__ == "__main__":
    main()
