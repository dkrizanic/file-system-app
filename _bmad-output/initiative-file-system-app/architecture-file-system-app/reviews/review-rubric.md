# Review — Good-spine checklist (rubric walker)

| | |
| --- | --- |
| **Spine** | `architecture-file-system-app.md` (initiative altitude, build-substrate) |
| **Inputs judged against** | PRD `prd-file-system-app/prd-file-system-app.md` · AGENTS.md (inherited invariants) |
| **Lens** | Good-spine checklist from `bmad-architecture/references/reviewer-gate.md` |
| **Stakes / units** | Production-shaped deliverable; two independently built units: React SPA and Symfony API |
| **Date** | 2026-10-01 |
| **Verdict** | **Strong spine, adopt with fixes.** Seven of eight checklist dimensions pass outright; the one real weakness sits exactly where the two independent units meet — the read side of the API contract (response schemas, root discovery, input normalization) needs one more pass before handoff. |

## Method

- Read the checklist, the spine, the PRD, and AGENTS.md in full.
- Ran the deterministic pass: `lint_spine.py --workspace <doc>` — 5 low findings, all
  false positives (`{id}` / `{name}` route tokens in the AD-6 table are legitimate URL
  templates, verified by hand). No placeholders, no duplicate AD ids, every AD has
  Binds/Prevents/Rule.
- Verified every Stack version against authoritative sources on 2026-10-01 (see
  "Tech currency" below).

## Dimension-by-dimension judgment

### 1. Fixes the real divergence points for the level below — misses none? **PARTIAL**

The three classic SPA↔API divergences are decisively fixed:

- **Routes, methods, payloads, codes** — AD-6's fixed endpoint table.
- **Origin/CORS** — AD-8's one-origin rule (nginx proxy in compose, Vite proxy in dev,
  "no CORS anywhere").
- **Envelope, pagination, ID representation, dates, naming** — inherited §4 + the
  Consistency Conventions table (UUIDs as opaque strings, camelCase JSON).

Two divergence points are missed:

- **Response schemas** — AD-6 pins the write side (paths, methods, codes) and says
  "camelCase payloads" but never says what a listed item, a page envelope, a search
  hit, or a suggestion *contains*. The SPA's `src/types` is specified (AGENTS §3) to
  "mirror the backend's read/write models" — with nothing to mirror. This is the
  bulk of the inter-unit contract. See **Finding 1**.
- **Root bootstrap** — UJ-1 starts at the Root; every endpoint in AD-6 keys off
  `{id}` / `folderId=`; nothing in the fixed surface yields the root's id. The SPA's
  first render has no specified call. See **Finding 2**.

### 2. Every AD's Rule enforceable, and does it prevent its stated divergence? **MOSTLY**

