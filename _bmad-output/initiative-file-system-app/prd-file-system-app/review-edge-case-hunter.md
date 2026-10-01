# Edge-Case Hunter Review — PRD File System App

**Reviewer:** edge-case-hunter (path-tracing pass over requirements)
**Date:** 2026-09-30
**Scope:** `prd-file-system-app.md` (FR-1..FR-9, glossary, NFRs, UJs), `addendum.md`; AGENTS.md §4 and §12 read as boundary context only.
**Method:** mechanically walk every FR's "Consequences (testable)" against every rule asserted elsewhere in the PRD (glossary, UJs, NFRs, assumptions) and AGENTS.md §4; report only unhandled paths — boundaries and interactions no stated consequence pins down.

**Verdict:** The FR set is well-shaped but its testable consequences cover happy paths plus a few named validations; the collation/case rule, empty search input, pagination ordering, and several root/rename/concurrency boundaries are un-pinned, and two sections contradict each other (or AGENTS.md §4) as written. 21 findings: 1 critical, 6 high, 9 medium, 5 low.

Severity counts: **critical 1 · high 6 · medium 9 · low 5**.

---

## Critical

### EC-01 — "Exact" search has no case/collation rule, and the one assumption that exists contradicts §8
**Location:** §4.5 FR-7, FR-8 (consequences); §3 Glossary "Exact-Name Search"; §8 Open Question 2; §9
**Gap:** FR-7/FR-8 consequences say only "returns only exact matches". §3 assumes case-insensitive uniqueness, and Open Question 2 says "case-insensitive uniqueness **and matching** assumed; confirm" — yet it stays an *unconfirmed open question* while FR-1's consequences already hard-code case-insensitive duplicates. So: does searching `INVOICES` find `invoices`? Is "exact" byte-equality or case-folded equality? Neither reading is testable until one is chosen, and the addendum explicitly defers normalization ("decide normalization there") — but the *matching semantics* are product behavior, not architecture. Unicode makes it worse: `café` (NFC) vs `cafe\u0301` (NFD), `ß` vs `SS`, Turkish dotless `i` — "case-insensitive" is undefined without a named folding rule.
**Fix:** Decide in the PRD, close Open Question 2, and add to §9. Add consequences to FR-7/FR-8: "Matching compares a normalized name (case-folded, NFC) — searching `INVOICES` finds `invoices`; `cafe\u0301` finds `café`." State the folding rule itself (e.g., "lowercase + NFC into a `normalized_name` used for uniqueness, search, and suggestions") and let architecture only pick the implementation.

## High

### EC-02 — FR-9's "deterministic [alphabetical]" ordering has no tie-break, and ties are globally legal
**Location:** §4.5 FR-9
**Gap:** Sibling uniqueness is per-parent (§3), so two Files named `invoice` in different folders are legal. "Ordered alphabetically" by name alone then leaves ties — which 10 of 30 same-named files appear is implementation-dependent, so the consequence is not deterministic and not testable.
**Fix:** "Ordered by normalized name, then by a stable secondary key (full path or id) as tie-break" — add a consequence: "Two files with the same name yield a stable, reproducible suggestion order."

### EC-03 — Empty/blank Search String behavior is undefined for all three search FRs — and it is the first state of the happy path
**Location:** §4.5 FR-7, FR-8, FR-9; §2.3 UJ-2 (covers only the *no-match* case)
**Gap:** Every typeahead starts with an empty box; `""` "starts with" matches **every** file. Does an empty/whitespace-only Search String return the first 10 files, nothing, or a 400? Same question for exact search. No consequence pins it; UJ-2's edge case only covers a non-empty string matching nothing.
**Fix:** Add one consequence covering all three FRs, e.g.: "An empty or whitespace-only Search String is invalid input (400 `validation_failed`), not a match-all" — or explicitly "returns no suggestions until ≥1 non-space character is typed."

