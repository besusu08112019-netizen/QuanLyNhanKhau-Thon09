# Sprint 19 GIS Google Maps Removal Audit

Date: 2026-07-09

## Audit scope

Checked PHP, JavaScript, CSS, HTML, API routes, config examples, SQL/database files, assets, tests and docs for Google Maps Platform references.

Search terms included:

- google.maps
- maps.googleapis.com
- maps.gstatic.com
- Google Maps
- Google Maps Loader
- Maps JavaScript API
- Places API
- Directions API
- Geocoder
- InfoWindow
- GOOGLE_MAPS_API_KEY
- Google API key patterns
- legacy GIS Google module names

## Findings before cleanup

Production code no longer loaded Google Maps after the previous Leaflet migration, but these leftovers were still present:

| Location | Finding | Action |
| --- | --- | --- |
| google-test.html | Standalone Google Maps test page loading maps.googleapis.com and gm_authFailure | Removed |
| .env.example | GOOGLE_MAPS_API_KEY example config | Removed |
| tests/browser/gis-google.spec.js | Test name still referenced Google module naming | Renamed to gis-leaflet.spec.js |
| assets/js/gis-search.js, assets/js/gis-smart.js | Legacy Leaflet GIS helper modules no longer loaded by the app shell | Removed |
| tools/build-assets.js | Still built legacy GIS helper minified files | Removed build entries |
| assets/js/gis-household-location.js | Legacy gis-smart class/attribute names | Renamed to gis-leaflet naming |

Non-Maps Google references that remain:

- fonts.googleapis.com and fonts.gstatic.com for site typography.
- docs mentioning Google Apps Script migration history. These are historical architecture documents, not Google Maps Platform integration and do not load Maps APIs.

## Final target architecture

Leaflet is the only GIS engine in the active application shell.

The active GIS runtime is organized through `App.gis.manager`:

- map
- markers
- layers
- popup
- gps
- routing
- search
- area

## Verification criteria

Final grep must return no production references to:

- google.maps
- maps.googleapis.com
- gm_authFailure
- GOOGLE_MAPS_API_KEY
- gis-google
- google-test.html

Browser tests must pass on desktop, tablet and mobile.
