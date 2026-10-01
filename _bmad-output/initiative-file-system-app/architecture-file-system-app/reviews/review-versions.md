# Review — Version & Reality Verification of the Architecture Spine

- **Reviewer role:** version-verification reviewer
- **Date:** 2026-10-01
- **Reviewed:** `architecture-file-system-app.md` (updated 2026-09-30), Stack table + named mechanisms (AD-1…AD-8)
- **Method:** direct fetches of authoritative sources (endoflife.date, packagist.org, postgresql.org current docs, symfony.com, vite.dev, frankenphp.dev, github.com releases). No assertions from training data.

## Verdict

The Stack table is accurate as of 2026-10-01: every pinned version exists, is the newest stable line for its technology, and the combinations are mutually compatible. Every architectural mechanism named is real and appropriate. Nothing is wrong; the findings below are minor footnotes and residuals to smoke-test at scaffold time.

## Stack verification — confirmed

| Claim in spine | Verified reality (source) | Status |
| --- | --- | --- |
| PHP 8.5 | Newest major; latest patch 8.5.11 (2026-09-24). Active support to 2027-12, security to 2029-12. 8.6 not yet released (expected ~Nov 2026). (endoflife.date/php) | Confirmed |
| Symfony 8.1 | Current stable; released 2026-05-29, latest patch 8.1.8 (2026-09-29). LTS is 7.4 (7.4.20), as the spine's framing assumes. Requires PHP >= 8.4 — satisfied by 8.5. (endoflife.date/symfony, symfony.com/doc/current/setup.html) | Confirmed |
| Doctrine ORM 3.7 | Latest stable 3.7.2 (2026-09-23); 3.x is the current major (4.0 exists only as `4.0.x-dev`). (packagist.org/packages/doctrine/orm) | Confirmed |
| PHPUnit 13.3 | Latest stable 13.3.6 (2026-09-29); 13.x is the current major. Requires PHP >= 8.4.1 — satisfied by 8.5. (packagist.org/packages/phpunit/phpunit) | Confirmed |
| PostgreSQL 18 | Newest GA major; latest patch 18.6 (2026-08-13), support to 2030-11. PostgreSQL 19 is **not** GA — only Beta 4 (2026-09-24) — so pinning 18 correctly honours the "no RC/beta" policy. (postgresql.org homepage, endoflife.date/postgresql) | Confirmed |
| Node.js 24 (LTS) | The only active LTS line (active support to 2026-10-20, then maintenance with security fixes to 2028-04). Node 26 is "Current / upcoming LTS" and flips to LTS in late October 2026. (endoflife.date/nodejs) | Confirmed (see finding 3) |
| React 19.3 | Latest 19.3.0 (2026-09-09); 19.x is the current major, no React 20 listed. (endoflife.date/react) | Confirmed |
| Vite 8 | Latest stable 8.3.2 (npm registry `latest` tag). Engines `node ^20.19.0 || >=22.12.0` — satisfied by Node 24. No Vite 9 on the `latest` tag. (registry.npmjs.org/vite/latest) | Confirmed |
| FrankenPHP, "current stable (symfony-docker default)" | Latest release v1.12.7 (2026-08); official Docker images ship PHP 8.2–8.5 variants, including PHP 8.5. dunglas/symfony-docker is a real, active template that runs Symfony on FrankenPHP+Caddy. (github.com/dunglas/frankenphp/releases, frankenphp.dev/docs/docker/, github.com/dunglas/symfony-docker) | Confirmed (see finding 4) |
| nginx, "current stable" | Stable branch 1.30 (1.30.5, 2026-09-15); mainline 1.31. Real and current. (endoflife.date/nginx) | Confirmed |

Cross-compatibility of the pinned set was checked where it matters: Symfony 8.1 needs PHP >= 8.4; PHPUnit 13.3 needs PHP >= 8.4.1; doctrine/doctrine-bundle 3.3.2 (latest, 2026-09-09) accepts Symfony `^6.4 || ^7.0 || ^8.0`, PHP `^8.4`, and DBAL `^4.0`; doctrine/dbal is at 4.5.0 (2026-09-24); Vite 8 accepts Node 24. No version conflict anywhere in the pinned set.

## Mechanisms verification — confirmed

| Mechanism (where used) | Verified reality (source) | Assessment |
| --- | --- | --- |
| PostgreSQL recursive CTEs (AD-2, AD-5: cascade delete, scoped search) | Real: `WITH RECURSIVE`, documented for "hierarchical or tree-structured data", with `SEARCH`/`CYCLE` options. (postgresql.org/docs/current/queries-with.html, §7.8) | Appropriate: one statement per subtree operation, no application-side recursion. |
| `text_pattern_ops` partial prefix index (AD-7) | Real: operator class for B-tree indexes on `text` that compares "strictly character by character", suitable for LIKE/regex pattern matching "when the database does not use the standard 'C' locale". (postgresql.org/docs/current/indexes-opclass.html) | Appropriate for `LIKE 'prefix%'` under any default collation; combining an opclass with a partial `WHERE` predicate is standard `CREATE INDEX` syntax. See finding 2 for an ORDER BY nuance. |
| Partial unique index `WHERE parent_id IS NULL` (AD-4) | Real: `CREATE UNIQUE INDEX ... WHERE ...` is documented. (postgresql.org/docs/current/indexes-partial.html, §11.8) | Appropriate: enforces the single NULL-parent root as a constraint; planner implication is irrelevant for constraint enforcement. |
| Doctrine discriminator / single-table mapping (AD-1) | Real: `SINGLE_TABLE` inheritance with `#[DiscriminatorColumn]` + `#[DiscriminatorMap]` maps a whole hierarchy to one table; docs note NOT NULL columns must live on the root and that STI needs no joins. (doctrine-project.org ORM docs, Inheritance Mapping) | Appropriate: Folder/File share the same columns here, so STI's usual weakness (sparse columns) does not apply. A plain `Item` entity with a `type` enum would also satisfy AD-1; either is consistent with the spine's wording. |
| FrankenPHP "debug mode" (AD-8) | FrankenPHP itself has no debug switch — development means running Symfony with `APP_ENV=dev`/`APP_DEBUG=1` under FrankenPHP; symfony-docker's dev flow is its default `compose.override.yaml` (volume mounts, Xdebug optional). Worker mode is the thing to keep off in dev; the spine already defers it. (frankenphp.dev, github.com/dunglas/symfony-docker) | Workable; wording only — see finding 4. |
| Vite dev proxy (AD-8: dev server proxying `/api`) | Real: `server.proxy` in the Vite config forwards path-prefixed requests to a target; documented with examples. (vite.dev/config/server-options.html) | Appropriate: gives one origin in dev, matching AD-8's no-CORS rule. |

