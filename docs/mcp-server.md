# Bold View — MCP server design

> **⚠️ RE-SCOPE NEEDED — 2026-07-19.** This design assumed the relational citation
> model in `citation-model.md`, which is now **shelved** in favour of a triple
> store (plus a thin `dataset_citation` table in the app). A curation MCP over the
> barcode data may still happen, but it would query the triple store and/or the v1
> API — not an in-Postgres relational graph. Treat the tool sketches below as
> reusable UX/structure ideas, and re-scope the data layer before building.


Status: **draft / on paper.** Part 3 of three (API → DB structure → **MCP**), and
it depends on the other two: [`api-v1.md`](api-v1.md) (the query surface it wraps)
and [`citation-model.md`](citation-model.md) (the curation graph it draws on).

## Goal

An MCP server as the way people **query and curate** BOLD barcode data from an
agentic chat environment ("Claude Science, but for barcodes"). The emphasis is
**curation**: taking a set of barcodes that are deficient in some way and
suggesting *sources of information* — or actual data — to improve them.

**Suggest-only.** For now the server never writes to the specimen data. It
proposes provisional, provenance-tagged assertions that a human curator accepts
or rejects. It runs over the read-only DB role (`boldview_app`); write-back, if
it ever happens, goes through a separate reviewed channel, never the MCP path,
never auto-applied. (See [db-hardening](db-hardening.md) for the role.)

## The curation loop

Every curation task is one loop in four beats:

**gap → candidates → evidence → decision**

1. **Gap** — select a working set of barcodes and find what's deficient
   (no real species name; no coordinate; no image; …).
2. **Candidates** — find things that could fill the gap (nearest sequences,
   other BIN members, papers about the taxon/BIN, curated external datasets).
3. **Evidence** — return each candidate *with provenance and confidence*, never
   as a bare answer.
4. **Decision** — a human accepts/rejects; accepted facts are recorded as
   provisional assertions, not silently written.

Each beat maps onto a layer already designed: gaps use the API filter grammar;
candidates use pgvector neighbours + the citation graph; evidence uses the
per-edge provenance columns; decisions land in a provisional-assertions table
(structurally the same as the mention→person resolution in the citation model).

## Curation exemplars (kept abstract for now)

The loop is one machine; these are instances of it. **Two or three more candidate
cases are still under consideration, including taxonomy-focused ones — do not
over-fit the design to any single dataset yet.**

- **Missing / placeholder coordinates.** Some barcodes lack lat/lon but share an
  identifier (e.g. a sequence accession) with records in a *curated external
  dataset* that has coordinates. The fill is an **exact join on the shared key**,
  so the suggestion is high-confidence and its provenance is unambiguous
  ("coordinate from external dataset D, record R, matched by key K"). This is the
  natural **walking skeleton**: exact, demonstrable, end-to-end (a barcode gains a
  point and appears on a map).
- **Missing / placeholder taxonomic names.** Some barcodes have only a BIN or a
  `Genus sp.` placeholder rather than a real species name. Candidates come from
  several signals combined: nearest **named** sequence neighbours (pgvector), the
  **consensus taxonomy of the BIN's other members**, **literature** linked to the
  BIN/taxon (citation graph), and **nomenclatural** matches (bionames). This is
  the richer, *evidence-weighted* case — suggestions are ranked, not exact.

The two differ only in how candidates are found and how confident the evidence
is; the surrounding loop and tools are identical.

## Tool design

Layered, following what the prior KG MCP server
(`~/Development/php-mcp-server`) validated in practice (15 task tools + 1 general
query tool):

- **Task tools** — named after the loop, not after tables:
  - `select_records(filter | dataset | bin | taxon | region)` — define the set
  - `find_gaps(set, kind = name | coordinate | image | …)` — the deficient subset
  - `suggest_coordinates(processids)` / `suggest_taxonomy(processids)` —
    candidates + ranked evidence
  - `find_literature(bin | taxon | dataset)` — works via the citation graph
  - `format_citation(work_uri, style)` — reuse `citeproc-php` over stored CSL-JSON
- **One structured general-query tool** — over the v1 API filter interface, for
  the long tail. **Not raw SQL** (injection / runaway-query / schema-leak risk);
  bounded by the API contract.
