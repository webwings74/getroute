import json
import webbrowser
from urllib.parse import urlencode

def main():
    base_url = "http://localhost/multiroute.php"  # Pas dit aan naar de juiste URL van multiroute.php
    locations = []

    print("Voer adressen in. Laat leeg en druk op Enter om te stoppen.")
    while True:
        address = input("Adres (of leeg om te stoppen): ").strip()
        if not address:
            break

        text = input("Optionele tekst voor dit adres: ").strip()
        icon = input("Optionele icoon-URL voor dit adres: ").strip()

        location = {"point": address}
        if text:
            location["text"] = text
        if icon:
            location["icon"] = icon

        locations.append(location)

    if not locations:
        print("Geen locaties ingevoerd. Programma wordt afgesloten.")
        return

    # Genereer de URL
    query_params = {
        "location": json.dumps(locations)
    }
    full_url = f"{base_url}?{urlencode(query_params)}"

    print(f"URL gegenereerd: {full_url}")

    # Open de URL in de standaard webbrowser
    webbrowser.open(full_url)

if __name__ == "__main__":
    main()