### EC-04 — Paginated listings/searches have no ordering, so page membership is untestable
**Location:** §4.1 FR-2; §4.5 FR-7, FR-8 ("paginated" with no order)
**Gap:** FR-2/FR-7/FR-8 assert pagination but no sort order. Without a deterministic order, "page 1 contains X" cannot be asserted, pagination metadata `total` is the only testable part, and insert/rename can shuffle items between already-served pages. FR-9 gets ordering right; the paginated collections don't.
**Fix:** Add to FR-2 (and mirror in FR-7/FR-8): "Listings are ordered deterministically (e.g., folders before files, then normalized name, then id) and that order is stable across pages."

### EC-05 — Pagination boundaries unspecified: offset ≥ total, limit = 0, negative values, limit above cap; the cap itself has no value
**Location:** §4.1 FR-2 (and FR-7/FR-8); AGENTS.md §4 ("sane defaults and a cap" — undefined)
**Gap:** "Paginated" is claimed testable, but no consequence defines: the default page size, the maximum `limit` (cap), behavior when `offset` ≥ `total` (empty 200 page or 404?), `limit=0`, negative `limit`/`offset` (clamp or 400?). SM-2 demands edge-case tests per FR; these can't be written.
**Fix:** Pin the numbers in the PRD or by reference: "default limit N (e.g. 50), cap M (e.g. 100), `limit`/`offset` outside range → 400 validation error; `offset` ≥ `total` → 200 with empty items and correct `total`."

### EC-06 — FR-6 has no "cannot delete the Root" consequence — the glossary forbids it, FR-4 mirrors rename, FR-6 stays silent
**Location:** §4.4 FR-6 (consequences); §3 Glossary "Root … cannot be created, renamed, or deleted"
**Gap:** §3 and FR-4 ("The Root cannot be renamed") establish the root special case, but FR-6's consequence list never says a Root delete is rejected. An implementer satisfying FR-6's three listed consequences could delete the Root. Also unmapped: which error? AGENTS.md §4's vocabulary (`validation_failed`/`not_found`/`conflict`) has no obvious fit for "exists but is not deletable" — `not_found` is a lie, `conflict` is undefined.
**Fix:** Add consequence: "Deleting the Root is rejected (choose the code, e.g. 400 `validation_failed` with a field-level message) and the tree is intact afterwards."

### EC-07 — Rename self-conflict: case-only rename and rename-to-same-name are undefined
**Location:** §4.4 FR-4 ("Same validation as creation: … sibling uniqueness")
**Gap:** Reusing creation validation verbatim makes `notes` → `notes` (and `notes` → `Notes`) collide *with itself* under case-insensitive uniqueness. Is a case-only rename allowed (real filesystems: yes)? Is a no-op rename a 200 or a 400? Un-pinned, and it is the exact boundary the case-insensitivity assumption creates.
**Fix:** Add consequence: "The uniqueness check excludes the item itself; renaming to its own current name (including a case-only change) succeeds and changes stored casing" — or explicitly rejects it; either, but decide.

## Medium

### EC-08 — Concurrent same-name creation (and rename-vs-create) has no pinned outcome
**Location:** §4.1 FR-1; §4.2 FR-3; §4.4 FR-4
**Gap:** "A name duplicating a sibling's fails" covers the sequential case only. Two simultaneous POSTs with the same normalized name (or a rename racing a create) must yield exactly one 201 and one 409 — none of the consequence lists say so, and SM-2's "functional API tests" can't assert the winner.
**Fix:** Add one shared consequence: "Concurrent same-name creation resolves to exactly one success and one 409 `conflict`; the uniqueness invariant holds under parallel requests."

### EC-09 — Cross-type sibling conflict (File `a` vs Folder `a`) is ambiguous
**Location:** §3 Glossary "Names are unique among siblings"; §4.1 FR-1; §4.2 FR-3
**Gap:** "Sibling" can be read as same-type-only. The Windows/macOS framing the assumption invokes conflicts files and folders with the same name; the PRD never says whether a File `notes` blocks a Folder `notes` in the same parent.
**Fix:** State in §3: "Names are unique among all sibling *Items*, regardless of type" and add a consequence to FR-3: "Creating a File named like an existing sibling Folder (case-insensitive) fails with the duplicate error."

