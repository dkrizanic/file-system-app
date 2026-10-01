# Reconciliation: Task Brief (AGENTS.md §12) vs PRD + Addendum

- **Source input:** company task brief, quoted verbatim in `AGENTS.md` §12.
- **Checked against:** `prd-file-system-app.md` (PRD) and `addendum.md`, same folder.
- **Method:** every sentence, bullet and note of the brief extracted verbatim, then
  located in the PRD/addendum. Verdicts: COVERED, COVERED-TAGGED (covered via a
  flagged assumption), DELIBERATE (explicit scope decision recorded), GAP (dropped,
  weakened, or silently changed), ADDED (PRD content beyond the brief).
- **Overall:** nothing from the brief is dropped or weakened. Five documentation
  gaps where the PRD either fails to record the brief's latitude or makes an
  untagged interpretation; several deliberate additions beyond the brief.

## 1. Requirement-by-requirement extract

### Preamble

| Brief (verbatim) | PRD coverage | Verdict |
|---|---|---|
| "large-scale browser-based file system" | Vision §1 (browser-based); NFR-1 quantifies "large-scale" as 100,000+ items | COVERED. The 100k figure and NFR-2's ~200 ms p95 are invented quantifications of "large-scale" — sensible PRD practice, but untagged inferences. |
| "functionally similar to Dropbox's web interface, or a folder browsing structure … on a Windows or macOS device" | Vision §1 ("works the way a desktop file explorer does"); Glossary §3 leans on Windows/macOS framing for case-insensitive uniqueness | COVERED. |

### Feature bullets

| Brief (verbatim) | PRD coverage | Verdict |
|---|---|---|
| "Create folders and subfolders" | FR-1 | COVERED. |
| "Create new files in the folders" | FR-3 ("inside any Folder, including the Root") | COVERED, with a silent small extension: the brief says files are created *in the folders*; FR-3 also allows files directly in the Root. Consistent with FR-1's Root handling, but not flagged. |
| "Search files by their exact name within a parent folder or across all files" | FR-7 (within a folder) + FR-8 (across all files) | COVERED, with two untagged interpretations — see Gap 2. (a) "within a parent folder" became "within one chosen Parent Folder's **subtree**" (§3 Glossary, FR-7) — subtree vs direct children is never decided or tagged. (b) FR-7 searches **Items** (files *and* folders) while the brief says *files*; FR-8 stays files-only, so the two FRs are also mutually inconsistent on result type. |
| "List the top 10 files that start with a search string. This will be used in the search box to show possible matches when the user is typing. Only 'start with' logic is required." | FR-9 (up to 10 Files, starts-with only, typeahead, NFR-2 latency, UJ-2) | COVERED. "Up to 10" ≈ "top 10". Ordering (alphabetical) and global scope are tagged `[ASSUMPTION]` and indexed in §9; the addendum records the autocomplete-scope alternative considered. Cleanest mapping in the PRD. |
| "Delete folders and files" | FR-5, FR-6 | COVERED. Cascade delete semantics and the alternatives (refuse-if-non-empty, trash) are recorded as a user decision (PRD §4.4 + addendum). DELIBERATE. |

### Constraints and notes

| Brief (verbatim) | PRD coverage | Verdict |
|---|---|---|
| "a file is simply its name and does not contain any other content" | Vision §1, Glossary §3 (File), Non-Goals §5 | COVERED. |
| "The frontend does NOT have to include any design or be adapted for mobile devices." | Non-Goals §5 ("Visual design and mobile/responsive adaptation — explicitly waived by the brief"); §2.3 scope dial "deliberately unstyled UI" | COVERED. |
| "The default React framework is acceptable." | §6.1 "React UI"; stack detail (React + TS + Vite) lives in AGENTS.md §3 | COVERED. Note: the brief waives design polish, not usability; UJ-1/UJ-2 and FR-2's "explicit empty state" reasonably raise the UI bar slightly above "no design". Acceptable reading, worth knowing. |
| "API service should use a SQL or noSQL database (of your choice! InMemory or File DB is also acceptable)." | §6.1 fixes PostgreSQL; AGENTS.md §3 records the stack | GAP 1 — see below. The brief's explicit latitude (including in-memory / file DB) is nowhere acknowledged; the PRD states PostgreSQL as if it were a requirement instead of recording the choice as deliberate against the brief's minimum. |
| "Provide a README with instructions on how to deploy your application." | NFR-3 ("a fresh reviewer reaches a running app using only the README"), SM-1, §6.1 "honest README"; README contract in AGENTS.md §10 | COVERED. Minor wording nuance: the brief says *deploy*, the PRD/AGENTS say *run* — equivalent for a take-home. |
| "Solution has to build and run in debug mode" | NFR-3 ("builds and runs in debug mode") — verbatim | COVERED. What "debug mode" concretely means per stack (Symfony dev env, Vite dev server, `APP_ENV=dev`) is left to architecture — fine at PRD level, but NFR-3 gives no acceptance shape. |
| "Docker is optional and it will be considered." | §6.1 makes "Docker one-command run" MVP scope | GAP 4 — see below. The PRD turns an explicit optional/bonus into an MVP requirement without noting the brief's framing. |
| "The solution must be delivered via a Git repository." | Only implicit: SM-1/NFR-3 ("a reviewer clones the repo"); AGENTS.md §7 + D4 own git | GAP 5 — see below. The delivery-vehicle requirement is never stated as such in the PRD. |
| "We will rate your solution on code structure, readability, and maintainability." | §0 purpose, §2.1 JTDB ("demonstrate craft — structure, readability, maintainability"), SM-C1 counter-metric | COVERED. |
| "To scope down this assignment, please don't worry about authentication or authorization." | NFR-5 (No auth, single-user, stated in README), Non-Goals §5, Vision §1 | COVERED. |