| AD | Enforceable? | Prevents stated divergence? |
| --- | --- | --- |
| AD-1 single `item` table + `UNIQUE(parent_id, normalized_name)` | Yes — one migration, inspection | Yes — DB-owned uniqueness kills the app-side check race (FR-1's concurrency consequence is satisfied by the constraint, not by PHP) |
| AD-2 adjacency + recursive CTE | Yes — query-count assertions per inherited perf principles | Mostly — see tail note: the letter covers *subtree* ops only; an ancestor walk (parent path for `GET /api/items/{id}`) in a PHP loop would be legal under the current wording |
| AD-3 `normalized_name` owns matching | Half — the stored-column half is enforceable; the input half has no named owner | Gap — see **Finding 3** |
| AD-4 protected NULL-parent root | Yes — partial unique index is mechanically enforceable; service guards testable | Yes — multi-root is structurally impossible |
| AD-5 one transaction per write | Yes — functional tests + no-partial-commit assertions | Yes |
| AD-6 fixed surface | Yes — functional tests assert routes and codes | Yes for routes/codes; read-side gap is Finding 1 |
| AD-7 partial prefix index | Yes — migration + `EXPLAIN` in a test | Yes — with an index-detail nit (**Finding 5**) |
| AD-8 one origin, three services | Yes — compose file + README run | Yes |

### 3. Nothing under Deferred could let units diverge? **PASS**

All five entries are genuinely scaffold-local or unit-owned (migration naming, CI
steps, opcache tuning, Redis absence per D2, SPA component breakdown). None of them
can make the SPA and the API diverge. Note: the read-side schema gap (Finding 1) is
*silent*, not deferred — a silent contract dimension is worse than a deferred one,
which is why it is a finding rather than a pass.

### 4. Named tech verified-current? **PASS**

Verified against authoritative sources on 2026-10-01:

| Spine says | Verified | Source |
| --- | --- | --- |
| PHP 8.5 | Current — 8.5.10 released 2026-08-27; newest stable branch | php.net/releases |
| Symfony 8.1 | Current stable minor (8.1.8, May 2026; requires PHP ≥ 8.4 — satisfied by 8.5) | symfony.com/roadmap |
| Doctrine ORM 3.7 | Current (3.7.2 marked Latest) | github.com/doctrine/orm/releases |
| PHPUnit 13.3 | Current major (13 released 2026-02-06) | phpunit.de |
| PostgreSQL 18 | Correct — 18 is current; PG 19 is at Beta 4 (2026-09-24), so pinning 18 honors the no-RC/beta rule | postgresql.org/docs |
| Node.js 24 | Current Active LTS line | nodejs.org schedule |
| React 19.3 | Current (19.3.0) | registry.npmjs.org/react/latest |
| Vite 8 | Current major (8.3.2) — minor unpinned in the spine | registry.npmjs.org/vite/latest |
| FrankenPHP / nginx "current stable" | **Unpinned** — see **Finding 4** | — |

### 5. Ratifies rather than contradicts the brownfield codebase? **PASS**

Greenfield repo. The only structure that exists — `backend/` + `frontend/` folders and
AGENTS.md conventions — is ratified by the Structural Seed, not contradicted.

### 6. Spec capabilities covered? **PASS**

`binds:` lists all of FR-1…FR-9 and NFR-1…NFR-5; the Capability → Architecture Map
places every FR. The PRD's global-suggestions assumption (§4.5) is consistently
encoded — the suggestions endpoint takes no folder scope. NFR-4 is governed by the
inherited §4 envelope plus AD-6; NFR-5 (no auth) is absence by design. Cosmetic: those
two NFRs have no map rows. SM-1 (reviewer running app in 15 min) is carried by AD-8.

### 7. No new AD weakens or contradicts an inherited invariant? **PASS**

Every AD is additive and sits inside AGENTS.md's rails: CTEs live behind repository
interfaces (§3 layering untouched); suggestions ≤ 10 keeps every collection bounded
(§4); compose as the single entry point matches §9; stack floors respected (§3 —
PHP 8.3 floor, PG 16+). One deliberate, sanctioned deviation: root delete/rename
returns `400 validation_failed` (AD-4) where §4's DELETE row lists only `404` — this
follows PRD FR-4/FR-6 explicitly and stays inside the fixed envelope vocabulary; worth
a half-sentence in the spine so a reviewer of AGENTS.md doesn't read it as drift.

### 8. Every owned dimension decided / deferred / open — especially the operational envelope? **MOSTLY**

The dimension a domain-focused draft usually skips is here, and decided: deployment &
environments (AD-8: compose runtime, dev proxy, same pinned versions), config
(conventions row: `.env` / compose env / `.env.example`), operations-lite (logging
convention, debug mode, no-leak 500s), infra/provider (GitHub repo D4, GH Actions §8),
security (auth descoped, NFR-5). Data model, API contract-write-side,
and performance are decided by AD-1…AD-7.

The gap is inside the API-contract dimension: its **read side** is neither decided,
deferred, nor an open question — it is silent (Findings 1 and 2, plus the
ancestor-path tail note).

## Findings

### 1. HIGH — AD-6 pins routes and codes but not the response schemas — the read side of the inter-unit contract is unspecified (AD-6)

AD-6's table fixes method, path, and success status per endpoint, and inherits the
error envelope. Nothing fixes what success *bodies* contain: the fields of a listed
item (id, name, type, parentId? …), the page envelope's key names (`items` vs data,
alongside the inherited `total/limit/offset` metadata), the shape of a search hit's
required parent path (FR-8: "each result includes its parent path"), or a suggestion
entry. The parent-path representation is a real architecture decision, not detail: a
plain string ("Root/Projects/2026") renders a label but cannot navigate, while an
id-bearing ancestor array lets the SPA build a clickable breadcrumb and "locate" the
file per FR-9's selection consequence — and it determines whether building it is an
AD-2 SQL concern or a PHP walk. This is precisely the surface where two independently
built units diverge.

**Fix:** extend AD-6 with a compact response-shape table (read models per endpoint:
item, page envelope, search hit incl. parent path, suggestion) — field lists suffice;
or bind named `DTO/Read` classes with their fields. One extra row deciding
parent-path-as-id-bearing-array closes both this and the AD-2 tail note.

### 2. MEDIUM — No contract for discovering the Root (AD-6, AD-4)

