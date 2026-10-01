---
title: 'API endpoints: routes, validation, error handling'
type: 'feature'
ticket: ''
created: '2026-10-01'
status: 'built'
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

**Problem:** The data layer exists (Item entity, ItemRepository behind its contract, NameNormalizer, migration with seeded root) but nothing speaks HTTP — no routes, no input validation, no error envelope.

**Approach:** Build the fixed AD-6 surface on top of what exists: thin controllers over `Contract/ItemServiceInterface` → `Service/ItemService` → the existing `ItemRepositoryInterface`; Validator-annotated write/query DTOs; pinned AD-9 read models assembled by `Mapper/ItemMapper`; one exception listener mapping domain exceptions to the AGENTS §4 envelope; functional HTTP tests plus unit validation tests.

## Boundaries & Constraints

**Always:** layering `Controller → Service → Repository` through `src/Contract/` interfaces; entities never reach JSON; error codes fixed to `validation_failed`/`not_found`/`conflict`/`internal_error`; camelCase JSON; AD-9 shapes verbatim; pagination `limit` default 50, cap 100, `offset` ≥ 0, metadata `total/limit/offset` on every collection; one transaction per write (AD-5); sibling uniqueness across types, self-excluded on rename (case-only rename succeeds); root rename/delete → `400 validation_failed`; search and suggestions return files only, all input normalized through `NameNormalizer`; POST 201 + `Location` → `GET /api/items/{id}`; DELETE 204 empty; tests run on real PostgreSQL in per-test transactions with query-count assertions on listing, detail, search, suggestions; branch `feature/api-endpoints`.

**Never:** no frontend work (`frontend/` is empty placeholders — later unit); no Redis, no CI/tooling (later units); no new error codes or routes beyond AD-6; no auth, no file content; nothing ever matches on `name` instead of `normalized_name`; no entity serialization, no mappers or Doctrine in controllers, no try/catch noise in controllers; no read-side filtering in PHP that SQL should do.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Create folder | `POST /api/folders` `{"name": " Projects "}`, no `parentId` | 201, trimmed ItemSummary, `Location: /api/items/{id}` | none |
| Duplicate sibling | create when a sibling of either type matches normalized name | — | 409 `conflict`, details on `name` (incl. translated `UniqueConstraintViolationException`) |
| Bad names | blank-after-trim, >255, containing `/` `\` control char, or exactly `.` / `..` | — | 400 `validation_failed`, details on `name` |
| Bad parent on create | `parentId` nonexistent → 404; `parentId` of a file → 400 detail on `parentId` | — | per PRD FR-1 / AGENTS §4 |
| Rename | `PATCH /api/items/{id}`; case-only `notes`→`Notes`, or own current name | 200 ItemSummary with new casing | unknown id 404; root 400; conflicting sibling 409 |
| Delete | `DELETE /api/items/{id}` on folder | 204, whole subtree gone (FK cascade, one transaction) | unknown id 404; root 400 |
| List folder | `GET /api/folders/{id}/items?limit&offset` | 200 Page<ItemSummary>, folders-first stable order; offset past end → empty items, true total | file id addressed → 404; `limit` 101/0/non-int, negative `offset` → 400 |
| Exact search | `GET /api/search?name=&scope=folder|all&folderId=` | 200 Page<ItemDetail> with root-first `parentPath`; `scope=all` ignores `folderId` | blank name → 400; `scope=folder` without `folderId` → 400; nonexistent/file `folderId` → 404 |
| Suggestions | `GET /api/suggestions?prefix=` | 200 `{"items": [...]}` ≤ 10, `{id, name, parentPath}` each | blank prefix → 200 empty items |
| Malformed body | invalid JSON, or unknown JSON fields | unknown fields ignored (lenient reads) | malformed JSON → 400 envelope |

</frozen-after-approval>

## Code Map

- `backend/src/Entity/Item.php`, `backend/src/Entity/ItemType.php` -- existing; `Item::ROOT_ID` drives the root guard
- `backend/src/Contract/ItemRepositoryInterface.php` -- existing; already covers every service need (find, findChildren, findAncestorPath, findByExactName, findSuggestionsByPrefix); unchanged
- `backend/src/Service/NameNormalizer.php` -- existing; single owner of normalization
- `backend/tests/Functional/FunctionalTestCase.php`, `QueryCounter.php` -- existing harness: per-test transaction + DBAL middleware query counting; API tests reuse both
- `backend/config/services.yaml` -- interface aliases live here (`ItemRepositoryInterface` precedent); test middleware already wired
- `backend/composer.lock` -- verified missing `symfony/property-access`, `symfony/property-info`, `phpdocumentor/reflection-docblock`; all three are required to denormalize typed DTOs (incl. nested arrays) via `#[MapRequestPayload]`/`#[MapQueryString]`
- `frontend/` -- placeholder dirs only; untouched here

