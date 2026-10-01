---
name: Adversarial review — File System App spine
type: architecture-review
status: final
created: 2026-10-01
reviewer: adversarial-architecture-reviewer
subject: architecture-file-system-app.md (draft, 2026-09-30)
inputs: prd-file-system-app.md, AGENTS.md
---

# Adversarial Review — Architecture Spine

## Method

Build two teams one level down that each obey every AD (AD-1…AD-8), every
inherited invariant, and AGENTS.md §4 to the letter, then let them integrate:

- **Team SPA** — React/TypeScript, builds strictly from AD-6's route table,
  the camelCase rule, the error envelope, and the PRD's testable consequences.
- **Team API** — Symfony, builds strictly from the same, plus AD-1…AD-5,
  AD-7, AD-8 and the Consistency Conventions.

Any place where both teams are fully compliant yet produce incompatible code
is a hole in the spine. (The same lens is applied inside the backend with two
developers each owning a subset of endpoints.)

## Headline

The spine pins routes, status codes, and the error envelope — and almost
nothing about success bodies. AD-6 says "camelCase payloads, envelope and
codes per AGENTS.md §4" and stops; AGENTS.md §4 fully specifies only the
*error* envelope and the *names* of pagination fields. The SPA consumes JSON
shapes, not routes, so the entire read-model contract is unwritten. On top of
that, the SPA cannot even learn the Root's id from any pinned surface, and
two backend devs can each implement "case-folded, NFC" differently and still
both obey AD-3.

---

## F-1 — The read-model / success-body contract is pinned nowhere

**Severity: High** — breaks integration on every screen.

**The two compliant-but-incompatible constructions:**

- Team API ships listings as `{"items": [{"id","name","type"}], "total", "limit", "offset"}`,
  returns `201` with an empty body plus a `Location` header, and answers
  `PATCH` with `{"id","name","type"}`.
- Team SPA declares in `src/types`: `Page<T> = { data: T[]; pagination: { total, limit, offset } }`,
  expects `POST`/`PATCH` to return the full item detail (`parentId`,
  `parentPath`, timestamps) so it can update local state without a re-fetch,
  and expects listing rows to carry `parentId` for back-navigation.

Both sides satisfy AD-1…AD-8 and AGENTS.md §4 exactly: §4 fixes methods,
codes, the error envelope and the *field names* `total`/`limit`/`offset` —
not their container, not the row shape, not whether POST/PATCH have bodies,
not what `Location` points to. The PRD names the array key `items` and the
metadata fields, but says nothing about nesting (`total` top-level vs a
`pagination` object), and nothing at all about item fields.

**The AD gap:** AD-6 fixes the route table only; no AD fixes any response
body. "camelCase payloads" constrains naming, not shape.

**Suggested fix — new AD-9 "Read-model & page contract":**

- Page envelope everywhere (listing, search): flat
  `{"items": [...], "total": int, "limit": int, "offset": int}`.
- `ItemSummary` (listing rows): `{ id, name, type }` with `type` exactly
  `"folder" | "file"` (string enum in JSON, values from AD-1).
- `ItemDetail` (body of `GET /api/items/{id}`, `POST /api/folders`,
  `POST /api/files`, `PATCH /api/items/{id}`): `ItemSummary` plus
  `parentId: string | null` and `parentPath` (shape per F-3).
- `Location` on 201 is always `/api/items/{id}`.
- `SearchHit` and `Suggestion` shapes pinned (F-5).
- Entities never gain fields "because they're there" — the payload is exactly
  what the SPA renders.

## F-2 — Root discovery: the SPA cannot learn the Root's id from the pinned surface

**Severity: High** — first screen cannot be rendered; silent gap because the
API team's own tests never paint a first screen.

**The two compliant-but-incompatible constructions:**

- Team API seeds the Root in a migration with a fresh UUIDv4 generated at
  seed time (the Consistency Conventions say "IDs: UUIDs"); the id differs
  per environment and is documented in the README as "look in the database".
- Team SPA must call `GET /api/folders/{id}/items` (AD-6) with *some* id to
  show anything. Guessing: it hardcodes a fixed constant
  (`00000000-0000-0000-0000-000000000000`), or calls `GET /api/items/root`,
  or passes a magic `"root"`/`null` — none of which any AD defines.

Note the asymmetry that hides this: `POST /api/folders` with omitted
`parentId` creates at the Root *without knowing its id*, so the API team's
functional tests — create, list, search, all by id — never trip over it. The
SPA hits it on the very first request.

**The AD gap:** AD-6 has no bootstrap/entry endpoint and no reserved root
identifier; AD-4 pins that the Root exists and is protected, not how a
client addresses it. The seed-strategy deferral ("scaffold-time detail")
defers the one fact the SPA needs on day one.

