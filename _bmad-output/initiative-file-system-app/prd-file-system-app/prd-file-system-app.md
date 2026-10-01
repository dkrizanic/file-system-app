---
title: File System App — PRD
status: final
created: 2026-09-30
updated: 2026-09-30
---

# PRD: File System App

## 0. Document Purpose

This PRD defines the product requirements for the File System App — a
browser-based file system built as a take-home task for a PHP developer
interview. It is written for the hiring reviewer evaluating the solution and for
the downstream artifacts (architecture doc, tickets) that implement it.
Vocabulary is anchored in §3 Glossary; features carry globally numbered FRs;
every inference made without confirmation is tagged `[ASSUMPTION]` and indexed
in §9. Engineering conventions — stack, API shape, git, testing — live in
`AGENTS.md` and are deliberately not duplicated here.

## 1. Vision

A browser-based file system that works the way a desktop file explorer does:
folders inside folders, files with names, immediate search. The user organizes
a large tree of named items and finds any of them again by name — through
scoped exact search while browsing, or through instant typeahead suggestions
across everything.

It is deliberately minimal: a file is its name and nothing else; there is no
content, no auth, no sharing. The value is the structure itself — a fast,
correct, well-tested hierarchy of named items that stays responsive at large
scale, delivered as code a reviewer can read, run, and trust.

## 2. Target User

### 2.1 Jobs To Be Done

- Organize a large set of named items into a nested folder structure (functional)
- Retrieve a known item by name without remembering where it lives (functional)
- Trust that deletes and renames never silently corrupt the structure (emotional)
- For the builder: demonstrate craft — structure, readability, maintainability —
  to a hiring reviewer (contextual)

### 2.2 Non-Users (v1)

- Anyone needing file content, upload, sharing, sync, or multi-user access —
  explicitly not built (brief scope-down).

### 2.3 Key User Journeys

*Scope dial: light — single operator, no auth, deliberately unstyled UI.*

- **UJ-1. Dario builds a hierarchy.** Dario opens the app at the Root, creates
  folders "Projects" and "Invoices", drills into "Projects", creates subfolder
  "2026", and adds files "contract.pdf" and "notes.txt". Each item appears in
  the listing immediately. **Edge case:** creating a second "2026" inside
  "Projects" is rejected with a conflict error carrying a field-level message
  for the name.
- **UJ-2. Daria finds a file she filed months ago.** Daria types "inv" into the
  search box; before she finishes typing "invoices-q1", the typeahead shows up
  to 10 files starting with what she typed. She picks a suggestion and the file
  is located. She also submits "invoices-q1" as an exact search scoped to the
  Root and sees every file with exactly that name, each with its parent path.
  **Edge case:** a Search String matching nothing shows an explicit empty
  state, not an error.

## 3. Glossary

- **Item** — a File or a Folder. Lives in exactly one Parent Folder, except the Root.
- **File** — an Item whose entire payload is its name. No content, no size.
- **Folder** — an Item that can contain other Items, forming the hierarchy.
- **Root** — the single top-level Folder every Item descends from; it exists
  from the start and cannot be created, renamed, or deleted.
- **Parent Folder** — the Folder an Item directly lives in. Names are unique
  among all sibling Items regardless of type (a File `notes` blocks a Folder
  `notes`), compared case-insensitively `[ASSUMPTION: matches the Windows/macOS
  framing of the brief]`.
- **Normalized Name** — a name case-folded and Unicode-normalized (NFC). The
  Normalized Name drives uniqueness, Exact-Name Search, and Suggestions;
  displaying keeps the original casing.
- **Search String** — the text the user types into the search box.
- **Exact-Name Search** — finding Files whose Normalized Name equals the
  normalized Search String, scoped to one Parent Folder's subtree or across
  all Files.
- **Suggestions** — up to 10 Files whose Normalized Name starts with the
  normalized Search String, shown in the search box while typing.
- **Rename** — changing an Item's name while it stays in its Parent Folder.

## 4. Features

### 4.1 Folder Management

**Description:** The user creates folders and subfolders inside any existing
folder and browses a folder's contents in a paginated, deterministically
ordered listing. Realizes UJ-1.

#### FR-1: Create folder

The user can create a Folder inside any Folder, including the Root. Realizes UJ-1.

**Consequences (testable):**
- A name whose Normalized Name duplicates any sibling Item's (File or Folder)
  fails with `409 conflict` carrying a field-level message for `name`.
