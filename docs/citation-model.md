# Bold View — citation & curation data model

> **⚠️ SUPERSEDED — 2026-07-19.** This relational, in-Postgres citation/curation
> model is **shelved**. Decision: the rich graph of works / people / taxon-names /
> relationships belongs in a **triple store** (reusing prior triple-store + BOLD-
> links work), NOT in this app's Postgres. bold-view instead gets ONE thin,
> read-only table — `dataset_citation` (works that cite datasets, loaded from
> `bold-dataset-citations/results/citations.csv`) — surfaced in the recordset
> view. The whole node/edge/identity model below is kept for design history only.
> See `docs/dataset-citations.md` for what was actually built.


Status: **draft / on paper.** Design agreed in discussion; not yet built.
Companion to [`api-v1.md`](api-v1.md) (the API that will expose this) and part 2
of three topics: API → **database structure** → MCP.

## Goal & scope

Add the citation / provenance data (papers, people, taxonomic names, and their
links to BOLD datasets and specimens) to the **existing Postgres database** as
relational tables — the raw material for *curating* the specimen data (finding
type material, verifying identifications, tracing who collected/identified what).

**In scope:** works, people, taxonomic names, datasets, and the edges between
them and existing specimen data.
**Deferred (metascience, not specimen curation):** institutions, funders — see
[Latent entities](#latent-entities-deferred).

## Core decision: relational, not a triple store

Store this graph as relational tables in the same Postgres instance, **not** in a
separate RDF triple store. Rationale:

- The predicate set is small and stable; RDF's open-world advantage isn't needed.
- Every useful query joins this data to the existing relational / PostGIS /
  pgvector data — a separate store means federating every curation query.
- **Provenance/confidence per edge** is the whole point of curation, and that is
  relational's strength and a naïve triple store's weakness (reification).
- One datastore = one thing to secure (cf. the BSI hardening work) and **one
  query surface for MCP** instead of SQL + SPARQL.

RDF stays available as an **output serialization** at the edge (JSON-LD / RDF
from the v1 API), same contract-vs-presentational split as the API doc. Storage
is relational; publishing linked data is a representation, not the model.

> The existing `boldannotation` table (subject/predicate/object) is an EAV /
> triple pattern. Keep it for the *sparse, unpredictable, rarely-queried* long
> tail only. Do **not** model citations as generic triples there — they get
> first-class tables below.

## Organizing principle: three layers

Every table is a **node**, an **edge**, or **identity**. New relationship → an
edge; new kind of thing → a node; "is X the same as Y?" → identity. Nothing
falls outside these three, which is what keeps ~a dozen tables from being sprawl.

## The identity pattern (one problem, solved once)

`work`, `person`, and `taxon_name` are the *same* identity problem. All share:

| | canonical PK | authority id (often absent) | raw strings | resolution link |
|---|---|---|---|---|
| **work** | DOI-URI, else minted `…/work/{id}` | DOI | CSL-JSON / raw JSON | → work_relation |
| **person** | ORCID-URI, else minted `…/person/{id}` | ORCID | mentions / names | → mention clustering |
| **taxon_name** | bionames-URI, else minted `…/name/{id}` | bionames id | name string | → boldtaxonomy concept |

**Rules that fall out of this pattern:**
- **PK is a canonical URI.** Use the authority URI (DOI / ORCID / bionames) when
  present; otherwise **mint a stable local URI** in the `boldview.iphylo.org`
  namespace. The minted URI dereferences through the v1 API — so every node has a
  real, resolvable URI (the linked-data payoff) without a triple store.
- **The authority id is a unique attribute, never the PK** (except a DOI, which
  *is* the canonical URI when present, being top of its hierarchy and never
  superseded). This keeps rows mergeable and avoids re-keying when an id arrives
  later.
- **Locators are never identity.** URLs and PDF links say *where a copy lives*,
  not *what a thing is* — they live in the identity layer as typed locators, and
  a thing may have many.

## Node tables

Illustrative column sketches, not final DDL.

### work
```
work
  uri            text primary key   -- DOI-as-URI, else minted local URI
  type           text               -- journal-article | preprint | dataset | …
  container_title text              -- journal / venue (extracted for filtering)
  issued_year    int                -- extracted from CSL for filtering/indexing
  csl            jsonb              -- CSL-JSON: display-oriented projection
  raw            jsonb              -- richest upstream record (Crossref/DataCite/
                                    --   OpenAlex) = archival source of truth
```
- **Normalize DOIs** (lowercase, strip `https://doi.org/`) before they become the
  URI, or you get duplicate works.
- Extract the 2–3 fields you actually *filter* on into real columns / expression
  indexes; keep the blobs as source of truth.
- **Store `raw`, not only `csl`.** CSL-JSON is lossy — it drops funder DOIs,
  award numbers, and ROR affiliation ids. `raw` is what makes the "revisit
  later" promise (institutions, funders) actually true.

### work_identifier (identity layer for works)
```
work_identifier
  work_uri   text references work(uri)
  scheme     text     -- doi | handle | url | pdf | pmid | …
  value      text
  kind       text     -- 'identifier' (doi, handle) | 'locator' (url, pdf)
  unique (scheme, value)      -- same DOI can't attach to two works
```

### person
```
person
  uri          text primary key   -- ORCID-as-URI, else minted local URI
  orcid        text unique        -- nullable; fast-path for the common case
  display_name text
```
- ORCIDs are harvested from work `csl`/`raw` author blocks.
- Name variants and per-paper detail live on **mentions** (below), not here.
  A curated-alias table (`person_name`) is optional if you want canonical
  aliases (e.g. from an ORCID record) distinct from as-printed strings.

### person_identifier (identity layer for people)
```
person_identifier                  -- mirrors work_identifier
  person_uri text references person(uri)
  scheme     text     -- orcid | wikidata | viaf | …
  value      text
  unique (scheme, value)
```

### taxon_name
```
taxon_name
  uri              text primary key   -- bionames-URI, else minted local URI
  bionames_id      text unique        -- nullable authority id
  name             text               -- the name string as cited
  taxon_id         text references boldtaxonomy(id)   -- NULLABLE resolution link
```
- **Names ≠ concepts.** `boldtaxonomy` is a taxon *concept* tree (GBIF-based,
  nested set); a `taxon_name` is a nomenclatural object. One concept has many
  names; a name is used across concepts.
- The FK to `boldtaxonomy` is the **churny** part — when a BOLD dump adds/deletes
  names, you re-resolve the *link*, not the name node. bionames anchors the name;
  BOLD anchors the concept.
- bionames often yields the **publishing work's DOI** → create a `work` and a
  `work_taxon` edge of type `establishes` (free provenance).

### dataset
```
dataset
  id     text primary key    -- DS-*  (matches recordset)
  doi    text                -- nullable
  title  text
```
Kept native (joins to specimens via `bold_recordset_code_arr`). A dataset with a
DOI is itself citeable — represented via `work_dataset` edges, not by folding it
into `work`.

### Existing nodes referenced
- **specimen / sequence** — `processid` in `boldmeta` / `boldvector`.
- **boldtaxonomy** — the taxon *concept* tree.

## Edges (all carry provenance)

**Provenance convention** — every edge table carries these four columns:
```
  source     text          -- where the assertion came from
  evidence   text          -- e.g. data-availability stmt | LinkOut | text-mining
  confidence real          -- 0..1
  created    timestamptz default now()
```

| Edge | Between | Notes |
|------|---------|-------|
| **person_mention** | person-instance ↔ (work \| specimen) | the authorship/agent edge; see below |
| **work_relation** | work ↔ work | typed: `cites`, `is-version-of`, `is-preprint-of`, `retracts` … (maps to DataCite/Crossref relation vocab) |
| **work_dataset** | work ↔ dataset | paper cites / documents a DS- dataset |
| **work_taxon** | work ↔ taxon_name | typed: `establishes` (original description) \| `mentions` |

`work_relation` generalizes plain citation so preprint↔version and
retraction links use the same structure as `cites`.

### Known ingestion sources for `work_dataset`

Two concrete sources of dataset-citation edges, each recorded with its own
provenance:

- **Zenodo CSV** — RP's compiled dataset-citation data:
  <https://doi.org/10.5281/zenodo.21240015>. A citeable dataset in its own right;
  its DOI *is* the provenance for the edges it contributes. Key part of the
  data-citation story.
- **DataCite `citations` relation on BOLD DS- DOIs** (prefix `10.5883/*`). Each
  BOLD dataset has a DataCite DOI whose record may list citing works, e.g.
  `GET https://api.datacite.org/dois/10.5883/ds-impmix2` →
  `citations.data[] = [{id: "10.1101/2022.05.03.490388", type: "dois"}, …]`.
  DataCite records also carry creators / titles / related identifiers, so
  harvesting them enriches the **dataset node** (into `raw`, and creators →
  `person_mention`s) well beyond citations.

**Corroboration is expected.** The same logical `(work, dataset)` edge can be
asserted by several sources (Zenodo, DataCite, later Crossref / text-mining).
Keep the assertions **per-source** — one provenance record per source on the
edge — rather than collapsing to a single unattributed edge. This is exactly why
provenance is per-assertion, and it lets confidence rise as sources agree.

## Person resolution: the mention → person model

The answer to "how granular are people?" — **split the mention from the person.**

```
person_mention
  id             bigserial primary key
  source_kind    text     -- 'author' | 'collector' | 'identifier'
  work_uri       text references work(uri)      -- when source_kind = author
  processid      text                           -- when source_kind = collector/identifier
  raw_name       text     -- name exactly as printed
  given          text
  family         text
  affiliation_raw text    -- per-paper context (authors); NOT a property of person
  position       int      -- order in author list
  -- resolution link (re-pointable, reversible, auditable):
  person_uri     text references person(uri)    -- NULLABLE until resolved
  match_confidence real
  match_method   text     -- 'orcid' | 'name+affiliation' | 'manual' | …
  <provenance columns>
```

- **Ingest is lossless.** Every author block *and* every specimen
  collector/identifier string becomes a `person_mention`. Ingest never makes an
  identity decision, so it never makes a wrong one.
- **Resolution is a separate, deferrable step** that links a mention to a
  canonical `person` with a confidence — an at-ingest heuristic now, a batch pass
  later, improvable without re-ingesting.
- This is where **authors overlap with collectors/identifiers**: all three are
  person-mentions clustering into the *same* shared `person` pool. That overlap
  (a stated goal) falls out for free.
- **Affiliation is a matching *feature*, on the mention** (it's per-paper and
  changes over time). Block candidate persons by name; affiliation similarity
  (+ shared co-authors, + ORCID) *raises* confidence to reuse an existing person.
- **Bias toward splitting.** Absent/mismatched affiliation ⇒ mint a new person,
  don't merge. Over-splitting is cheap and reversible (re-point mention links);
  over-merging two real people is corrupting. Merge is the reversible direction.
- Every merge/split is re-pointing links — **no re-keying, fully auditable**
  (the evidence sits on the mention). An optional history/audit table can record
  link changes if a full trail is wanted.

> Conceptually there are separate "authorship" and "specimen-agent" edges;
> mechanically both are `person_mention` rows, which is what makes the shared-
> agent identity work.

## <a name="latent-entities-deferred"></a>Latent entities (deferred)

Institutions (**ROR**) and funders (many with **DOIs**) are metascience, not
specimen curation. **Deferred, not forgotten:**

- They fit the *same identity pattern* (canonical URI PK, ROR/funder-DOI as
  authority id) — so promoting them later is the template a 5th/6th time, at
  ~zero structural cost.
- They stay **latent in `work.raw`** until needed. Promote a latent entity to a
  first-class node when you need to **(a)** query/aggregate across it, **(b)**
  attach curation edges to it, or **(c)** resolve/dedupe it.
- Affiliations follow suit: the `affiliation_raw` string on a mention is the raw
  material that can later resolve to a ROR id (same pattern) — build when a use
  case earns it.

## Repository boundary

This model lives in a **new, separate repo** (mirroring the existing BOLD→Postgres
loader): it is ETL — run offline, on a different cadence than the website, with
different dependencies (CSL/DataCite harvest, dedup, name-matching). This is where
the "keep major tasks in their own repo" instinct pays off, because it genuinely
does not deploy with the site.

**That repo owns the schema.** All DDL, migrations, FKs, and indexes for the
citation/curation tables *and* the derived objects the MCP will need (deficiency
views, `external_occurrence`, the provisional-assertions table — see
[`mcp-server.md`](mcp-server.md)) live here and nowhere else. It is the single
source of truth for "how do I recreate these tables."

**bold-view only reads them.** The v1 API (which stays in bold-view) dereferences
node URIs and serves entity→literature endpoints by *querying* these tables; it
never defines them. This is the one discipline multi-repo must get right — with
three repos touching the same Postgres, the schema needs exactly one owner or it
drifts. The owner is this repo.

(The three homes overall: **API → bold-view**, **citation/curation schema +
ingestion → this new repo**, **MCP server → its own repo**.)

## Relationship to the rest of the schema

- **v1 API**: node URIs dereference through it (`GET /work/{id}`, etc.), and the
  API is where RDF/JSON-LD serialization would live if wanted.
- **MCP** (topic 3): builds on this single SQL surface — no SPARQL federation.
- **`boldvector` / `boldmeta` split**: intentionally **out of scope here** —
  deferred to the next BOLD data-dump refresh (when values will also be cleaned).
  The suboptimal pgvector index (`ivfflat`, `probes=1`; HNSW likely better) is a
  separate task again.

## Status

Design only — nothing built. Repo boundary **decided** (separate ingestion repo,
owns the schema — see [Repository boundary](#repository-boundary)). Next:
(a) scaffold the new repo, (b) turn these sketches into migrations + FKs/indexes
there, and (c) design ingestion (CSL/raw harvest → works, mention extraction →
resolution).
