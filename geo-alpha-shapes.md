# Geographic alpha shapes

A small side-feature in bold-view that fits a polygon (loosely, an "alpha
shape") around all the barcode points belonging to an administrative region,
inspired by Flickr's *The Shape of Alpha*:

- Blog post: <https://code.flickr.net/2008/10/30/the-shape-of-alpha/>
- Clustr (the C++ tool that generated the original Flickr shapes, by
  Schuyler Erle / Aaron Straup Cope): <https://github.com/straup/Clustr>
- Web wrapper for Clustr: <https://github.com/straup/ws-clustr>
- Sean Gillies on the topic: <https://sgillies.net/2012/10/13/the-fading-shape-of-alpha.html>

## What it does

Given a country (and optionally a province / region / sector / site within
it), return:

- a polygon containing the BOLD barcode points for that area
- a bounding box of those points

Both are returned as GeoJSON. The web page lets you drill down through the
admin hierarchy (country → province → region → sector → site).

## Algorithm

Flickr's Clustr uses CGAL's true alpha-shape implementation. PostGIS only
gained `ST_AlphaShape` in 3.5, and the database here is on PostGIS 3.4, so
this implementation uses `ST_ConcaveHull(geom, target_percent, allow_holes)`
instead — a different algorithm with a similar visual effect. Lower
`target_percent` → tighter hull. The default is `0.3`.

The bounding box is computed from `ST_XMin / ST_YMin / ST_XMax / ST_YMax`
of the same set of points and emitted as a top-level GeoJSON `bbox`
member (RFC 7946 §5), so spec-aware previewers (e.g. GISLook on macOS)
render only the hull and treat the bbox as metadata.

## API

`api.php` exposes the hull and the children of a node:

```
api.php?hull=<ISO>
        [&province=<...>]
        [&region=<...>]
        [&sector=<...>]
        [&site=<...>]
        [&marker=COI-5P]   marker code, default COI-5P
        [&alpha=0.3]       ST_ConcaveHull target_percent, 0..1
        [&limit=10000]     point cap fed into the hull, 0 = no cap
        [&children=1]      list child names instead of returning the hull
```

Hull response (GeoJSON):

```json
{
  "type": "FeatureCollection",
  "bbox": [west, south, east, north],
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Polygon", "coordinates": [...] },
      "properties": {
        "country": "VN",
        "marker":  "COI-5P",
        "count":   10000,
        "limit":   10000,
        "sampled": true,
        "alpha":   0.3,
        "depth":   0,
        "role":    "hull"
      }
    }
  ]
}
```

Children response (JSON):

```json
{
  "parent_depth": 0,
  "child_level":  "province/state",
  "country":      "VN",
  "marker":       "COI-5P",
  "children":     [{"name": "Bac Kan"}, {"name": "Ba Ria-Vung Tau"}, ...]
}
```

The top-level country listing (no levels specified) is served from a
static file (`geo_countries.json`) — distinct-country counts on the full
~7 M-row dataset take ~50 s, so the list is precomputed.

## Web page

`geo.php` mirrors the API in URL parameters:

- `geo.php` — list of countries (`ISO — name`), no map
- `geo.php?country=VN` — Vietnam hull + bbox + list of provinces
- `geo.php?country=VN&province=Lao+Cai` — drill down further

The map shows the bbox in blue and the hull in light purple with a white
stroke. Each navigation re-fits the map to the bbox.

## Sampling

`ST_ConcaveHull` is the dominant cost; on the full ~590 k US points it
takes ~30 s, on a 10 k sample about 1 s. The default `limit=10000`
caps the inner subquery; pass `limit=0` to use every point. Sampling is
"first N rows the planner returns" — not random, but spatial coverage
is good enough for visualisation.

## Crawling

The hierarchical API is designed so a script can walk the tree and store
GeoJSON for every node — useful for geotagging records or aligning BOLD
localities with OpenStreetMap features:

```
api.php?hull=&children=1                       → every country
api.php?hull=VN                                → country hull
api.php?hull=VN&children=1                     → province names
api.php?hull=VN&province=Lao+Cai               → province hull
... and so on
```

## Files

- `core.php` — `get_geo_hull()`, `get_geo_children()`, `get_geo_countries()`
- `api.php` — `display_geo_hull()`, `display_geo_children()` + dispatch
- `geo.php` — web page
- `geo_countries.json` — precomputed top-level country list
