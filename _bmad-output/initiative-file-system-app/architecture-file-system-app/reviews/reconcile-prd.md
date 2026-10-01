# Reconcile: PRD + AGENTS.md vs Architecture Spine

- Date: 2026-10-01
- Inputs: `prd-file-system-app/prd-file-system-app.md`, `prd-file-system-app/addendum.md`, `AGENTS.md` (repo root)
- Spine: `architecture-file-system-app.md` (status: draft, binds FR-1…FR-9, NFR-1…NFR-5)

## Verdict

The spine is substantively aligned: every FR/NFR has at least a nominal home, the
heavy decisions (normalized matching, one-table tree, recursive CTE, root
protection, one transaction per write, partial prefix index) all match the PRD
and AGENTS.md, and the brief's "build and run in debug mode" is honored. Five
quiet drops/tensions remain — none fatal, all fixable with one line each.

## FR/NFR coverage audit

| Req | Spine home | Verdict |
| --- | --- | --- |
| FR-1 create folder | AD-1, AD-3, AD-5, AD-6; map row `Service/ItemService` + `DTO/Write/CreateItem` | Covered. Name grammar (trim, ≤255, forbidden chars) is carried only by the blanket "FR/NFR contracts" inherit row — acceptable, but no AD anchors it. |
| FR-2 list contents | AD-6 listing endpoint; inherited "pagination 50/100" | Covered except the ordering rule (see Gap 3). |
| FR-3 create file | Same row/home as FR-1; AD-6 `POST /api/files` | Covered. |
| FR-4 rename | AD-3 (normalized_name maintained on rename), AD-5, AD-4 (root → 400) | Covered; case-only rename works because only `normalized_name` is compared and `name` casing is stored. |
| FR-5 delete file | AD-5, AD-6 `DELETE /api/items/{id}` | Covered. |
| FR-6 cascade delete | AD-2 (recursive CTE), AD-4 (root rejected), AD-5 (atomic, one transaction) | Covered, including depth ≥ 50 atomicity. |
| FR-7 scoped exact search | AD-2, AD-3, AD-6 search endpoint; map row `Repository` + CTE scope | Partially covered — "Files only, Folders are not returned" has no home (Gap 2). |
| FR-8 global exact search | Same as FR-7 | Partially covered — per-result parent path has no home (Gap 4). |
| FR-9 suggestions | AD-7 (top-10, prefix, files-only, `normalized_name, id` order); AD-6 `GET /api/items/{id}` "incl. parent path (FR-9 selection)" | Covered. Blank/whitespace-prefix behavior unstated (minor; inherited PRD rule). Stale-response discarding is frontend-owned per Deferred — fine. |
| NFR-1 scale (100k items; 10k-subtree delete ≤ 10 s, atomic) | AD-2, AD-7; map row "NFR-1/2 performance → indexes + query-count tests" | Partially covered — no measurement plan (Gap 1). |
| NFR-2 suggestion latency (~200 ms p95, worst-case corpus ≥ 50% same first letter) | AD-7 | Partially covered — index serves it, but the corpus/target is not operationalized (Gap 1). |
| NFR-3 deliverability (debug mode, Git repo, README-only run) | AD-8 (compose, one origin), Structural Seed `APP_ENV=dev`; map row "NFR-3 run/deploy" | Covered — debug mode honored (see below). |
| NFR-4 API discipline | Inherited invariants row: AGENTS.md §4 status table, envelope, field-level details | Covered. |
| NFR-5 no auth | Bound in frontmatter; no map row | Acceptable — it is an absence plus a README note (AGENTS.md §10 owns the README); nothing for the spine to house. |

## Contradiction checks

- **Status codes:** spine ↔ PRD agree everywhere (POST 201/400/404/409, PATCH
  200/400, DELETE 204/404, root rename/delete 400). One tension against
  AGENTS.md §4: the table's DELETE row lists only 204/404, while PRD FR-6 and
  spine AD-4 return `400 validation_failed` for root delete. PRD and spine are
  right; AGENTS.md's table just doesn't enumerate it (Gap 5).
- **Pagination numbers:** PRD default 50 / cap 100 = spine's inherited
  "pagination 50/100". Suggestions at ≤ 10 satisfy "no unbounded result set".
- **Matching semantics:** AD-3 mirrors the PRD's Normalized Name exactly —
  case-folded, NFC, drives uniqueness/exact/suggestions/ordering, display casing
  kept in `name`. Nothing matches on `name`. No drift.
- **Root rules:** pre-seeded, `parent_id IS NULL`, partial unique index, rename
  and delete rejected — matches PRD §3, FR-4, FR-6, §9. Creating folders at root
  via omitted `parentId` matches FR-1.
- **Atomicity:** AD-5 (one transaction per write, CTE-collected subtree deleted
  in the same transaction) satisfies FR-6 and NFR-1's atomicity clause.
- **Scale targets:** 100k appears (AD-7); the 10 s / 10k-delete and 200 ms p95
  targets appear nowhere operational (Gap 1).
- **Concurrent duplicate creation:** AD-1's DB-enforced `UNIQUE (parent_id,
  normalized_name)` resolves FR-1's one-201-one-409 consequence. Sound.

## Addendum "Technical pointers" audit

1. Prefix index rather than LIKE scans — **honored**: AD-7 partial btree
   `text_pattern_ops` index serves `LIKE 'prefix%'`.
2. Composite unique on `(parent_id, normalized_name)` — **honored**: AD-1.
3. Recursive deletion strategy with a README trade-off note — **honored for the
   strategy** (AD-2: adjacency list + recursive CTE, explicitly rejecting
   closure table and materialized path); the README trade-off note is implied by
   AGENTS.md §10 but the spine never commits to writing it.
4. Pagination / envelope / status codes inherited, not re-decided — **honored**
   (Inherited Invariants table), except the root-delete-400 extension above.

## "Build and run in debug mode" (brief, NFR-3)

Honored: AD-8 names the FrankenPHP app container "Symfony, debug mode" and the
Structural Seed pins `APP_ENV=dev`. Follow-up for the build ticket: pin
`APP_DEBUG=1` explicitly so "debug mode" is a checked fact, not a label.

## Gaps

1. NFR-1/NFR-2 have no measurement plan — the addendum explicitly delegated the
   "exact measurement plan" to architecture, yet neither the rules nor Deferred
   mention a perf test, seeded corpus (≥ 50% same first letter), 10 s / 10k
   delete check, or 200 ms p95 verification.
2. FR-7/FR-8 "Files only — Folders are not returned" has no home: only AD-7
   (suggestions) filters `type = 'file'`; the search endpoint in AD-6 and AD-3's
   matching rule say nothing about a type filter.
3. FR-2's listing order (folders-first, then Normalized Name, then id) is never
   stated as a rule — it survives only implicitly in the
   `(parent_id, type, normalized_name, id)` index shape, and depends on the
   `type` enum ordering folders before files, which no AD defines.
4. FR-8's per-result parent path is unhoused: the spine attaches parent path
   only to `GET /api/items/{id}` (tagged FR-9 selection), not to search results,
   which is what makes same-named files in different folders distinguishable.
5. Root delete → `400` (PRD FR-6, spine AD-4) quietly extends AGENTS.md §4's
   DELETE row (204/404 only); the spine should note the delta or AGENTS.md §4
   should list 400 for DELETE so the three documents agree.