## 2. Gaps (dropped, weakened, or silently changed)

1. **DB latitude unrecorded** — the brief explicitly allows SQL, noSQL, in-memory
   or file DB; the PRD (§6.1 In Scope) fixes PostgreSQL without acknowledging the
   latitude or recording the choice as deliberate. The choice is defensible
   (NFR-1/NFR-2 need indexed prefix lookups; Docker makes Postgres cheap) — but
   the PRD should say so. → fix in §6.1 or a decision note.
2. **FR-7 scope and subject silently interpreted** — "within a parent folder"
   became "the folder's subtree" (§3, FR-7), and FR-7 returns *Items* (folders
   included) while the brief says *files* and FR-8 correctly says *Files*.
   Neither interpretation is tagged or in the Assumptions Index, and the FR-7 /
   FR-8 inconsistency survives. → tag in §3/FR-7 + §9, and align FR-7/FR-8.
3. **Rename (FR-4) is beyond the brief's feature list** — the addendum records
   "Brief omits rename and move. User chose rename in, move out", but the PRD
   body presents rename as an ordinary feature with no beyond-brief marker, in
   tension with its own counter-metric SM-C1 ("feature count beyond the brief —
   extra features lower the score"). → mark FR-4/§4.3 as a deliberate
   beyond-brief addition (cheap, reuses creation validation) so a reviewer reads
   it as a scope decision, not scope creep.
4. **Docker optionality unnoted** — brief: "Docker is optional and it will be
   considered"; PRD §6.1 makes the Docker one-command run an MVP requirement
   with no note that the brief treats it as a bonus. → one clause in §6.1.
5. **Git delivery never stated** — the brief's "must be delivered via a Git
   repository" appears in the PRD only implicitly ("clones the repo" in
   SM-1/NFR-3); AGENTS.md owns the conventions but the PRD never carries the
   requirement. → fold into NFR-3 or §6.1.

Minor, not counted as gaps: files creatable in the Root (FR-3) — silent small
extension of "in the folders"; NFR-1/NFR-2 numbers (100k, ~200 ms p95) —
untagged quantifications of "large-scale"; Root immutability (§3) — untagged
inference; "deploy" rendered as "run" — equivalent here.

## 3. Added beyond the brief (silently or deliberately)

| Addition | Where | Recorded? |
|---|---|---|
| List/browse folder contents with pagination (FR-2) | §4.1 | Silent, but implied by "folder browsing structure" and required by every other feature; pagination mandated by AGENTS.md §4. Acceptable. |
| Rename (FR-4) | §4.3 | Deliberate (addendum), unmarked in PRD body — Gap 3. |
| Case-insensitive sibling-name uniqueness | §3, FR-1 | Tagged `[ASSUMPTION]`, indexed §9. Clean. |
| Name validation bounds (non-blank, ≤255, no `/` `\`) | FR-1 | Tagged `[ASSUMPTION]`, indexed §9. Clean. |
| Suggestion ordering (alphabetical), global suggestion scope | FR-9, §4.5 | Tagged `[ASSUMPTION]`, indexed §9; alternatives in addendum. Clean. |
| Scale/latency numbers (100k items, ~200 ms p95) | NFR-1, NFR-2 | Untagged quantification of "large-scale". |
| Root cannot be created/renamed/deleted | §3, FR-4 | Untagged inference. Trivial, but technically an unconfirmed decision. |
| Files in the Root | FR-3 | Silent extension. |
| Error envelope, status-code map, camelCase, pagination metadata | NFR-4 → AGENTS.md §4 | Lives in AGENTS.md by design (PRD §0 boundary); supports the rating criteria. Clean. |

## 4. Verdict

No brief requirement is dropped or weakened: every bullet and note lands in the
PRD (FR-1…FR-9, NFR-3, NFR-5, Non-Goals, SM-C1) or in a recorded scope decision
(cascade delete, rename). The reconciliation risk is narrower: the brief's
*latitude* (DB choice, Docker optional) and two search-scope interpretations are
absorbed silently rather than recorded, and rename — the one feature addition —
is not marked as such in the PRD body despite SM-C1 penalizing extra features.
Fixes are documentation-level: add the notes from §2 above.