- Name grammar: names are trimmed; blank-after-trim, over 255 characters,
  containing `/`, `\`, `.`, `..`, or any control character are rejected with
  `400 validation_failed` `[ASSUMPTION: these bounds]`.
- Concurrent creation of two same-Normalized-Name siblings resolves to exactly
  one `201` and one `409`; the uniqueness invariant holds under parallel requests.
- Creating inside a nonexistent (or already deleted) parent fails with
  `404 not_found`; a parent that is a File fails with `400 validation_failed`
  on `parentId`.
- The new folder appears in its parent's listing.

#### FR-2: List folder contents

The user can open any Folder and see its Files and subfolders. Realizes UJ-1.

**Consequences (testable):**
- Listings are paginated with `limit` defaulting to 50 and capped at 100;
  `limit`/`offset` outside their valid range fail with `400 validation_failed`
  `[ASSUMPTION: these numbers]`.
- `offset` beyond the end returns `200` with empty `items` and a correct `total`.
- Listings are ordered folders-first then Files, each by Normalized Name then
  id; the order is stable across pages.
- Opening the Root shows top-level items.
- An empty folder shows an explicit empty state (`200`, empty `items`).

### 4.2 File Management

**Description:** Files are created by name inside folders; a file carries
nothing but its name. Realizes UJ-1.

#### FR-3: Create file

The user can create a File inside any Folder, including the Root. Realizes UJ-1.

**Consequences (testable):**
- Same name grammar, sibling uniqueness (including against a same-named
  sibling Folder), concurrency, and parent rules as FR-1.
- The new file appears in its parent's listing.

### 4.3 Renaming

**Description:** The user renames a File or Folder in place; the hierarchy
around it is untouched. Beyond the brief's feature list by explicit user
decision — a sanctioned addition, not scope creep (see SM-C1).

#### FR-4: Rename item

The user can change an Item's name while it stays in its Parent Folder.

**Consequences (testable):**
- Same name grammar as FR-1; sibling uniqueness is checked against siblings,
  excluding the item itself.
- Renaming to the item's own current Normalized Name — including a case-only
  change such as `notes` → `Notes` — succeeds and updates the stored casing.
- After a rename, Exact-Name Search and Suggestions match the new name only,
  under both case variants.
- A renamed Folder remains a valid search scope with its subtree unchanged.
- The Root cannot be renamed (`400 validation_failed`).

### 4.4 Deletion

**Description:** Deleting is destructive and immediate — no trash, no undo
(user decision: cascade semantics).

#### FR-5: Delete file

The user can delete a File; it disappears from its parent's listing.

**Consequences (testable):**
- Deleting a nonexistent File fails with `404 not_found`.
- After deletion the File appears in no listing, no Exact-Name Search result,
  and no Suggestions.
- Re-creating a File with the same name in the same parent succeeds.

#### FR-6: Delete folder (cascade)

The user can delete a Folder; everything inside it — at any depth — is deleted
with it.

**Consequences (testable):**
- Deleting the Root is rejected with `400 validation_failed`; the tree is
  intact afterwards.
- After deletion, no item of the subtree appears in any listing or search result.
- A subtree of depth ≥ 50 deletes completely; the delete is atomic — it either
  removes the whole subtree or none of it.

### 4.5 Search

**Description:** Two complementary retrievals: scoped exact lookup, and
typeahead suggestions in the search box `[ASSUMPTION: suggestions run across
all files, not only the current folder — the Dropbox-style global search box]`.
Realizes UJ-2.

#### FR-7: Exact-name search within a folder

The user can find Files whose Normalized Name equals the normalized Search
String within a chosen Folder's subtree (its own Files and all descendants').
Folders are not returned — the brief scopes search to files.

**Consequences (testable):**
- Returns only exact normalized matches inside the scope; ordered by
  Normalized Name then id; paginated per FR-2's contract.
- Choosing the Root as scope behaves identically to FR-8.
- A blank or whitespace-only Search String fails with `400 validation_failed`.
- Zero matches return `200` with empty `items` (explicit empty state), never
  an error.

#### FR-8: Exact-name search across all files

The user can find Files whose Normalized Name equals the normalized Search
String across the entire system.

**Consequences (testable):**
- Returns only exact normalized matches; ordered by Normalized Name then id;
  paginated per FR-2's contract.
- Each result includes its parent path, so same-named Files in different
  folders are distinguishable.
- A blank Search String fails with `400 validation_failed`; zero matches return
  `200` with empty `items`.

#### FR-9: Typeahead suggestions

While the user types, the system returns up to 10 Files whose Normalized Name
starts with the normalized Search String.

**Consequences (testable):**
- Only "starts with" logic on the Normalized Name — no substring, fuzzy, or
  content matching; searching `INVOICES` suggests `invoices`.
- At most 10 results, ordered by Normalized Name then id, so same-named Files
  in different folders yield a stable, reproducible order
  `[ASSUMPTION: alphabetical with id tie-break]`.
- No suggestions are produced until at least one non-space character is typed;
  an empty Search String never matches everything.
- Selecting a suggestion locates the File (it is shown to the user).
- Suggestions displayed always correspond to the current Search String; stale
  responses are discarded.
- Fast enough to feel instant while typing (NFR-2).

### 4.6 Cross-Cutting NFRs

- **NFR-1 Scale:** browsing and search stay correct and responsive with
  100,000+ items `[ASSUMPTION: scale target]`; a cascade delete of a
  10,000-item subtree completes within 10 seconds and is atomic
  `[ASSUMPTION: numbers]`.
- **NFR-2 Suggestion latency:** suggestions return within ~200 ms p95 at
  100,000 Files `[ASSUMPTION: target]`, measured against a seeded corpus that
  includes a worst case where ≥ 50% of Files share the same first letter.
- **NFR-3 Deliverability:** the solution builds and runs in debug mode; it is
  delivered via a Git repository (GitHub); a fresh reviewer reaches a running
  app using only the README.
- **NFR-4 API discipline:** REST per AGENTS.md §4 — correct methods and status
  codes, validated input, consistent error envelope (conflicts carry
  field-level `details`), paginated collections.
- **NFR-5 No auth:** single-user by design (brief scope-down), stated in the README.

## 5. Non-Goals (Explicit)

- File content, upload, download, sizes, thumbnails — a file is its name.
- Authentication and authorization — explicitly descoped by the brief.
- Moving items between folders — recorded in the README quality-of-life section.
- Trash, undo, version history — deletes are immediate.
- Sharing, sync, multi-device, multi-user collaboration.
- Visual design and mobile/responsive adaptation — explicitly waived by the brief.
- Fuzzy, substring, or content search — only exact and starts-with.

## 6. MVP Scope

### 6.1 In Scope

Features 4.1–4.5 (FR-1 through FR-9) delivered end-to-end: React UI, REST API,
PostgreSQL, Docker one-command run, functional and unit tests, honest README.

The brief grants latitude on the database (SQL, noSQL, in-memory, or file DB
all acceptable); PostgreSQL is a deliberate recorded choice (AGENTS.md
decision D-series), not an oversight. The brief makes Docker optional; we ship
it deliberately — the one-command run is what SM-1 measures.

### 6.2 Out of Scope for MVP

As §5. Deferred quality-of-life candidates (README section): move between
folders, trash with restore, bulk operations, drag-and-drop.
`[NOTE FOR PM: move is the most likely first addition.]`

## 7. Success Metrics

- **SM-1:** a reviewer clones the repo and reaches a running app within
  15 minutes using only the README. Validates NFR-3, NFR-4.
- **SM-2:** the full test suite is green — functional API tests for every FR
  including edge cases, unit tests for validation. Validates FR-1…FR-9.

**Counter-metrics (do not optimize)**
- **SM-C1:** feature count beyond the brief — extra features lower the score
  (rename is the single sanctioned exception, §4.3); structure, readability
  and maintainability are what is rated.

## 8. Open Questions

1. Autocomplete scope: global (assumed, §4.5) versus current-folder-first.
   Non-blocking — the assumption is tagged and indexed.

## 9. Assumptions Index

- §3 Parent Folder — sibling-name uniqueness is case-insensitive and
  type-agnostic (File vs Folder).
- §3 Normalized Name — case-folded, NFC; drives uniqueness, search, suggestions.
- §4.1 FR-1 — name grammar: trimmed, non-blank, ≤ 255 characters, no `/`, `\`,
  `.`, `..`, control characters.
- §4.1 FR-2 — pagination numbers: default 50, cap 100, out-of-range → 400,
  offset past end → empty 200.
- §4.5 — suggestions run across all files, not only the current folder.
- §4.5 FR-9 — suggestions ordered by Normalized Name with id tie-break.
- §4.6 — scale target 100k items; 10k-subtree atomic delete within 10 s;
  ~200 ms p95 suggestion latency with worst-case shared-prefix corpus.
- §4.3 / §5 — Root is immutable (no create, rename, delete) `[ASSUMPTION:
  framing; the brief never mentions a root at all]`.
