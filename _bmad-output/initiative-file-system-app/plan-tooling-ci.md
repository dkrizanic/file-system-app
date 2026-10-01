---
title: 'Local quality gates: PHPStan and PHP-CS-Fixer'
type: 'chore'
ticket: ''
created: '2026-10-01'
status: 'ready-for-dev'
baseline_revision: '64296de'
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

**Problem:** Nothing enforces AGENTS.md conventions yet — PHPStan and PHP-CS-Fixer are not installed (verified: absent from `backend/composer.json` require-dev), so type and style drift is caught only by reading. CI on GitHub Actions is now deliberately out of scope (user decision → D16), so the local gates are the whole enforcement layer.

**Approach:** Install both tools in the app container, commit `phpstan.neon` (level `max`, zero issues — fix code, never mute silently) and `.php-cs-fixer.dist.php`, run the fixer so the tree is formatted, rewrite AGENTS.md §8 to drop the CI promise while keeping the two gates, and record the CI trade-off and the deferred flex-lock note in the README.

## Boundaries & Constraints

**Always:** every composer/phpstan/fixer/phpunit command runs inside the app container (host PHP is 8.4; the project pins 8.5 — never on the host); PHPStan at level `max` over `src/`, `tests/`, `migrations/` with zero errors, no baseline file; fixes happen in code, not in config — any `ignoreErrors` entry, inline `@phpstan-ignore`, level below max, or dropped CS rule must be logged as an appended Plan Change Log entry naming the rule and the reason (a clean pass needs no entry); the full test suite is green after every code-touching step; commits follow §7; AGENTS.md and README change in the same unit they describe.

**Never:** nothing under `.github/` (no `ci.yml`, no workflows — D16); no hand-edits to `backend/symfony.lock` — it changes only when a flex recipe run forces it, and this unit adds no recipe-bearing packages; do not absorb the api-endpoints unit's in-flight uncommitted composer changes (`property-access`, `property-info`, `reflection-docblock` + recipe files) into this unit's commits or its branch; no runtime dependencies, no Redis; no analysis of framework-generated `bin/` and `config/` files.

## Code Map

- `backend/composer.json` -- gets `phpstan/phpstan` and `friendsofphp/php-cs-fixer` in require-dev plus `phpstan` / `cs-check` / `cs-fix` scripts; the vendor named volume needs `docker compose run --rm app composer install` afterwards (see compose.override.yaml comment)
- `backend/src/Repository/ItemRepository.php`, `backend/src/Contract/ItemRepositoryInterface.php` -- already shape-annotated (`list<array{...}>` PHPDoc); expect few max-level findings
- `backend/tests/Functional/FunctionalTestCase.php` -- `getContainer()->get('doctrine')->getConnection()` narrows to `object` under max; needs a real fix (assertion or annotation), the known likely offender
- `backend/.gitignore` -- add `.php-cs-fixer.cache`
- `AGENTS.md` -- §8 (drop CI), decision log (append D16), project status (retitle + tick the tooling checkbox)
- `README.md` -- Known limitations line "no CI yet" becomes the D16 trade-off; How to test gains the gate commands; flex-lock line
- `_bmad-output/initiative-file-system-app/architecture-file-system-app/architecture-file-system-app.md` -- Deferred section, bullet "CI workflow steps beyond AGENTS.md §8 — ticketed with the pipeline" is now false; amend it

## Tasks & Acceptance

**Execution:**
- [ ] `git` -- create `chore/phpstan-cs-fixer-gates` off `main` (start from a tree that carries no api-endpoints in-flight changes)
- [ ] `backend/composer.json` -- in the container: `composer require --dev phpstan/phpstan:^2 friendsofphp/php-cs-fixer:^3` (newest stable, lock pins exact); add the three scripts; `.gitignore` the fixer cache
- [ ] `backend/phpstan.neon` -- level `max`, paths `src`, `tests`, `migrations`; no baseline, no ignores
- [ ] `backend/src/**`, `backend/tests/**` -- fix every max-level error in code; run `composer cs-fix` once PHPStan is clean and let it reformat the analysed tree; commit as separate `fix` and `style` commits
- [ ] `backend/.php-cs-fixer.dist.php` -- ruleset `@Symfony` plus `declare_strict_types`, risky rules allowed, Finder on the same three paths
- [ ] `AGENTS.md` -- §8 retitled "Tooling": keep PHPStan ("highest practical level, zero unresolved issues") and PHP-CS-Fixer as the enforcement layer, run locally before every push; delete the GitHub Actions bullet; append D16 to the decision log (CI out of scope, supersedes the CI part of D7); retitle and tick the project-status entry
- [ ] `README.md` -- Known limitations: CI is deliberately out of scope (D16) and what replaces it, plus the flex-lock note: `backend/symfony.lock` intentionally references scaffolding files that were deleted, so leave it alone and never run `recipes:update` against it without pruning; How to test: the exact gate commands
- [ ] spine `architecture-file-system-app.md` -- amend the Deferred CI bullet: the pipeline is out of scope per D16 (2026-10-01); gates are local

**Acceptance Criteria:**
- Given the app container, when `composer phpstan` runs, then it reports zero errors at level `max`, and `phpstan.neon` contains no baseline and no `ignoreErrors`.
- Given the formatted tree, when `composer cs-check` runs, then it exits 0.
- Given any muted rule anywhere, then a Plan Change Log entry names the rule and the reason — absence of entries means nothing was muted.
- Given AGENTS.md after the unit, then §8 promises no CI, D16 is the newest decision, the status checkbox is ticked, and `git ls-files .github` returns nothing.
- Given README, then the CI trade-off and flex-lock note are in Known limitations, and every documented command is one that actually ran.
- Given the whole suite, when `php bin/phpunit` runs, then it is green with strict deprecations on — the type fixes and reformatting changed no behavior.

</frozen-after-approval>

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

Plan-level decisions (pre-approved, no open questions):
- **Level `max` is "highest practical"**, chosen on evidence: ~15 small classes, fully typed, PHPDoc array shapes already in place. If a max finding is genuine framework noise that cannot be fixed in code, the fallback is one narrow `ignoreErrors` with a Change Log entry — never a level drop, never a baseline.
- `bin/` and `config/` stay unanalysed and unformatted on purpose: `config/reference.php` is a generated dump and `bin/console` / `bin/phpunit` are skeleton shims; keeping them stock keeps recipe checks and upgrades clean.
- `phpstan/phpstan` and `friendsofphp/php-cs-fixer` carry no flex recipes, so `backend/symfony.lock` is untouched by this unit. The uncommitted symfony.lock diff now in the tree belongs to the api-endpoints unit's `property-info` recipe run — allowed by the same carve-out (a recipe run forced it).
- Composer scripts exist so README commands stay copy-pasteable and decoupled from `vendor/bin` paths.

Golden `phpstan.neon`:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
        - migrations
```

## Verification

**Commands:**
- `docker compose exec app composer phpstan` -- expected: "No errors" at level max
- `docker compose exec app composer cs-fix` then `composer cs-check` -- expected: check exits 0
- `docker compose exec app php bin/phpunit` -- expected: whole suite green, strict deprecations on
- `git ls-files .github` and `git diff main -- backend/symfony.lock` -- expected: no output (nothing under `.github/`; lock untouched by this unit)
- `grep -n "D16" AGENTS.md` -- expected: the new decision row, last in the log
