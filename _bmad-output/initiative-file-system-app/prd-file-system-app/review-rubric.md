# PRD Quality Review — File System App

Calibration: lean but rigorous. Engineering conventions
(stack, API shape, git, testing) deliberately live in AGENTS.md and their absence
from the PRD is not a gap. Reviewed artifacts: `prd-file-system-app.md`,
`addendum.md`, cross-checked against AGENTS.md §2/§3/§4/§6.

## Overall verdict

This is a genuinely lean, decision-complete PRD: every FR carries testable
consequences, the Non-Goals section does real work, the vision is specific to this
product, and the two UJs pull their weight — no theater anywhere. What is at risk
clusters in two places: §0 promises "every inference made without confirmation is
tagged `[ASSUMPTION]`" yet the most load-bearing ones (100,000+ items, ~200 ms p95,
"subtree", FR-7's Items-vs-Files asymmetry, the unrecorded DB/Docker latitude) are
silent; and the traceability layer has holes a story writer will trip on — exact-name
search (FR-7/FR-8) is attributed to a journey that never performs it, and no FR
defines what picking a suggestion does. All fixes are documentation-level; approve
to build after one tagging-and-tracing pass.

## Decision-readiness — strong

The PRD states decisions as decisions and names what was given up. Cascade delete is
marked "(user decision: cascade semantics)" (§4.4) with the losing alternatives and
reasons in the addendum ("Refusal adds an extra UI flow and endpoint for no rated
value; trash adds restore UX the product never asks for"). Rename-in/move-out is
decided the same way (§4.3 + addendum: "Rename reuses creation validation almost
entirely... move drags in subtree integrity concerns"). The three Open Questions
(§8) are genuinely open — each carries a working default, none is rhetorical. The
single `[NOTE FOR PM]` (§6.2, "move is the most likely first addition") sits at a
real deferred decision, not a safe checkpoint. Someone pushing back would find their
objection engaged in the addendum's Alternatives considered rather than dodged.

The one unacknowledged tension is internal: the counter-metric SM-C1 (§7) penalizes
"feature count beyond the core set" while FR-4 (rename) is exactly such a feature —
decided, but never reconciled with the counter-metric inside the PRD body, where the
justification ("nearly free") lives only in the addendum.

### Findings

- **[medium]** Counter-metric contradicts an in-scope feature (§7 SM-C1 vs §4.3 FR-4) — SM-C1 warns that "feature count beyond the core set — every extra feature adds surface to maintain," yet rename is beyond the core set (addendum: "The original scope omits rename and move"). As written, the PRD's own counter-metric penalizes FR-4, and a reader of the PRD body alone cannot resolve it. *Fix:* reword SM-C1 to "features beyond this PRD's agreed scope" and carry the addendum's one-line justification ("rename reuses creation validation almost entirely") into §4.3 so the decision survives the counter-metric on its own terms.

## Substance over theater — strong

No furniture anywhere. The Vision (§1) could not swap into another PRD in this
category: "a file is its name and nothing else; there is no content, no auth, no
sharing... delivered as code a reviewer can read, run, and trust." There is no
personas section, no differentiation/market filler — correctly resisted for this
shape. The NFRs (§4.6) carry product-specific numbers (100,000+ items, ~200 ms p95,
15-minute cold start) instead of boilerplate, and NFR-4 delegates API mechanics to
AGENTS.md §4 rather than restating them. Even the JTBD list earns its keep: the
contextual job — "keep the codebase worth maintaining" (§2.1) — is precisely
what SM-C1 operationalizes. The two UJs are a few lines each and carry edge cases
(the duplicate "2026" rejection, the explicit empty state) rather than sentiment.

### Findings

None at this dimension.

## Strategic coherence — strong

There is a thesis and everything follows from it: "deliberately minimal... The value
is the structure itself — a fast, correct, well-tested hierarchy of named items...
code a reviewer can read, run, and trust" (§1). Feature selection mirrors it: all
five original capabilities in, one near-free addition (rename), everything else out
with a reason (§5). The Success Metrics validate the thesis instead of measuring
activity — SM-1 tests reviewer-runnability, SM-2 tests correctness, and SM-C1 is a
counter-metric actively guarding the minimalism thesis. Operational rather than
user-facing metrics are the right shape for a single-operator tool per the rubric.
The one coherence seam (rename vs minimalism) is filed under Decision-readiness.

### Findings

None at this dimension.

## Done-ness clarity — adequate

Every FR carries a "Consequences (testable)" block, and most consequences are
genuinely falsifiable — duplicate sibling rejected case-insensitively, ≤10
alphabetical suggestions, explicit empty states, not-found on deleting a missing
file, "no item of the subtree appears in any listing or search result." That is
better discipline than most PRDs. Calibration note: pagination defaults, the error
envelope, and status-code mapping deliberately live in AGENTS.md §4, and that
boundary holds — the findings below are about what the PRD itself leaves unpinned.
Where it thins is the validation and search cluster: the name-rule set has known
gaps for a system framed against "Windows or macOS" (§3), FR-7/FR-8 leave scope
semantics an implementer must guess at, and two adjectives survive ("consistently,"
"responsive"). Downstream story creation leans hardest here, so per the rubric this
is judged unforgivingly: adequate, one pass away from strong.

### Findings

- **[medium]** Name-rule set incomplete for the desktop framing (§4.1 FR-1) — the rules reject blank, >255 characters, and `/` or `\`, but not ".", "..", whitespace-only or leading/trailing-space names, or Windows-reserved characters such as ":". AGENTS.md §6 demands coverage of "blank/oversized input... boundary values," and "." and ".." are the first names a senior reviewer will type into a file system. *Fix:* enumerate the rejected set in FR-1 (add ".", "..", trim-then-check blank, optionally a reserved-character list), or extend the existing `[ASSUMPTION]` to state these were consciously excluded.
- **[medium]** FR-7/FR-8 scope semantics left to the implementer (§4.5, §3) — FR-7 searches "Items" (folders included) "within one chosen Parent Folder's subtree" while FR-8 searches "Files" only; the original wording says "files" in both clauses. Neither the anchor folder's own membership in scope, nor the Items/Files asymmetry between the two FRs, is justified anywhere. *Fix:* one sentence per FR — state whether the chosen folder itself is in scope, and either justify folders-in-scoped-search (locating a folder is plausible intent) or align both FRs to Files.
- **[low]** Duplicate-name error class ambiguous against AGENTS.md §4 (§4.1 FR-1) — "fails with a field-level validation error" reads as 400/validation_failed, but AGENTS.md §4 maps "duplicates/conflicts → 409." *Fix:* write "409 conflict with field-level details" so functional tests assert the right status and code.
- **[low]** Listing order unspecified (§4.2 FR-2) — pagination is required but no sort is named; page boundaries and pagination tests are unstable without a deterministic order. *Fix:* one line, e.g. "listings sorted case-insensitively by name," plus whether folders and files interleave or separate.
- **[low]** NFR-1 "responsive" has no bound (§4.6) — NFR-2 gets a number (~200 ms p95) but browsing and exact search at 100,000+ items get an adjective. *Fix:* set a p95 for listing and exact search at NFR-1 scale, or state that AGENTS.md's pagination/no-N+1 rules are the operational bar.
- **[low]** "Completely and consistently" is half-falsifiable (§4.4 FR-6) — "no item of the subtree appears in any listing or search result" is testable; "consistently" is not, and "deeply nested" names no depth. *Fix:* replace with a fixture bound (e.g., "a subtree nested ≥10 levels deletes without orphaned descendants").

## Scope honesty — adequate

The scaffolding is exemplary: Non-Goals (§5) is specific and reasoned — seven
exclusions, each traced to a scope-down decision or a README quality-of-life note —
and the move de-scope is loud, not silent (§5 + §6.2 NOTE FOR PM). The four inline
`[ASSUMPTION]` tags roundtrip cleanly against §9, and each has a working default so
nothing blocks building. Open-items density (3 open questions, 4 assumptions, 1 note)
is right for the stakes.

But §0 promises "every inference made without confirmation is tagged `[ASSUMPTION]`
and indexed in §9," and the promise leaks in exactly the load-bearing places: NFR-1's
"100,000+" and NFR-2's "~200 ms p95" are invented quantifications of the
"large-scale" goal; FR-7's "subtree" is an interpretation of "within a parent folder";
Root immutability (§3) is an untagged inference; and the original explicit latitude —
DB of choice including in-memory/file, "Docker is optional," delivery via a Git
repository — is absorbed silently (§6.1 states PostgreSQL and the Docker one-command
run as if they were requirements; git appears only implicitly via "clones the repo"
in SM-1), and the PRD has not yet absorbed the fixes that close those gaps. One
tagging pass away from strong.

### Findings

- **[medium]** Untagged load-bearing inferences (§4.6 NFR-1/NFR-2, §4.5 FR-7, §3 Root) — the 100,000+ scale, the ~200 ms p95, the subtree reading, and Root immutability are presented as settled facts; per §0's own rule they are inferences from the original wording, and they are the figures the addendum itself says "push toward" an index-based design. *Fix:* tag them `[ASSUMPTION]` and index in §9, or confirm and close them into §8.
- **[medium]** Original latitude absorbed silently (§6.1, §4.6) — the working agreement explicitly allows any SQL/noSQL/in-memory/file DB and treats Docker as an optional bonus; the PRD states PostgreSQL and Docker as plain MVP requirements and never states the git-delivery requirement. The choices are defensible — recording them as choices is what is missing. *Fix:* one clause in §6.1 each: "PostgreSQL chosen over looser DB options because NFR-1/NFR-2 need indexed lookups"; "Docker elevated from optional to one-command-run"; "delivered via a private Git repository (AGENTS.md §7, D4)."
- **[low]** Rename not marked as a beyond-scope addition in the body (§4.3) — the addendum records the decision, but §4.3 presents rename as an ordinary feature with no marker, in tension with SM-C1. (Same root as the Decision-readiness finding; the body-level marker is the scope-honesty half of the fix.) *Fix:* a "[deliberate beyond-scope addition — reuses creation validation]" marker at §4.3.

## Downstream usability — adequate

This PRD is chain-top — AGENTS.md §11 routes it into the architecture doc and
tickets — so traceability matters more, not less. The mechanics are clean: glossary
terms (Item, File, Folder, Root, Parent Folder, Search String, Suggestions) are used
consistently inside FRs, consequences, and SMs; IDs (FR-1–9, NFR-1–5, SM-1/2/C1,
UJ-1/2) are contiguous and unique; cross-references resolve (FR-9 → NFR-2; NFR-4 →
AGENTS.md §4 exists; §6.2 → §5). Sections read sensibly pulled out alone, and FR-3's
"Same name rules and sibling uniqueness as FR-1" is an ID reference, not "see above."

The weakness is the UJ↔FR trace layer. §4.5 claims "Realizes UJ-2" for the whole
search section, but UJ-2's narrative exercises only the typeahead — the core
exact-name search (FR-7, FR-8) appears in no journey at all, and FR-4/FR-5/FR-6
carry no realization lines either (honestly absent, but un-traced). UJ-2's final
step — "She picks one and the item is found" — describes behavior no FR defines. For
a workflow that slices stories by journey, these are the gaps that surface mid-sprint.

### Findings

- **[medium]** Exact-name search is traced to a journey that never performs it (§4.5 vs §2.3 UJ-2) — FR-7 and FR-8 sit under a section claiming "Realizes UJ-2," but UJ-2 only types into the typeahead; journey-based story slicing will orphan the two search FRs the feature list puts first. *Fix:* extend UJ-2 (Daria finishes typing and hits exact search within a scope) or add a two-line UJ-3 for exact lookup; alternatively drop the claim on FR-7/FR-8 and cite the feature list directly — and add realization lines (or citations) to FR-4/FR-5/FR-6.
- **[medium]** "She picks one and the item is found" has no implementing FR (§2.3 UJ-2 vs §4.5 FR-9) — FR-9 shows suggestions; nothing says what picking one does (navigate to the containing folder? highlight a row? nothing?). The original scope only requires showing matches, so either the UJ over-promises or the FRs under-deliver. *Fix:* add the outcome to FR-9 ("selecting a suggestion navigates to the item's Parent Folder with the item highlighted") or reword UJ-2 to stop at seeing the match.

## Shape fit — strong

The shape matches the product. The scope dial is stated outright (§2.3: "light —
single operator, no auth, deliberately unstyled UI"); two short UJs instead of
template furniture; operational success metrics instead of user-facing ones — exactly
the capability-spec-with-light-UJs hybrid the rubric expects for a single-operator
tool. It resists both over-formalization (no personas, no market sections) and
under-formalization (glossary, consequences, counter-metric all present). It behaves
like a chain-top PRD: §0 declares the downstream audience and the AGENTS.md boundary,
and the addendum hands architecture real pointers instead of pre-deciding them. The
only seam is §6.1 naming concrete stack choices (React, REST, PostgreSQL, Docker) —
a summary, but of facts AGENTS.md owns, so a future stack change in AGENTS.md leaves
§6.1 drifting silently.

### Findings

- **[low]** §6.1 hard-codes stack names despite the boundary claim (§0 vs §6.1) — §0 says engineering conventions "live in AGENTS.md and are deliberately not duplicated here," yet §6.1 restates React/REST/PostgreSQL/Docker. Harmless today, a silent drift point if AGENTS.md §3 ever changes. *Fix:* either accept the summary consciously or reword §6.1 to "delivered end-to-end on the AGENTS.md §3 stack."

## Mechanical notes

- **Assumptions Index roundtrip:** clean — 4 inline `[ASSUMPTION]` tags (§3 Parent Folder, §4.1 FR-1, §4.5 description, §4.5 FR-9) ↔ 4 indexed entries in §9, all matching in substance and location.
- **ID continuity:** FR-1–FR-9, NFR-1–NFR-5, SM-1/SM-2/SM-C1, UJ-1–UJ-2 — contiguous, unique, no duplicates; all cross-references resolve (FR-9→NFR-2, NFR-4→AGENTS.md §4, §6.2→§5).
- **Glossary drift:** minor — "Suggestions" (§3, capitalized term) vs "typeahead suggestions" (§1 Vision, §4.5, FR-9 title) vs "results" (FR-9 consequence "At most 10 results"); "Exact-Name Search" (§3) vs "Exact-name search" (FR-7/FR-8 titles). Pick one canonical form per term for clean source extraction.
- **UJ protagonist naming:** UJ-1 names "Dario," UJ-2 names "Daria" — two names for a stated single-operator product; reads as a typo or improvised persona. Use one name in both.
- **Required sections for stakes:** all present (Vision, Target User, Glossary, Features with consequences, Non-Goals, MVP Scope, Success Metrics with counter-metric, Open Questions, Assumptions Index). Frontmatter still `status: draft` — flip once §8's questions are answered and the scope-honesty tags land.
- **Pattern worth keeping:** UJ-1's edge case ("creating a second '2026'... rejected") pre-loads FR-1's duplicate rule — reuse that pattern when fixing the UJ-2 findings.
