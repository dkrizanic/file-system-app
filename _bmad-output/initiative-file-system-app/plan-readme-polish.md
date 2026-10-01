---
title: 'README polish: full §10 contract, every claim verified'
type: 'chore'
ticket: ''
created: '2026-10-01'
status: 'ready-for-dev'
route: 'full'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/AGENTS.md', '{project-root}/_bmad-output/initiative-file-system-app/architecture-file-system-app/architecture-file-system-app.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `README.md` still describes the domain-model unit only — "There is no API and no frontend yet" (README.md:11), API overview "Empty for now" (README.md:112-116) — while the API and SPA units have delivered; AGENTS §10 requires eight sections whose every claim is true of the running system, and the D16 (no CI pipeline) and rename-sanctioning decisions are not yet in the decision log (verified: it ends at D15, AGENTS.md:324).

**Approach:** Rewrite the README against the running compose stack — run first, write second: execute every command the README will print, curl every route for the examples, run the suite on `db-test` — then bring AGENTS.md's decision log and project status in line with reality.

## Boundaries & Constraints

**Always:** every command, URL, status code, and response excerpt the README prints is executed in this unit and matches observed output; the eight §10 sections, in contract order (What it does · Requirements · How to run · How to test · API overview · Assumptions · Known limitations & trade-offs · What I'd improve with more time); the API overview carries the AD-6 route table plus one worked example per endpoint; the error envelope and fixed code vocabulary exactly as AGENTS §4; the mandated limitations all present — no frontend tests (D6), compose test-db instead of Testcontainers-PHP (D14), no CI pipeline (D16), rename as the sanctioned beyond-brief feature, move/trash deferred to improvements, debug-mode dev runtime; the mandated improvements all present — move, trash, a CI pipeline on GitHub Actions, FrankenPHP worker mode, Redis revisit (D2), Symfony 8.2 LTS hop; plain English, short sentences, no marketing tone (§11.2); README.md + AGENTS.md changes in one docs commit.

**Never:** no application code or dependency changes — a README claim that fails against the running system is reported for the owning unit, never reworded into vagueness; no new endpoints, error codes, routes, or CI files; no status box ticked without verifying it in the tree; no marketing tone anywhere in the README.

</frozen-after-approval>

## Code Map

- `README.md` -- the deliverable; current text predates the API and SPA units
- `AGENTS.md` -- decision log ends at D15 (D16/D17 to append); Project status checklist to tick (§11)
- `_bmad-output/initiative-file-system-app/architecture-file-system-app/architecture-file-system-app.md` -- AD-6 route table and AD-9 response shapes: content source for the API overview
- `compose.yaml`, `compose.test.yaml`, `compose.override.yaml` -- source of truth for run/test commands, ports 8080/5433, test profile
- `backend/composer.json` -- platform pin `8.5.0`; Requirements must match it (host needs Docker only)
- `backend/src/Controller/`, `frontend/src/` -- delivered by the api-endpoints and React units; examples are exercised against them

## Tasks & Acceptance

**Execution:**
- [ ] `git` -- create `docs/readme-polish` off the tip the orchestrator provides -- one branch per work unit (D13)
- [ ] `AGENTS.md` -- append D16 (no CI pipeline: quality gates run locally, README states it) and D17 (rename is the single beyond-brief feature, sanctioned per PRD FR-4, prd-file-system-app.md:145) -- the goal mandates both trade-off statements; §11.5 requires the decisions behind them logged
- [ ] `README.md` -- rewrite to the eight §10 sections against the running system: boot `docker compose up -d --build`, dry-run every planned command, curl all 8 AD-6 routes (plus one 400 and one 409 for the envelope), run the suite on `db-test`, capture real outputs, then write -- claims must match captured output, not memory
- [ ] `AGENTS.md` -- Project status: verify each open box against the tree, tick only the true ones; expected true: API endpoints + validation, React app, README polish; expected false: Tooling & CI (no pipeline, D16)

**Acceptance Criteria:**
- Given the finished README, when its How-to-run block is pasted into a fresh shell, then every command succeeds with the output the README describes.
- Given the How-to-test block, when pasted verbatim, `php bin/phpunit` is green on `db-test` and the stop command cleans up.
- Given each API-overview example, when its curl is replayed, then status code, headers (Location on 201), and body shape match what the README prints — 8 routes plus the 400/409 envelope examples.
- Given AGENTS §10, when the README is compared to it, all eight sections exist and every mandated limitation and improvement item is present.
- Given AGENTS.md, when read, D16 and D17 exist and each ticked status box is demonstrably true in the tree.

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

Plan-level decisions (pre-approved, no open questions):
- API overview shape: the AD-6 table verbatim (method, path, purpose, success) + one worked example per route — curl command and a short real response excerpt. The error envelope and code vocabulary are documented once; examples reference them.
- Rename appears in What it does as the single beyond-brief feature and again under Known limitations; move and trash are not implemented and live only under What I'd improve — per the PRD's deferred quality-of-life note (prd-file-system-app.md:282-284).
- D16/D17 are appended by this unit because the goal mandates their trade-offs and the on-disk log ends at D15 (verified 2026-10-01).
- Status-box expectation is pinned but verification wins: Tooling & CI stays unticked unless PHPStan, PHP-CS-Fixer, and a GitHub Actions pipeline are all present.
- Still-true limitation bullets carry forward (dev-only image, vendor volume with the manual `composer install` step, schema-tool warning, PostgreSQL 18 volume path, port 8080); claims made false by delivered units are rewritten, not kept.
- One commit, e.g. `docs(readme): bring the readme to the full section 10 contract`, body noting the run-everything-first method.

## Verification

**Commands:**
- `docker compose up -d --build` -- expected: all dev services healthy
- paste of the README's How-to-run block, verbatim -- expected: every command exits 0 with the described output
- `docker compose -f compose.yaml -f compose.test.yaml --profile test up -d --wait db-test` then `docker compose exec app php bin/phpunit` -- expected: suite green; `... --profile test stop db-test` after
- one curl per route, taken from the README's own examples -- expected: outputs identical to what the README prints (8 success examples + 1×400 + 1×409)

**Manual checks (if no CLI):**
- §10 checklist: eight sections, contract order
- tone pass: short sentences, no marketing words
- AGENTS.md: D16/D17 present; every ticked box matches the tree
