# Webwings Routekaarten

Dit project is een webapplicatie waarmee routes en locaties op een interactieve kaart kunnen worden weergegeven. Dit kan afzonderlijk, of tegelijkertijd. Het maakt gebruik van de [OpenStreetMap](https://www.openstreetmap.org/)-kaartlaag en integreert verschillende API's om routes en locaties te visualiseren. Voor sommige API's is een API-sleutel vereist met een (gratis) account, zoals o.a. bij [TracesTrack](https://tracestrack.com/), [ThunderForest](https://www.thunderforest.com/) en [OpenRouteService](https://openrouteservice.org/).

## Bestanden in dit project

- **`example-config.js`**: Voorbeeld van de configuratie, je moet dit bestand kopieren naar `config.js` en dan al je eigen API keys invullen.
- **`getroute.php`**: De hoofdtoepassing die de kaart weergeeft en routes en locaties verwerkt.

## Functionaliteiten

### 1. **Kaartweergave**
De kaart wordt weergegeven met behulp van de [Leaflet.js](https://leafletjs.com/) bibliotheek en toont standaard een OpenStreetMap-kaartlaag.

### 2. **Routes**
- Routes worden opgehaald via de OpenRouteService API.
- De gebruiker kan een `route`-parameter in de URL meegeven als een JSON-array. Elk punt in de array bevat:
  - `point`: De locatie (adres of coördinaten).
  - `text`: Optionele tekst die wordt weergegeven in een popup.
  - `icon`: Optionele URL van een icoon dat wordt gebruikt voor de marker.

Hieronder volgt een voorbeeld van een `route`-parameter, met en zonder de optionele parameters text en icon. De text parameter kan een volledige HTML formaat hebben, inclusief `<img src'..'>` tags. Dit kan de URL wel lang maken, dus gebruik dit met mate. De icon parameter kan een URL zijn naar een afbeelding die als icoon wordt weergegeven op de kaart.

```json
[
    {"point": "Amsterdam", "text": "Startpunt", "icon": "https://example.com/start.png"},
    {"point": "Rotterdam", "text": "Eindpunt"},
    {"point": "Utrecht"}
]
```

### 3. **Locaties**
- Locaties worden opgehaald via de Nominatim API.
- De gebruiker kan een `location`-parameter in de URL meegeven als een JSON-array. Elk punt in de array bevat dezelfde eigenschappen als een routepunt.

Voorbeeld van een `location`-parameter:
```json
[
    {"point": "Utrecht", "text": "Bezienswaardigheid"}
]
```

### 4. **Huidige locatie**
Als er geen routes of locaties zijn opgegeven, probeert de applicatie de huidige locatie van de gebruiker te bepalen via de browser's geolocatie-API. Als dit niet lukt, wordt er een fallback naar Amsterdam gebruikt.

### 5. **Totale afstand en tijd**
- De totale afstand en reistijd van de route worden berekend en weergegeven in een overlay (`#duration-container`) op de kaart.
- De aankomsttijd wordt ook berekend op basis van de huidige tijd en de totale reistijd.

### 6. **Beschikbare Iconen**

In de folder `mapicons` zijn een aantal iconen beschikbaar die je kunt gebruiken voor markers op de kaart. Deze iconen kunnen worden opgegeven via de `icon`-parameter in de JSON-array van een route- of locatiepunt. Je kunt met de `.pxd` bestanden eigen iconen maken, met bijvoorbeeld Pixelmator Pro, GIMP of Photoshop.

| Bestandsnaam         | Voorbeeld                              |
|-----------------------|----------------------------------------|
| `campfire.png`        | ![campfire](mapicons/campfire.png)    |
| `campingcar.png`      | ![campingcar](mapicons/campingcar.png)|
| `pause.png`           | ![pause](mapicons/pause.png)          |
| `pin.png`             | ![pin](mapicons/pin.png)              |
| `pinpoint.png`        | ![pinpoint](mapicons/pinpoint.png)    |
| `start.png`           | ![start](mapicons/start.png)          |
| `stop.png`            | ![stop](mapicons/stop.png)            |
| `truckcamper.png`     | ![truckcamper](mapicons/truckcamper.png) |
| `workshop.png`        | ![workshop](mapicons/workshop.png)    |
| `world.png`           | ![world](mapicons/world.png)          |

Je kunt deze iconen gebruiken door de bestandsnaam op te geven in de `icon`-parameter, bijvoorbeeld: `mapicons/start.png`. Als je eigen iconen wilt toevoegen, plaats deze dan in de `mapicons`-folder en geef de bestandsnaam op in de `icon`-parameter.

## Hoe te gebruiken

1. **Installeer een lokale server**
   Het script is PHP, dus de webserver moet dit ondersteunen. Omdat het script externe API's gebruikt, moet het worden uitgevoerd op een server (bijvoorbeeld Apache of een lokale server zoals XAMPP).

2. **Plaats de bestanden**
   Zorg ervoor dat `config.js` en `getroute.php` in dezelfde map staan.

3. **Voeg API-sleutels toe**
   Vul de API-sleutels in `config.js` in met je eigen sleutels. Dit bestand is niet toegankelijk voor de browser, maar moet wel correct zijn ingesteld om de API's te kunnen gebruiken. Hier is een voorbeeld van hoe de inhoud van het bestand eruit zou moeten zien:
   ```js
   const config = {
       thunderforestApiKey: "JOUW_THUNDERFOREST_API_KEY",
       tracestrackApiKey: "JOUW_TRACESTRACK_API_KEY",
       openRouteServiceApiKey: "JOUW_OPENROUTESERVICE_API_KEY"
   };
   ```

4. **Open de applicatie**
   Open `getroute.php` in een browser via de server. Voeg parameters toe aan de URL om routes en locaties te specificeren:
   ```
   http://localhost/getroute.php?route=[...]&location=[...]&layer=[...]&section=[...]&profile=[...]
   ```

### Beschikbare URL-parameters

- **`route`**: Een JSON-array met routepunten. Elk punt bevat:
  - `point`: De locatie (adres of coördinaten).
  - `text`: Optionele tekst die wordt weergegeven in een popup.
  - `icon`: Optionele URL van een icoon dat wordt gebruikt voor de marker.
  - `color`: Optionele array van kleur(en) van de route, `color=["orange","navy"]`  

  Voorbeeld:
  ```json
  [{"point":"Amsterdam","text":"Startpunt"},{"point":"Rotterdam","text":"Eindpunt"}]
  ```

- **`location`**: Een JSON-array met locaties. Elk punt bevat dezelfde eigenschappen als een routepunt.

  Voorbeeld:
  ```json
  [{"point":"Utrecht","text":"Bezienswaardigheid"}]
  ```

### Verdere optionele parameters

- **`layer`**: De kaartlaag die wordt gebruikt. Mogelijke waarden:
  - `osm`: (standaard) OpenStreetMap-kaartlaag.
  - `cycle`: Fietsknooppuntenkaartlaag.
  - `transport`: OpenStreetMap-transportkaartlaag
  - `topo`: Tracestrack topografische kaartlaag (vereist een API-sleutel).

- **`section`**: Een boolean (`true` of `false`) die bepaalt of de bij tussenpunten van de route de tussenafstand en -tijd moet worden weergegeven in de popup-tekst. Standaard is `false`.

- **`profile`**: Het vervoersprofiel dat wordt gebruikt voor routeberekeningen die OpenRouteService kan leveren. Mogelijke waarden:
  - `driving-car` (standaard): Autorijden.
  - `cycling-regular`: Fietsen, of specifieker `cycling-road` voor de weg, `cycling-mountain` voor mountainbiken, `cycling-electric` voor e-bikes.
  - `foot-hiking`: Wandelen, of specifieker `foot-walking` voor wandelen met een specifieke focus op voetpaden, `foot-mountain`: Bergwandelen.
  - `wheelchair`: Toegankelijkheid voor rolstoelen.

- **`zoom`**: De zoomfactor van de kaart, als je deze parameter opgeeft, wordt de zoomfactor geforceerd naar de opgegeven waarde. Standaard is `13`. Mogelijke waarden zijn tussen `1` en `20`. De zoomfactor kan ook worden ingesteld via de kaart zelf.

### Voorbeelden

## Alleen de getroute.php
Als je alleen de getroute.php oproept in een webbrowser, zonder parameters, wordt de kaart weergegeven met de huidige locatie van de gebruiker (indien beschikbaar) of met een fallback naar Amsterdam.
```
http://localhost/getroute.php
```

## Een Route.
Een voorbeeld-URL om een route van Amsterdam naar Rotterdam weer te geven met de topografische kaartlaag en het fietsprofiel:
```
http://localhost/getroute.php?route=[{"point":"Amsterdam","text":"Startpunt"},{"point":"Rotterdam","text":"Eindpunt"}]&layer=topo&profile=cycling-regular
```

## Locatie(s)
Een voorbeeld van een kaart met een locatie:
```
http://localhost/getroute.php?location=[{"point":"Utrecht","text":"Bezienswaardigheid"}]
```

## Een route met een locatie
```
http://localhost/getroute.php?route=[{"point":"Amsterdam","text":"Startpunt"},{"point":"Rotterdam","text":"Eindpunt"}]&location=[{"point":"Utrecht","text":"Bezienswaardigheid"}]
```

## Externe afhankelijkheden

- **Leaflet.js**: Voor kaartweergave.
- **Tracestrack API**: Voor topografische kaartlagen. (API Key vereist)
- **OpenRouteService API**: Voor routeberekeningen. (API Key vereist)
- **Thunderforest API**: Voor extra kaartlagen. (API Key vereist)
- **Nominatim API**: Voor geocodering van locaties.

## Licentie
Dit project is gelicentieerd onder de MIT-licentie:

Copyright © Richard, Webwings 2025 

Hierbij wordt gratis toestemming verleend aan iedereen die een kopie van deze software en bijbehorende documentatiebestanden (de "Software") ontvangt, om zonder beperking in de Software te handelen, inclusief maar niet beperkt tot het gebruiksrecht, het kopieerrecht, het wijzigingsrecht, het samenvoegingsrecht, het publicatierecht, het distributierecht, het sublicentierecht en/of het verkooprecht van kopieën van de Software, onder de volgende voorwaarden:

De bovenstaande copyrightvermelding en deze toestemmingsverklaring moeten worden opgenomen in alle kopieën of substantiële delen van de Software.

DE SOFTWARE WORDT GELEVERD "ZOALS HET IS", ZONDER ENIGE GARANTIE, UITDRUKKELIJK OF IMPLICIET, INCLUSIEF MAAR NIET BEPERKT TOT DE GARANTIES VAN VERKOOPBAARHEID, GESCHIKTHEID VOOR EEN BEPAALD DOEL EN NIET-INBREUK. IN GEEN GEVAL ZULLEN DE AUTEURS OF COPYRIGHTHOUDERS AANSPRAKELIJK ZIJN VOOR ENIGE CLAIM, SCHADE OF ANDERE AANSPRAKELIJKHEID, HETZIJ IN EEN ACTIE VAN CONTRACT, ONRECHTMATIGE DAAD OF ANDERSZINS, VOORTKOMEND UIT, UIT OF IN VERBAND MET DE SOFTWARE OF HET GEBRUIK OF ANDERE HANDELINGEN IN DE SOFTWARE.