### EC-10 — Name grammar underspecified: trim/"blank" semantics, leading/trailing spaces, `.` and `..`, control characters, chars vs bytes
**Location:** §4.1 FR-1 (bounds: "Blank names, names over 255 characters, no `/` or `\`")
**Gap:** Un-pinned boundaries: (a) is `" notes "` blank? trimmed-then-checked or literal? (b) `.` and `..` contain neither separator yet are path sentinels — legal? (c) names containing newlines/control characters pass the stated rules; (d) 255 *characters* vs *bytes* (matters for indexes and for UTF-16 surrogate pairs).
**Fix:** Extend the bounds line: "Names are trimmed; blank-after-trim is rejected; `.` and `..` and any control character are rejected; length limit is 255 characters."

### EC-11 — FR-7 vs FR-8 asymmetry: scoped search returns *Items*, global returns *Files* — and the brief says "files" for both
**Location:** §4.5 FR-7 ("find Items") vs FR-8 ("find Files") vs FR-9 ("Files"); AGENTS.md §12 ("Search files … within a parent folder or across all files")
**Gap:** Scoped search finds files *and folders*, global finds files only. If deliberate, no consequence or rationale says so; if accidental, it contradicts both FR-8 and the verbatim brief. Related unhandled root case: choosing the Root as FR-7's scope makes it a superset of FR-8 — behavior there is undefined.
**Fix:** Pick one: align FR-7 to Files (matches brief), or keep Items and add a consequence stating folders are included in scoped search only, and define Root-as-scope behavior explicitly.

### EC-12 — FR-5's consequences omit every post-delete invariant that FR-6 spells out
**Location:** §4.4 FR-5 (single consequence: not-found) vs FR-6
**Gap:** FR-6 verifies the subtree is gone from listings *and search results*; FR-5's list never asserts the deleted File disappears from its parent's listing, from exact search (FR-7/FR-8), or from suggestions (FR-9). The description says it in prose; "Consequences (testable)" — the contract SM-2 tests against — doesn't.
**Fix:** Mirror FR-6: "After deletion the File appears in no listing, no exact-search result, and no suggestions; re-creating the same name in the same parent succeeds."

### EC-13 — Rename/search interaction unstated
**Location:** §4.4 FR-4 vs §4.5 FR-7/FR-8/FR-9
**Gap:** Delete gets a search-visibility consequence (FR-6); rename gets none. After renaming `a.pdf` → `b.pdf`: old name must stop matching FR-7/FR-8, new name must start matching, suggestions must reflect the new prefix, and FR-9's ordering position must move. A renamed Folder must also remain a valid FR-7 scope.
**Fix:** Add to FR-4: "After a rename, exact search and suggestions reflect the new name only (both case variants), and a renamed Folder remains a valid search scope with its subtree unchanged."

### EC-14 — "Deeply nested" is untestable; no max depth, no verified depth
**Location:** §4.4 FR-6 ("Deeply nested subtrees delete completely and consistently"); §4.1 FR-1 (unbounded creation depth); NFR-1
**Gap:** "Deeply" and "consistently" are filler without a number. Nothing states whether depth is unbounded (a 10,000-level chain is legal?) or capped, and recursive operations (cascade delete, subtree search) hit real limits long before NFR-1's 100k items do if shaped as a chain.
**Fix:** Either state a max depth (rejected beyond N, e.g. 255) or make it measurable: "a subtree of depth ≥ 50 deletes completely; depth is unbounded by contract."

### EC-15 — Create with a nonexistent/deleted parent, or inside a File: no error consequence, and AGENTS.md §4's POST row (400/409) has no 404
**Location:** §4.1 FR-1; §4.2 FR-3 ("inside any Folder"); AGENTS.md §4 route table
**Gap:** `POST` with a `parentId` that doesn't exist, was cascade-deleted a moment ago, or points at a *File* — none of the consequences say what happens, and the inherited status vocabulary doesn't obviously cover "parent not found" for POST (404 is listed for GET item / PUT/PATCH / DELETE, not POST).
**Fix:** Add: "Creating inside a nonexistent or deleted parent fails (pick 400 or extend the §4 table with 404-on-POST); creating inside a File is rejected with the same duplicate/validation path."

