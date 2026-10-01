---
title: 'Domain model & migrations'
type: 'feature'
ticket: ''
created: '2026-10-01'
status: 'built'
baseline_revision: '7ce0426'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/AGENTS.md', '{project-root}/_bmad-output/initiative-file-system-app/architecture-file-system-app/architecture-file-system-app.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The running skeleton has no persistence layer — no entity, no schema, no queries — so nothing of the file-system domain exists yet.

**Approach:** Build the data layer per the spine: `Item` entity (single table, `folder|file` discriminator, UUID v7 ids), `NameNormalizer`, `ItemRepository` behind `Contract/ItemRepositoryInterface` with the recursive-CTE queries (subtree, ancestor path) and the prefix query, one hand-written migration creating `item` with all four indexes and the seeded root, and the first real tests: unit tests for normalization, functional tests against a real PostgreSQL test container.

## Boundaries & Constraints

**Always:** AGENTS.md conventions (strict types, final classes except the entity, no narrating comments, indexes live in the migration); spine AD-1/2/3/4/7 exactly; branch `feature/domain-model`; tests run against real PostgreSQL, never mocked; `COLLATE "C"` on `normalized_name`; root = fixed UUID constant seeded by migration, exposed as `Item::ROOT_ID`.

**Never:** no controllers, DTOs, mappers, services or exception mapping (API unit); no endpoints; no frontend; no CI; don't use Doctrine's schema-tool-generated migration for the unexpressible parts — hand-write the SQL (partial indexes, `text_pattern_ops`, collation); never load child collections through the entity (N+1 rule — no inverse side on the association).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Persist item | new Item(name, type, parent) | stored with normalized_name = case-folded NFC of name | none |
| Duplicate sibling | insert File `Notes` under Folder A when Folder `notes` exists | unique-constraint violation | constraint surfaces; translation to 409 is the API unit's job |
| List children | folder with mixed items, limit/offset | folders first, then files, each by normalized_name then id; stable across pages | offset past end → empty page + correct total |
| Ancestor path | item 3 levels deep | root-first array of {id, name}, item excluded | parent NULL (root) → empty path |
| Suggestions | prefix with 30 matches | ≤ 10, files only, ordered normalized_name then id | blank prefix → empty result |
| Delete folder | folder with nested subtree | subtree fully gone in one transaction (FK cascade) | none expected |

</frozen-after-approval>

## Code Map

