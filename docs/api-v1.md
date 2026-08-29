# Bold View API v1 — design

Status: **draft / on paper**. This locks the contract before any router code is
written. Companion machine-readable spec: [`openapi.yaml`](../openapi.yaml).

## Goals

1. A **standard, documented, tested** HTTP API over the existing read-only data,
   as the stable surface that an MCP server (and any external consumer) can
   depend on.
2. Do it **additively**: the new `/v1/...` routes are the contract; the current
   query-param endpoints in `api.php` and their `format=html` fragments keep
   working untouched so the web UI (`index.php`) does not break.
3. Reuse the existing `get_*` functions in `core.php` unchanged — this is a
   routing + representation + documentation layer, not a data-layer rewrite.

## Core principles

### Representations: contract vs. presentational
Content is negotiated with `?format=` (kept as-is; simple and already wired).

| format | media type | contract? | notes |
|--------|-----------|-----------|-------|
| `json` (default) | `application/json` | **yes** — versioned, schema'd, tested | the canonical representation |
| `geojson` | `application/geo+json` | **yes** | spatial resources (maps, extents, hulls) |
| `fasta` | `text/plain` | **yes** | sequence downloads |
| `html` | `text/html` | **no — presentational** | server-rendered UI fragments; body is opaque and may change at any time |

**Rule:** MCP and external clients consume only contract formats. HTML fragments
stay a first-class output for the web UI (hypermedia / "HTML over the wire"), but
are deliberately outside the stability guarantee — nothing machine-readable may
parse them. In OpenAPI they are documented as `text/html` with an opaque body.

### HTTP semantics
- Real status codes (already handled by `send_doc`): `200`, `400`, `404`, `410`,
  `500`; add `422` for validation errors. Stop relying on the in-body `status`
  as the primary signal (keep it for backward-compat, but the HTTP code is
  authoritative).
- `GET` for all reads; `POST` only for large/complex inputs (a sequence to
  search, a set of ids to build a tree).
- Versioned under `/v1`. A breaking change means `/v2`, never a silent change.

### Consistent envelopes
- **Error**: `{ "status": <int>, "message": "<human readable>" }`.
- **Collection** (paged): `{ "page": <int, 1-based>, "limit": <int>, "count":
  <int, items in this page>, "items": [ ... ] }`. (Current code returns a bare
  `hits`/records array + `status`; v1 standardises the wrapper. Legacy endpoints
  keep their old shape.)
- **Timing**: the informational `took` (seconds) may appear on any response.

### Fields deliberately excluded from the contract
- `embedding` — the raw 1024-dim pgvector. Internal, large, and an
  implementation detail of similarity search. Never serialised in v1.
- The in-body `status` is retained but documented as legacy/secondary.

### Input handling
Every route parameter that reaches SQL is **parameterised** (`pg_query_params`)
as it is migrated — this closes the string-interpolation injection risk in the
touched paths as a side effect of adding v1 + tests.

## Resource model

Nouns already implicit in `api.php`, made explicit as paths. `{id}` is
URL-encoded (processids and BIN URIs contain `:` and other chars).

### Phase 1 — core read resources (this spec)
```
GET  /v1/records/{processid}                     one barcode record
GET  /v1/records/{processid}/images              images for a record
GET  /v1/records/{processid}/related?limit=      nearest sequences (vector kNN)
GET  /v1/bins/{bin_uri}                           one BIN
GET  /v1/bins/{bin_uri}/sequences   (fasta)       raw sequences for a BIN
GET  /v1/taxa/{id}                                taxon by internal id
GET  /v1/taxa?name=&rank=                          taxon by name (+ optional rank)
GET  /v1/recordsets/{id}                          dataset metadata
GET  /v1/recordsets/{id}/records?page=&limit=     barcodes in a dataset
GET  /v1/recordsets/{id}/images?page=&limit=      images in a dataset
GET  /v1/barcodes?filter=&page=&limit=            paged barcode list
GET  /v1/images?filter=&page=&limit=              paged image list
POST /v1/search/sequence                          BLAST-style similarity search
```

### Phase 2 — spatial + derived (documented later, same principles)
```
GET  /v1/records/{processid}/alignment            alignment vs reference
GET  /v1/records/{processid}/map        (geojson) BIN map for a related set
GET  /v1/records/{processid}/tsne                 t-SNE / Vega spec
GET  /v1/bins/{bin_uri}/tsne
GET  /v1/recordsets/{id}/extent         (geojson) spatial extent
GET  /v1/regions/{country}/{province?}/…          admin-region hull / children
POST /v1/trees                                    NJ tree from a set of ids
GET  /v1/taxa/{id}/extent               (geojson)
```

The Phase-2 endpoints mostly emit GeoJSON or visualization payloads (Vega,
Newick, SVG). They follow the same contract/presentational split — GeoJSON is
contract; SVG is presentational like HTML.

## Legacy → v1 mapping (for the eventual frontend migration)

| Legacy | v1 |
|--------|----|
| `api.php?record=X` | `GET /v1/records/X` |
| `api.php?record=X&related&limit=N` | `GET /v1/records/X/related?limit=N` |
| `api.php?record=X&images` | `GET /v1/records/X/images` |
| `api.php?bin=X` | `GET /v1/bins/X` |
| `api.php?bin=X&format=fasta` | `GET /v1/bins/X/sequences` |
| `api.php?taxonname=X&rank=R` | `GET /v1/taxa?name=X&rank=R` |
| `api.php?taxonid=X` | `GET /v1/taxa/X` |
| `api.php?recordset=X&records&page=N` | `GET /v1/recordsets/X/records?page=N` |
| POST `{sequence:…}` | `POST /v1/search/sequence` |

The `tree=1` flag on `related`/`search` is retained as a query parameter
(`?tree=1`) that adds a `newick` field to the JSON, rather than a separate route.

## Testing

- **Contract tests**: hit each Phase-1 route (happy path + a 404) and validate
  the JSON/GeoJSON body against the OpenAPI schemas.
- **Smoke tests** for `format=html`: assert `200` and presence of the expected
  root element only — never assert on inner markup (it is presentational).
- Run against a small fixture dataset so results are deterministic.

## Documentation page (`docs.html`)

The `openapi.yaml` doubles as the source for a live, browsable docs page — no
hand-written HTML docs to maintain.

- Ship a single static `docs.html` at `/docs` that loads a generic renderer and
  points it at `/openapi.yaml`. The page fetches the spec at load time, so docs
  regenerate automatically whenever the spec changes (same source of truth that
  drives the contract tests).
- **Renderer:** Redoc (clean, read-only reference) as the default. If we want
  live "Try it out" request forms, use Swagger UI or Scalar instead — decide at
  build time; the spec is identical either way.
- **Vendoring:** prefer serving the renderer's JS bundle locally rather than
  from a CDN, so the docs page has no third-party runtime dependency.
- **"Try it out" / CORS:** docs page and API are same-origin
  (`boldview.iphylo.org`), so live requests work without CORS config. If a
  renderer or consumer is ever cross-origin, add `Access-Control-Allow-Origin`
  (safe for a read-only GET API).

## Out of scope for v1
Auth, rate limiting, write operations. The API is read-only, matching the
`boldview_app` DB role. If MCP later needs throttling, it is added at the edge,
not baked into the contract.