- **Schema discovery = the OpenAPI spec.** The prior server introspected the
  triple store live (`listTypes`, `listTypeProperties`, …); with a fixed schema
  the agent instead learns available resources/fields from the published
  contract. No live introspection needed.

**The BIN is a first-class curation unit.** The prior server's most useful tools
were BIN-keyed (`binIdentifications`, `binSequenceCitations`,
`binDatasetCitations`); carry that over — a BIN is the natural handle for "what
do we know, and what's cited, about this cluster."

**Output discipline** ("Claude Science" is interactive, not batch): compact,
strictly limited/paginated results; every returned fact tagged with **source +
confidence**; dereferenceable **URIs** on every entity (which the URI-PK identity
model gives for free). Tool *descriptions* carry real weight — the agent selects
by them.

## What the MCP demands of the API and schema

Working backward from the tools — these are the requirements the MCP places on
topics 1 and 2:

**API (topic 1):**
- **Deficiency filters** — the filter grammar must express *absence* and
  *placeholder* (`missing:coordinate`, `missing:species_name`, and a rule for
  "an identification that is really a BIN or `sp.`"). Today's filters are
  positive-only.
- **Batch neighbours** — a set-level form of `GET /records/{id}/related`.
- **Entity → literature** endpoints — `GET /bins/{id}/works`,
  `GET /taxa/{id}/works`, `GET /datasets/{id}/works` (new; depend on topic 2).

**Schema (topic 2):** all three items below are DDL, so they are **owned by the
citation/curation ingestion repo** ([`citation-model.md` §Repository
boundary](citation-model.md#repository-boundary)), not defined in bold-view — the
API and MCP only read them.
- **Computable deficiency** — a view/flags (`has_species_name`,
  `has_coordinate`) including the placeholder-detection rule.
- **External gap-filler sources** — an `external_occurrence`-style table
  (external records joinable to BOLD by a shared key such as an accession),
  indexed on that key; and the **`dataset` node generalised** to cover external
  (e.g. GBIF) datasets via a `source` discriminator. Ingesting such a dataset
  also populates the citation graph (dataset → derived-from → work), so it serves
  both the coordinate and the literature loops.
- **Provisional assertions** — a suggestions/decisions table
  (`source`, `confidence`, `status = proposed|accepted|rejected`, provenance),
  reusing the citation model's assertion machinery. This is where MCP output
  lands; a curator promoting a suggestion is the same act as confirming a
  mention.

## Reuse from the prior server

Template for structure and UX, not a code port (the query layer is entirely
different):
- Shared MCP handler + a **query-builder/formatter pair per tool**; **stdio and
  HTTP** modes over one handler.
- Swap the SPARQL-over-curl execution layer for **v1 API calls**.
- `citeproc-php` (already used) → formatted citations from stored CSL-JSON.
- Realistic size signal: ~2,600 LOC core in the prior server.

## Deferred

- **Write-back** to specimen data (kept out; suggest-only for now).
- **Paper-extracted images / biodiversity data lake** — a data-acquisition
  feeder that later adds image↔work / image↔taxon edges and an image-retrieval
  tool; latent until a use case earns it.
- **Live external federation** (e.g. broad GBIF lookups) — for *small curated*
  external datasets with an exact join key, ingest is preferred over federation
  (it fills the map *and* the provenance graph); live federation stays an option
  for open-ended lookups.

## Status & next steps

Design only — nothing built. The prerequisites are topics 1 and 2; topic 3 is
then substantially a re-skin of the prior server with the query layer swapped.

Repo boundary **decided**: three homes — **v1 API in bold-view**,
**citation/curation schema + ingestion in a new separate repo** (owns the DDL),
**this MCP server in its own repo** (consumes the v1 API over HTTP; independently
deployable, suggest-only). See [`citation-model.md` §Repository
boundary](citation-model.md#repository-boundary).

Anticipated next steps (owner: RP):
- Scaffold the new citation/curation repo and add the new datatypes to the
  database there (it owns the schema).
- Stand up **at least part of the v1 API** so real tools have something to call.
- Firm up 2–3 curation exemplars (incl. taxonomy) before fixing tool signatures.
