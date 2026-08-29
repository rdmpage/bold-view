# future-ideas

Parked prototypes and design explorations that are **not wired into the app** —
kept for reference, not loaded or executed by anything. Nothing here is live.

## Contents

- **`work.php`** — a prototype of the "entity per file" pattern (fetch +
  construct + serialise for one entity, class `Work`, explicit `require`, no
  autoloader). Written 2026-07-12 while exploring a relational citation model in
  Postgres. That model was **shelved** on 2026-07-19 (the rich works/people/names
  graph now lives in a triple store; the app only surfaces a thin
  `dataset_citation` table — see `docs/dataset-citations.md`), which orphaned the
  `work` entity specifically.

  Still useful as a **reference for the entity-per-file reorg** if the app's own
  entities (`record` / `bin` / `taxon`) are ever refactored out of the big
  `core.php` / `index.php` files. See the reorg discussion in project memory.