AD-4 pre-seeds exactly one NULL-parent root. Every read in AD-6 is keyed by `{id}` or
`folderId=`. Nothing in the fixed surface returns the root's id — no `GET /api/root`,
no reserved id or slug convention, no bootstrap payload. UJ-1 ("Dario opens the app at
the Root") and FR-7's folder-scoped search both need it. The SPA's initial screen has
no specified call, so the SPA unit must invent one and hope the API unit invented the
same.

**Fix:** one line in AD-6 — e.g. a reserved root slug (`GET /api/folders/root/items`,
`folderId=root` accepted by search), or a fixed well-known root UUID stated in the
Consistency Conventions.

### 3. MEDIUM — AD-3 names no single owner for normalization; write path and search path can normalize differently (AD-3)

The Rule binds the stored column ("maintained on create and rename… nothing ever
matches on `name`") but is silent on *where the Search String is normalized*. If PHP
case-folds + NFC-normalizes names on write while a repository applies SQL `lower()`
to the search/suggestion input, edge inputs (German ß, Turkish İ, an NFD-composed
"café") fold differently on the two paths, and the same query matches via one
endpoint but not another — a reborn variant of exactly the "mixed case rules between
endpoints" divergence AD-3 claims to prevent.

**Fix:** one sentence in AD-3's Rule: a single normalizer in PHP (case-fold + NFC,
one function, unit-tested) is applied to stored names *and* to every search and
suggestion input; repositories only ever compare pre-normalized values.

### 4. LOW — Stack table carries two unpinned rows (Stack)

FrankenPHP and nginx are "current stable" — prose the lint cannot catch and AGENTS §3
("pin them: composer.json, package.json, Dockerfile, compose") says otherwise; Vite is
pinned to major only (8, currently 8.3.x). Everything else in the table is pinned.

**Fix:** pin major.minor for FrankenPHP and nginx (or state explicitly that the
symfony-docker starter's pin is adopted verbatim at scaffold time), and consider
pinning Vite to 8.3.

### 5. LOW — Seed indexes don't quite serve the orderings they are claimed to serve (AD-7, Structural Seed)

Two index/ordering mismatches, both one-token fixes:

- The listing index `(parent_id, type, normalized_name, id)` serves `type ASC`, but
  'file' sorts *before* 'folder' — FR-2's folders-first order needs `type DESC` (a
  backward btree scan reverses all keys, so ASC doesn't cover it).
- AD-7's partial index on `(normalized_name text_pattern_ops)` serves the `LIKE
  'prefix%'` filter but not the full `ORDER BY normalized_name, id` unless `id` is
  appended to the index columns.

**Fix:** `INDEX (parent_id, type DESC, normalized_name, id)` and
`(normalized_name text_pattern_ops, id) WHERE type = 'file'` — or mark both
explicitly as scaffold-time detail so the seed doesn't read as a verified claim.

## Tail notes (informational, no action demanded)

- **AD-2 letter vs ancestor paths:** "Application code never walks the tree in a
  loop" is written for subtree operations; a PHP parent-pointer loop building the
  parent path of `GET /api/items/{id}` would be legal under the current wording and
  N+1-shaped. Half a sentence ("ancestor paths are also one SQL query") — or
  Finding 1's parent-path decision — closes it.
- **AD-6 wording:** "null/omitted `parentId` = root" can be misread as "creates a
  root"; AD-4 disambiguates, but "the parent defaults to the Root" says it without
  the cross-reference.
- **AGENTS §4 vs root delete:** §4's DELETE row lists only `404`; AD-4/PRD return
  `400 validation_failed` for the root. Sanctioned and inside the fixed vocabulary —
  note it in the spine so it doesn't read as convention drift.
- **Capability Map:** NFR-4 and NFR-5 have no rows (governed by inherited §4 + AD-6,
  and by absence, respectively). Cosmetic.
- **Lint:** all five `lint_spine.py` findings are false-positive route tokens;
  verified and dismissed.

## Checklist scorecard

| # | Dimension | Verdict |
| --- | --- | --- |
| 1 | Fixes real divergence points, misses none | **Partial** — Findings 1, 2 |
| 2 | Every AD Rule enforceable + prevents its divergence | **Mostly** — Finding 3; AD-2 tail note |
| 3 | Deferred cannot let units diverge | **Pass** |
| 4 | Tech verified-current | **Pass** — Finding 4 nit |
| 5 | Ratifies brownfield | **Pass** (greenfield) |
| 6 | Spec capabilities covered | **Pass** |
| 7 | No AD weakens inherited invariants | **Pass** |
| 8 | Owned dimensions decided/deferred/open (incl. operational envelope) | **Mostly** — read-side API contract silent (Findings 1, 2) |