- `backend/config/packages/doctrine.yaml` -- attribute mapping to `src/Entity` already active; `identity_generation_preferences` line is irrelevant to UUID ids; add uuid type mapping here
- `backend/phpunit.dist.xml` + `backend/tests/bootstrap.php` -- strict deprecation settings; test env `APP_ENV=test`; extend for the functional base class
- spine AD-1..AD-9 -- the contract this unit implements; **AD-5 gets amended** (see Design Notes)
- `backend/composer.json` -- add `symfony/uid` for UUID v7 generation
- Investigation fact: the Testcontainers PHP client could not be verified to exist at any stable packagist slug (two authoritative checks 404'd) — AGENTS.md §6's pre-authorized fallback applies: compose-managed throwaway test database, recorded as D14 + README trade-off

## Tasks & Acceptance

**Execution:**
- [ ] `git` -- create `feature/domain-model` off main
- [ ] `backend/src/Service/NameNormalizer.php` -- final class, one public `normalize(string): string` = case-fold + NFC; used by the entity on construct and rename -- single owner per AD-3
- [ ] `backend/src/Entity/Item.php` -- UUID v7 id assigned in constructor via `symfony/uid`, `type` PHP enum (`ItemType::Folder/File`), unidirectional `parent` ManyToOne (no inverse side), `name` + `normalizedName`, `rename()` re-normalizing; `Item::ROOT_ID` constant
- [ ] `backend/src/Contract/ItemRepositoryInterface.php` + `backend/src/Repository/ItemRepository.php` -- methods: `find`, `findChildren(folderId, limit, offset): page`, `findAncestorPath(id): array`, `findDescendantIds(id): array` (recursive CTE), `findByExactName(name, folderId|null, limit, offset): page`, `findSuggestionsByPrefix(prefix, 10)`; native SQL for the CTE/prefix queries
- [ ] `backend/migrations/Version*CreateItemTable.php` -- hand-written: `item` (id uuid pk, parent_id uuid null FK `ON DELETE CASCADE`, type, name, normalized_name `COLLATE "C"`); `UNIQUE(parent_id, normalized_name)`; partial unique `WHERE parent_id IS NULL`; `(parent_id, type DESC, normalized_name, id)`; `(normalized_name text_pattern_ops, id) WHERE type='file'`; root seed row (`Item::ROOT_ID`, name `Root`)
- [ ] `backend/compose.test.yaml` (root-level `compose.test.yaml` if it must live beside compose.yaml) -- `db-test` service: postgres:18 under a `test` profile, exposed on host 5433
- [ ] `backend/tests/Functional/FunctionalTestCase.php` -- boots kernel, points `TEST_DATABASE_URL` at db-test, runs migrations once per run, wraps each test in a transaction with rollback
- [ ] `backend/tests/Unit/NameNormalizerTest.php` + `backend/tests/Functional/ItemRepositoryTest.php` -- cover every matrix row
- [ ] `README.md` -- How to test section (real commands); `AGENTS.md` -- tick "Domain model & migrations", add D14 (test-db fallback) and note the AD-5 amendment
- [ ] spine `architecture-file-system-app.md` -- amend AD-5's Rule (same id): FK `ON DELETE CASCADE` inside the service transaction replaces the CTE id-collection delete; CTEs remain for subtree search and ancestor paths; log to its memlog

**Acceptance Criteria:**
- Given the test database up, when the functional suite runs, then every matrix row passes against real PostgreSQL.
- Given the root row, when queried, then it exists with `parent_id IS NULL`, id `Item::ROOT_ID`, and the partial unique index blocks a second NULL-parent row.
- Given `docker compose up` (dev), when `doctrine:migrations:migrate` runs, then the schema and root seed apply cleanly.
- Given any repository query, when executed, then no N+1 (queries are single statements; functional tests assert query counts on list/suggestions).

## Implementation Notes

- User directive at approval: if implementation reveals drift or error in the PRD or AGENTS.md, update those documents within this same unit (never let them fall out of sync with reality).

## Plan Change Log

- 2026-10-01 — Review fix: `findDescendantIds` removed from `ItemRepositoryInterface` and `ItemRepository`. Dead code since the AD-5 FK-cascade amendment — the cascade needs no collected ids and scoped search embeds its own scope CTE; the delete-cascade test now asserts emptiness with a direct count query.
- 2026-10-01 — Review fix: `findByExactName` falls back to a single COUNT query over the same scope+filter when the limited CTE returns no rows, so an offset past the end keeps the true total (pagination contract parity with `findChildren`). New functional test covers it.
- 2026-10-01 — Review fix: `NameNormalizer` switched from `mb_strtolower` to full case folding (`symfony/string` `UnicodeString::folded(false)` + NFC) so final-sigma variants of one name collide; `symfony/string` added as a pinned dependency.

## Review Triage Log

- 2026-10-02, lens quick, 1 pass (0 loopbacks): 4 findings → 4 patched. Verdicts: high 1, medium 3, false 0, maybe-false 0.
- **high** (patched, `a197790`) — `findByExactName` lost the true total when offset ≥ matches (`count(*) OVER ()` rides the limited CTE; empty page → total 0, breaking the pagination contract `findChildren` honors). Fix: empty-window fallback to a `SEARCH_COUNT_SQL` over the same scope+filter; functional test `find_by_exact_name_offset_past_end_returns_empty_page_with_full_total`.
- **medium** (patched, `a197790`) — `NameNormalizer` had no Contract interface and was consumed concretely by Entity/Repository; root cause was an AGENTS.md §3 ambiguity (pure transformation vs service). Fix per the user's doc-sync directive: §3 now states pure transformations (mappers, `NameNormalizer`) are stateless final classes without interfaces.
- **medium** (patched, `a197790`) — `findDescendantIds` had no production caller after AD-5's FK-cascade amendment (test-only helper on a published contract = dead code). Removed from interface and implementation; the cascade test asserts emptiness directly on the connection.
- **medium** (patched, `a197790`) — "case-folded" was implemented as Unicode lowercasing; final sigma diverged (ΟΔΌΣ vs οδός never collide). Fix: `symfony/string` `UnicodeString::folded(false)` (compat=false: NFKC_CF would over-fold and needs ext-intl) plus a ς→σ sweep closing `folded()`'s contextual-lowercase gap, NFC last; unit test `folds_final_sigma_variants_to_one_form` proves all three spellings collide.

## Design Notes

AD-5 amendment rationale: PostgreSQL's self-referential `ON DELETE CASCADE` performs the subtree delete atomically in one statement — the same guarantee the CTE-id-collection gave, with less code; the recursive CTEs stay where SQL truly owns recursion (subtree search, ancestor path). Test DB: `compose --profile test up -d db-test`; tests connect via `TEST_DATABASE_URL` (host port 5433). Root name "Root" is display sugar — it participates in no uniqueness scope (NULL parent). UUID type: `Symfony\Bridge\Doctrine\Types\UuidType` mapping, ids generated in PHP (`Uuid::v7()`), never by the database.

## Verification

**Commands:**
- `docker compose --profile test up -d db-test` -- expected: db-test healthy
- `docker compose exec app php bin/console doctrine:migrations:migrate -n` -- expected: migration applied
- `docker compose exec app php bin/phpunit` -- expected: all tests green, strict deprecations on
- `docker compose exec app php bin/console dbal:run-sql "SELECT ..."` root-seed sanity -- expected: 1 row

**Manual checks (if no CLI):**
- README test commands match what actually ran.