**Suggested fix — new AD-10 "Root addressing":** pick one and pin it:

1. (Recommended) A fixed, well-known Root UUID
   (`00000000-0000-0000-0000-000000000000`), seeded by the migration,
   identical in every environment, documented in the README and in AD-10;
   `GET /api/items/{rootId}` returns a normal `ItemDetail` with
   `parentId: null`, `parentPath: []`, so breadcrumbs and root-protection in
   the UI (compare against the constant) work with no extra surface.
2. Alternatively `GET /api/root` returning `ItemDetail` — more conventional
   REST, one more route in AD-6.

Also pin who seeds the Root (migration, exactly once) so a second seeder —
fixtures, a bootstrap command — collides with the AD-4 partial unique index
loudly instead of silently creating a second NULL-parent row in some
environments.

## F-3 — `parentPath` shape is undefined, and ancestor traversal is owned by no AD

**Severity: High** — search results and item detail are the two features the
PRD explicitly builds around "parent path" (FR-8, FR-9 selection).

**The two compliant-but-incompatible constructions:**

- Team API implements "incl. parent path" as a string:
  `"parentPath": "Projects/2026"` — names joined with `/`, cheap
  `string_agg` in the same query, ids discarded.
- Team SPA types it as `parents: { id, name }[]` — it needs ancestor *ids*
  because clickable breadcrumbs are the only navigation primitive besides
  the listing, and the PRD's own UJ-2 is "pick a suggestion and the file is
  located" — locating means navigating to the parent folder.

Both are "the item, including its parent path". The string version silently
removes the ability to navigate; the array version breaks a SPA built for
strings. Sub-ambiguities either way: does the path include the Root's name?
The item itself? Leading separator? (Names cannot contain `/` per FR-1, so
the separator is at least safe.)

**The AD gap:** AD-6 mentions "parent path" without a shape; no other AD
mentions it. Worse, AD-2 assigns recursion to SQL only for *subtree*
operations (cascade delete, scoped search) — computing an ancestor chain is
neither, so two backend devs split: one writes an upward recursive CTE, the
other loops parent-by-parent in PHP. The loop is a per-level N+1 that the
inherited performance principles discourage but no AD forbids for ancestor
walks.

**Suggested fix:** tighten AD-6 (shape) and AD-2 (mechanism): `parentPath`
is an array of `{ id, name }` ordered from the Root's first child down to
the direct parent — excluding the Root itself and the item itself — computed
by one upward recursive CTE in the repository. AD-2's rule becomes "SQL owns
recursion, both down the tree and up it."

## F-4 — The name-normalization algorithm has three potential owners

**Severity: Medium** — correctness bug at the edges of Unicode, invisible to
ASCII test data.

**The two (three) compliant-but-incompatible constructions:**

- Dev A owns create (`POST /api/folders`, `POST /api/files`) and normalizes
  with NFC + `mb_strtolower`.
- Dev B owns rename (`PATCH`) and search input normalization with Symfony's
  `UnicodeString::fold()` (full case folding: `ß`→`ss`, different Turkish
  `İ` handling).
- The database's unique index compares stored `normalized_name` bytes —
  there is no pinned statement that the DB never re-normalizes (no
  `lower()` functional index, no collation reliance).

All obey AD-3's literal words: "case-folded, NFC … maintained on create and
rename." Result: `Straße` vs `STRASSE` conflicts on create but not on rename
(or vice versa), and exact search misses items created by the other path.
Nobody deviates from the AD; the AD pinned the intent, not the algorithm or
its single owner.

**The AD gap:** AD-3 names the transform, not the implementation, its
owner, or whether PostgreSQL participates.

**Suggested fix:** tighten AD-3: one final `NameNormalizer` class is the
only code that normalizes (create, rename, search input, suggestion input);
pick and name the exact algorithm (boring choice: NFC then `mb_strtolower`;
document the `ß`/full-folding trade-off in the README); the database only
ever compares stored bytes — no `lower()`, no collation-dependent matching;
unit tests pin the tricky cases (`ß`, `İ`, full-width, combining marks).

## F-5 — Suggestions: response envelope and blank-prefix behavior are unpinned

**Severity: Medium** — the most-called endpoint in the app, and the SPA's
error-surfacing rules make the wrong guess visible to the user.

**The two compliant-but-incompatible constructions:**

- Team API: `GET /api/suggestions?prefix=` answers `200` with a bare JSON
  array of names `["invoices-q1", …]`, and returns `400 validation_failed`
  for a blank/whitespace/missing prefix (mirroring search's blank rule from
  FR-7).