## Tasks & Acceptance

**Execution:**
- [x] `git` -- create `feature/api-endpoints` off main
- [x] `backend/composer.json` -- in the app container, `composer require symfony/property-access symfony/property-info phpdocumentor/reflection-docblock` -- serializer needs them for object denormalization; keep pins consistent
- [x] `backend/src/DTO/Write/` -- `CreateFolder` (`parentId` ?Uuid, `name`), `CreateFile` (`parentId` Uuid, `name`), `RenameItem` (`name`), `PaginationQuery` (`limit` 1..100 default 50, `offset` ≥ 0 default 0), `SearchQuery` (`name`, `scope` enum folder|all, `folderId` ?Uuid, limit/offset), `SuggestionQuery` (`prefix`) -- declarative constraints; resolvers throw 400 on failure
- [x] `backend/src/Validator/ItemName.php` (+ validator), `SearchTerm.php` -- `ItemName` = whole grammar on the trimmed value with per-rule messages; `SearchTerm` = blank-after-trim only -- grammar single-sourced, unit-testable
- [x] `backend/src/DTO/Read/` -- `ItemSummary`, `ItemDetail` (+`parentPath`), `PathEntry`, `Page`, `Suggestion`, `SuggestionList` -- final classes repeating pinned AD-9 fields, no inheritance
- [x] `backend/src/Exception/` -- `ItemNotFoundException`, `ParentNotFoundException`, `RootChangeForbiddenException`, `InvalidParentTypeException`, `DuplicateItemNameException`
- [x] `backend/src/Contract/ItemServiceInterface.php` + `backend/src/Service/ItemService.php` -- createFolder, createFile, rename, delete, listChildren, getItem, search, suggestions; one transaction per write; flush-time `UniqueConstraintViolationException` → `DuplicateItemNameException`; returns mapper-built read models
- [x] `backend/src/Mapper/ItemMapper.php` -- write→entity (applies the trim), entity/repo-rows→read models; the only entity↔DTO translator
- [x] `backend/src/EventListener/ApiExceptionListener.php` -- `#[AsEventListener]` on kernel.exception: ValidationFailed + root-guard + invalid-parent-type → 400 with details; not-found → 404; duplicate → 409 with `name` details; any Throwable → generic 500 envelope, no stack traces even in debug
- [x] `backend/src/Controller/FolderController.php`, `FileController.php`, `ItemController.php`, `SearchController.php` -- the 8 AD-6 routes via attributes; DTO validation, one service call, `$this->json()`; POST sets Location, DELETE returns 204
- [x] `backend/config/services.yaml` -- alias `ItemServiceInterface` → `ItemService`
- [x] `backend/tests/Unit/WriteModelValidationTest.php` -- every constraint edge via `Validation::createValidatorBuilder()->enableAttributeMapping()`, no kernel
- [x] `backend/tests/Functional/Api/ApiTestCase.php` -- extends `FunctionalTestCase`; `client()` helper calling `createClient()` + `disableReboot()` (so the outer transaction survives requests) + JSON request/envelope assert helpers
- [x] `backend/tests/Functional/Api/` -- one class per endpoint group (create folder/file, list, detail, rename, delete, search, suggestions): every matrix row, Location headers, 204 bodies, pagination metadata, envelope shapes, and query-count asserts (reset counter → request → `count()`)
- [x] `README.md` + `AGENTS.md` -- API overview section; tick project status