### EC-16 — Duplicate-name error framing: "field-level validation error" (FR-1/UJ-1) vs duplicates → 409 `conflict` (AGENTS.md §4)
**Location:** §4.1 FR-1; §2.3 UJ-1; NFR-4 → AGENTS.md §4 ("duplicates/conflicts → 409", `validation_failed` is the 400 code)
**Gap:** FR-1 and UJ-1 promise a *field-level validation error*, but §4 (which NFR-4 makes binding) classifies duplicates as 409 `conflict`, not 400 `validation_failed`. Does the 409 envelope still carry `details[{field: "name"}]`? As written, an implementer satisfies one document and fails the other.
**Fix:** State: "Duplicate sibling name → 409 `conflict` with field-level `details` for `name` in the standard envelope" and reword FR-1/UJ-1 from "validation error" to "conflict error with a field-level message."

## Low

### EC-17 — NFR-1 covers browsing/search at 100k items; the write path at scale is unbounded
**Location:** §4.6 NFR-1; §4.4 FR-6
**Gap:** "Correct and responsive" scopes to browsing and search only. Cascade-deleting a 100k-item subtree has no performance or partial-failure expectation (timeout? atomicity if it dies mid-walk?).
**Fix:** Extend NFR-1 or add an FR-6 consequence: "deleting a subtree of ≥ 10,000 items completes within a stated bound and is all-or-nothing."

### EC-18 — Out-of-order typeahead responses (stale keystroke race) undefined
**Location:** §4.5 FR-9; NFR-2
**Gap:** Each keystroke fires a request; a slow response for `i` can arrive after `inv` and overwrite correct suggestions. Nothing requires results to correspond to the current prefix.
**Fix:** Add consequence: "suggestions displayed always correspond to the current Search String; stale responses are discarded (client-side or request-tagged)."

### EC-19 — Zero-match explicit empty state exists only in UJ-2, not in FR-7/FR-8 consequences
**Location:** §4.5 FR-7, FR-8; §2.3 UJ-2 edge case
**Gap:** UJ-2 promises an explicit empty state for no matches, but the FR consequences (the tested contract) never restate it; FR-7/FR-8 could return an error or empty 404-style response and still pass their listed consequences.
**Fix:** Add to both: "Zero matches → 200 with empty `items` (explicit empty state), never an error."

### EC-20 — Globally duplicate names are indistinguishable in FR-8 results
**Location:** §4.5 FR-8
**Gap:** Same-named files in different folders are legal; global exact search returning two identical names gives the user (and the test) no way to tell them apart — results need parent/path context, which no consequence requires.
**Fix:** Add: "each result includes its parent path so same-named Files are distinguishable."

### EC-21 — NFR-2's p95 has no corpus shape; worst case is untested
**Location:** §4.6 NFR-2 (200 ms p95 at 100,000 files)
**Gap:** 100k files with *dispersed* prefixes is easy; 60k files all starting with `a` makes every `a`-keystroke a worst-case prefix scan. The metric is testable only against a defined distribution.
**Fix:** Specify: "measured against a seeded corpus including a worst-case shared prefix (≥ 50% of files sharing one first letter)."

---

## Notes on contradictions found (per review brief)

1. **FR-7 (Items) vs FR-8 (Files) vs brief §12 ("files" both scopes)** — EC-11.
2. **§8 Open Question 2 (case matching "confirm") vs FR-1 consequences already asserting case-insensitive duplicates** — EC-01.
3. **UJ-1/FR-1 "field-level validation error" vs AGENTS.md §4 duplicates → 409 `conflict`** — EC-16.
4. **§3 "Root … cannot be deleted" vs FR-6 consequences omitting it** — EC-06.
5. **FR-5 vs FR-6 asymmetric consequence completeness for identical delete semantics** — EC-12.