- Team SPA: expects `{"items": […]}` like every other collection (AGENTS.md
  §4: "Collection endpoints are paginated … include pagination metadata" —
  do suggestions obey that or not? AD-7 says "never aggregates anything
  else", implying no `total`, but that is the *only* clue), and per AGENTS.md
  frontend rules it surfaces 400s with meaningful messages — so clearing the
  search box pops an error toast.

Both satisfy AD-7 ("`LIKE 'prefix%'`, `LIMIT 10`, ordered by
`normalized_name, id`", "returns at most 10 rows") and FR-9's "an empty
Search String never matches everything" (400 *or* empty 200 both qualify).

**The AD gap:** AD-7 pins the query plan, not the response contract. FR-9
pins the cap, not the envelope or the blank case.

**Suggested fix:** extend AD-7: response is exactly `{"items": [Suggestion]}`
— no pagination metadata; blank/whitespace/missing prefix returns `200` with
`{"items": []}`, never `400` (typeahead must never error); `Suggestion` =
`{ id, name, parentPath }` — FR-9 explicitly anticipates same-named files in
different folders, and ten identical names with no path give the user
nothing to pick between.

## F-6 — "Valid item id, wrong type" is undefined on the folder-addressed endpoints

**Severity: Medium** — reachable in ordinary UI races (navigate into a
folder that another action just deleted or that is a file).

**The two compliant-but-incompatible constructions:**

- Dev A owns listing: `GET /api/folders/{id}/items` with a *file's* id →
  `404 not_found` ("no folder with that id").
- Dev B owns search: `GET /api/search?scope=folder&folderId={fileId}` →
  `400 validation_failed` on `folderId` (mirroring FR-1's parent-is-file
  rule for POST).

Team SPA, handling both endpoints, now faces two different contracts for the
same mistake, and either team's choice can be argued as compliant: AGENTS.md
§4 fixes 404 for GET item and 404-for-nonexistent-parent on POST, but says
nothing about wrong-type ids on GET/search.

**The AD gap:** no AD or §4 row covers type mismatches between an id's
resource and the addressed resource.

**Suggested fix:** pin both in AD-6: `GET /api/folders/{id}/items` —
nonexistent id **or** file id → `404 not_found`; `folderId` on search —
missing/nonexistent → `404 not_found`, file id → `400 validation_failed`
with field `folderId` (consistent with FR-1's parent rule).

## F-7 — Loose ends (each a one-line fix, each a possible mismatch)

**Severity: Low** — none blocks a screen alone; each costs an integration
argument.

- **PATCH payload optionality:** is `{ "name": … }` required? Dev A returns
  `400 validation_failed` for `{}` / missing `name`; Dev B treats it as a
  no-op `200`. Fix: pin required, `400`.
- **Unknown JSON fields in write models:** ignored (Symfony DTO default) or
  rejected? Fix: pin "ignored" in the conventions.
- **`details` on non-400/409:** absent or `[]`? The conventions imply
  400/409 only, but a SPA with a strict parser breaks on the other choice.
  Fix: pin absent on 404/500; SPA types mark it optional.
- **Page metadata nesting:** flat vs `{ items, page: {…} }` — folded into
  AD-9 (F-1); listed here so it is not re-litigated later.

---

## What survived the attack (no action needed)

- **AD-4 + the partial unique index** close the classic two-roots /
  double-seed collision loudly — the second NULL-parent insert fails at the
  database, not in a review.
- **AD-5 + the DB unique constraint** close the create/rename same-name race;
  PRD FR-1's concurrent-create consequence (exactly one 201, one 409) is
  achievable as specified.
- **The error envelope** (codes, message, field-level `details` on 400/409)
  is fully pinned by AGENTS.md §4 — the SPA can parse errors today.
- **AD-8** closes CORS and split-origin drift between dev and compose.
- **ID representation** ("UUIDs, opaque strings in JSON") is unambiguous
  enough for the SPA; only the Root's *value* is missing (F-2).

## Recommended disposition

| Finding | Action |
| --- | --- |
| F-1 read-model contract | New AD-9 |
| F-2 root addressing | New AD-10 (recommend fixed UUID + migration owner) |
| F-3 parentPath shape + ancestors | Tighten AD-6 and AD-2 |
| F-4 normalizer owner | Tighten AD-3 |
| F-5 suggestions envelope + blank prefix | Tighten AD-7 |
| F-6 wrong-type ids | Tighten AD-6 |
| F-7 loose ends | One-liners into AD-6 / Consistency Conventions |

All fixes are contract text, not new architecture: no new layer, service, or
dependency — the spine's structure is sound; its interface specification is
the gap.