**Acceptance Criteria:**
- Given db-test is up, when the full suite runs, then unit + functional API tests are green.
- Given the app container, when `debug:router` runs, then exactly the 8 AD-6 routes exist under `/api`.
- Given any error response, when its body is inspected, then it is the §4 envelope with `details` only on 400/409 and no stack trace on 500.
- Given list/detail/search/suggestions requests, when queries are counted, then counts are constant (no N+1).

## Implementation Notes

## Plan Change Log

- 2026-10-01 — `symfony/expression-language` added to the required packages beyond the planned three: the `When` constraint enforcing "folder scope requires folderId" refuses to run without it (verified by the unit suite failing before, passing after). Same commit as the other three.
- 2026-10-01 — `ItemService` drops the explicit `wrapInTransaction`: Doctrine ORM 3 already wraps each `flush()` in its own transaction (verified in the query log: BEGIN → SAVEPOINT → INSERT → RELEASE → COMMIT), so the wrapper only doubled the savepoints on every write. Each write is a single flush, so AD-5's one-transaction-per-write atomicity is preserved by the flush itself; the unique-violation → 409 translation stays.
- 2026-10-01 — Route `requirements` with `Uuid::VALID_PATTERN` dropped: the constant does not exist in symfony/uid 8.1, and the framework's `UidValueResolver` already rejects a malformed `{id}` with 404 — same outcome, no duplicate regex.
- 2026-10-01 — `GET /api/search` uses `#[MapQueryString(mapWhenEmpty: true)]` without a controller default: the default path injects an unvalidated DTO, so a request with no query string at all would have returned 200 instead of the pinned blank-name 400. With the flag, the empty query denormalizes and validates like any other.
- 2026-10-01 — `ApiExceptionListener` gained a monolog `error` log on the 500 branch: setting the response suppresses the framework's own error logging, which left unexpected failures invisible. They are still never leaked to the response.
- 2026-10-01 — Harness: `FunctionalTestCase` extends `WebTestCase` (instead of `KernelTestCase`) and routes its boot through an overridable `boot()` hook, because `createClient()` refuses to run after `bootKernel()` — `ApiTestCase` must own the boot for the outer transaction to survive requests. Repository tests are unaffected.

## Review Triage Log

## Design Notes

Plan-level decisions (pre-approved, no open questions):
- Parent-is-file on create is `400` with a `parentId` detail, while a file addressed as a folder (list, search scope) is `404` — the PRD FR-1 / AD-6 split, kept as pinned.
- The trim is applied by `ItemMapper` on write→entity; constraints validate the trimmed value so the grammar lives once in `ItemName`.
- The listener also catches uncaught Throwables → 500 envelope, so `APP_ENV=dev` cannot leak stack traces; unknown-route 404s keep the framework default.
- `scope=all` ignores `folderId`; sibling-uniqueness races resolve to one 201 + one 409 via the DB unique index and the flush translation.
- PHPStan and PHP-CS-Fixer are not installed yet (verified: not in require-dev); they become a gate with the tooling unit, not this one.

## Verification

**Commands:**
- `docker compose --profile test up -d db-test` -- expected: db-test healthy
- `docker compose exec app composer require symfony/property-access symfony/property-info phpdocumentor/reflection-docblock` -- expected: lock updated with pinned versions
- `docker compose exec app php bin/phpunit` -- expected: whole suite green, strict deprecations on
- `docker compose exec app php bin/console debug:router` -- expected: the 8 AD-6 routes
- `docker compose up -d` then `curl -si -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d "{\"name\":\"Docs\"}"` -- expected: 201 + Location; `DELETE /api/items/{ROOT_ID}` → 400 envelope