## Findings

### 1. LOW — Symfony 8.1's support clock is short; 8.2 LTS arrives November 2026

endoflife.date/symfony shows Symfony 8.1 with both active and security support ending **2027-01-31**, and 8.2 (the next LTS, per the May/November cadence) expected around November 2026 — weeks after this review. This is the accepted consequence of the "newest stable wins" policy and is not relitigated here; a take-home reviewed before January needs nothing. The spine should simply be aware the pin ages into "unsupported standard release" within four months unless it takes the free 8.0→8.2-era compatibility window in November. No change required now; mention the planned hop in the README if the review period might extend past January.

### 2. LOW — AD-7's `text_pattern_ops` index will not satisfy `ORDER BY normalized_name` under a non-C collation

The pattern-opclass index orders by byte comparison, which differs from locale collation, and PostgreSQL will not use it to satisfy `ORDER BY normalized_name` when the database uses a non-C locale (the opclass docs note such indexes cannot serve ordinary comparisons). The suggestions query (`LIKE 'prefix%'`, `ORDER BY normalized_name, id`, `LIMIT 10`) will therefore likely take the index for the prefix filter plus a small sort (or incremental sort) of the matched rows. Correctness is unaffected and the sorted set is bounded by the prefix matches, so this stays cheap — but the "no sort" intuition implied by AD-7 is not guaranteed. Options if it ever matters: give `normalized_name` a `C`/deterministic ICU collation (which would also make `text_pattern_ops` unnecessary and unify ordering with uniqueness), or accept the tiny sort and verify with `EXPLAIN` at the 100k-row NFR scale.

### 3. LOW — Node 24's active-LTS window closes on 2026-10-20

Node 24 is the correct active LTS as of authoring (2026-09-30) and today; it moves to maintenance LTS (security fixes to 2028-04) on **2026-10-20**, when Node 26 becomes the active LTS. The pin was right at the moment it was made and nothing needs to change for the build to work; note only that "LTS" in the README will silently come to mean "maintenance LTS" in three weeks. Vite 8's engine range (`^20.19.0 || >=22.12.0`) covers both 24 and 26, so a later bump is trivial.

### 4. INFO — "FrankenPHP (Symfony, debug mode)" attributes debug to the wrong layer

FrankenPHP has no debug mode of its own. What AD-8 actually relies on is Symfony's `APP_ENV=dev` / `APP_DEBUG=1` environment inside the FrankenPHP container, with worker mode disabled in dev (symfony-docker achieves this through its default `compose.override.yaml` dev override). The design works as intended; the spine's phrasing just puts the word "debug" one layer too low. Harmless — worth one clarifying word at scaffold time so nobody goes hunting for a FrankenPHP debug flag. Relatedly, "symfony-docker default" is accurate for the FrankenPHP choice, but note the spine's topology (nginx in front, SPA statics + `/api` proxy) deliberately diverges from symfony-docker's single-Caddy default; that is a composition choice, not a version claim, and it is coherent.

### 5. INFO — Two adjacent compatibility facts were not independently confirmable from authoritative pages

- **Doctrine DBAL 4.5's PostgreSQL 18 platform support:** ORM 3.7 + DBAL 4.5 are current and the doctrine-bundle chain (3.3.2: Symfony `^8.0`, DBAL `^4.0`) fits the pinned stack, but neither packagist page states a supported-PostgreSQL matrix. PG 18 has been GA for over a year, so support is expected — this was simply not provable from the sources fetched.
- **Testcontainers PHP library with Docker Desktop on Windows:** named in the inherited invariants, not the Stack table; untested in this review. AGENTS.md §6 already carries the documented fallback (compose-managed throwaway test database), so the risk is bounded.

Both should be confirmed by the first scaffold commit rather than by further document review.

## Source list

- https://endoflife.date/php · /symfony · /postgresql · /nodejs · /react · /nginx
- https://packagist.org/packages/doctrine/orm · /doctrine/dbal · /doctrine/doctrine-bundle · /phpunit/phpunit
- https://registry.npmjs.org/vite/latest
- https://symfony.com/doc/current/setup.html
- https://www.postgresql.org/ · /docs/current/queries-with.html · /docs/current/indexes-opclass.html · /docs/current/indexes-partial.html
- https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/inheritance-mapping.html
- https://vite.dev/config/server-options.html
- https://frankenphp.dev/ · /docs/docker/ · https://github.com/dunglas/frankenphp/releases · https://github.com/dunglas/symfony-docker
