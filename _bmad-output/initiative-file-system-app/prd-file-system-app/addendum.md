# PRD Addendum: File System App

Depth that belongs downstream (architecture, solution design) or earned a place
without fitting the PRD narrative. Decisions below are mirrored in the run's
`.memlog.md`.

## Alternatives considered

- **Folder deletion.** Options: cascade (chosen) vs refuse-when-non-empty vs
  trash-with-restore. Refusal adds an extra UI flow and endpoint for no rated
  value; trash adds restore UX the brief never asks for. Cascade is one
  behavior, trivially testable, and matches "delete means delete".
- **Rename scope.** Brief omits rename and move. User chose rename in, move out
  (README quality-of-life). Rename reuses creation validation almost entirely,
  so it is nearly free; move drags in subtree integrity concerns.
- **Autocomplete scope.** Global (chosen assumption) vs current-folder-first.
  The brief describes the search box showing matches while typing, Dropbox-style;
  global matches that mental model. Folder-first optimizes for a browsing flow
  the typeahead does not serve.

## Technical pointers for the architecture doc

- The "large-scale" phrase plus NFR-1/NFR-2 pushes toward an index supporting
  prefix lookups (e.g. a b-tree on name or a normalized name column) rather
  than LIKE scans; exact measurement plan belongs to architecture.
- Sibling uniqueness maps naturally onto a composite unique index on
  (parent_id, normalized_name). The normalization *rule* is decided in the PRD
  (case-folded, NFC — see Glossary "Normalized Name"); architecture only picks
  the implementation (column + index strategy, casing storage).
- Cascade delete maps to recursive deletion strategy (application-side walk vs
  closure table vs materialized path) — an architecture decision with a
  README-tradeoff note either way.
- Pagination, error envelope, status codes: already fixed in AGENTS.md §4;
  architecture inherits, does not re-decide.

## PRD ↔ AGENTS.md boundary

The PRD owns *what the product does*; AGENTS.md owns *how code is written and
delivered*. Reviewers read the README first, AGENTS.md second, this PRD third.
