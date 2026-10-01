# File system app

A browser-based file system — folders and files in a tree, similar in spirit
to Dropbox's web interface — built as a PHP Developer interview take-home.

**Current state:** the backend is a Symfony 8.1 application running in debug
mode against PostgreSQL 18 under docker compose, with its domain data layer
and the full JSON API in place: the `item` table (folders and files in one
tree), the `ItemRepository` behind `src/Contract/ItemRepositoryInterface`,
the eight API endpoints over `App\Contract\ItemServiceInterface` with
validation and a uniform error envelope, and a real PHPUnit suite (unit +
functional, including HTTP-level tests) against a throwaway PostgreSQL
container. The React frontend is the next unit.

## What it does

- One `item` table holds the whole hierarchy (`folder` | `file` discriminator,
  adjacency list via `parent_id`, foreign key `ON DELETE CASCADE`), created by
  the hand-written migration `backend/migrations/Version20261001120000.php`.
- Every item stores its display `name` and a `normalized_name` (case-folded,
  NFC, deterministic `C` collation). Uniqueness among siblings, listing order,
  exact search and the typeahead prefix query all compare `normalized_name` —
  one rule everywhere, owned by `App\Service\NameNormalizer`.
- `App\Repository\ItemRepository` answers listing (folders-first, paginated),
  ancestor paths, exact-name search (whole tree or scoped to a folder's
  subtree, files only) and top-10 prefix suggestions — the recursive
  walks are single PostgreSQL `WITH RECURSIVE` statements, the prefix query
  rides a partial `text_pattern_ops` index.
- The JSON API (see [API overview](#api-overview)) serves the eight endpoints:
  thin controllers call `App\Service\ItemService`; input arrives through
  Validator-annotated write/query DTOs; responses are built from read-model
  DTOs — entities never reach JSON. Name rules live once in a custom
  `ItemName` constraint; a listener maps domain exceptions to the error
  envelope, and unexpected failures return a generic 500 that never leaks a
  stack trace (they are logged instead).
- The root is a pre-seeded, well-known row with a fixed id:
  `1a0ef9c6-0000-7000-8000-000000000000` (`App\Entity\Item::ROOT_ID`). A
  partial unique index guarantees it stays the only parent-less item; the root
  cannot be renamed or deleted.

Tests exercise all of this against real PostgreSQL — the database is never
mocked.

## Requirements

- Docker: Docker Desktop (Windows/macOS) or Docker Engine with the compose
  plugin.
- A checkout of this repository.

Nothing else is needed on the host: no PHP, no Composer, no Node. The
containers carry the authoritative runtimes; on the backend that is PHP 8.5,
and `backend/composer.json` pins `config.platform.php: 8.5.0` so dependency
resolution always targets it.

## How to run

```bash
docker compose up -d --build
```

Then create the schema and try the API:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose ps                                   # app and db healthy
docker compose exec app php bin/console --version   # Symfony v8.1.x (env: dev, debug: true)
curl -si -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d '{"name":"Docs"}'
# HTTP/1.1 201 Created ... Location: /api/items/<uuid> {"id":"...","type":"folder","name":"Docs","parentId":"1a0ef9c6-..."}
curl -s "http://localhost:8080/api/folders/1a0ef9c6-0000-7000-8000-000000000000/items"
# {"items":[...],"total":1,"limit":50,"offset":0}
```

The migration is idempotent: running it again is a no-op. The API answers on
`http://localhost:8080` (FrankenPHP serving the Symfony app in `APP_ENV=dev`,
`APP_DEBUG=1`). PostgreSQL data lives in the `db_data` named volume.

Everyday commands:

```bash
docker compose logs -f app          # tail application logs
docker compose down                 # stop (keeps the database volume)
docker compose down -v              # stop and wipe the database volume
```

Working on the backend: `compose.override.yaml` (auto-loaded) bind-mounts
`./backend` into the container for live editing and keeps `vendor/` in a
named volume seeded from the image. After pulling changes that touch
`composer.json`/`composer.lock`, refresh that volume:

```bash
docker compose run --rm app composer install
```

## How to test

The suite runs inside the app container against `db-test`, a second
PostgreSQL 18 under the compose `test` profile — a real database, never a
mock or an in-memory substitute.

```bash
# start the throwaway test database (tmpfs: nothing survives its removal)
docker compose -f compose.yaml -f compose.test.yaml --profile test up -d --wait db-test

# run everything: 31 unit tests (name grammar + validation rules) and
# 77 functional tests (repository against PostgreSQL, plus 60 HTTP-level API tests)
docker compose exec app php bin/phpunit

# stop the test database when done
docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test
```

The first test of a run applies the migrations to `db-test` automatically;
every test then works inside a transaction that is rolled back, so tests are
independent and order-safe and leave no data behind. Query-count assertions
pin the N+1 rule at both levels: repository calls and HTTP requests run a
constant number of statements (for example, all-scope search and suggestions
are exactly one query each; listing is the page query plus its count).

Host port 5433 mirrors `db-test` for psql debugging
(`psql postgresql://app:app@localhost:5433/app_test`).

## API overview

JSON only, camelCase fields, on `http://localhost:8080`. Exactly eight
routes:

| Method & path | Purpose | Success | Errors |
| --- | --- | --- | --- |
| `POST /api/folders` | Create folder; `parentId` omitted/null = root | `201` + `Location: /api/items/{id}` | 400, 404, 409 |
| `POST /api/files` | Create file; `parentId` required | `201` + `Location: /api/items/{id}` | 400, 404, 409 |
| `GET /api/folders/{id}/items?limit&offset` | List folder contents, folders first | `200` + page | 400, 404 |
| `GET /api/items/{id}` | Item detail incl. `parentPath` (root first, item excluded) | `200` | 404 |
| `PATCH /api/items/{id}` | Rename (`{"name": "..."}`) | `200` | 400, 404, 409 |
| `DELETE /api/items/{id}` | Delete file / cascade folder | `204`, empty body | 400, 404 |
| `GET /api/search?name=&scope=folder\|all&folderId=&limit&offset` | Exact search, files only | `200` + page | 400, 404 |
| `GET /api/suggestions?prefix=` | Top-10 files starting with the prefix | `200` | — |

Shapes: a listing page is `{"items": [...], "total": int, "limit": int,
"offset": int}`; a summary is `{"id", "type": "folder"|"file", "name",
"parentId"}`; a detail adds `"parentPath": [{"id", "name"}, ...]`. Search
returns details; suggestions return `{"items": [{"id", "name", "parentPath"}]}`
with no page metadata. `limit` defaults to 50 and caps at 100; `offset` ≥ 0;
an offset past the end is `200` with empty `items` and the true `total`.

Names are trimmed, must be non-blank, ≤ 255 characters, without `/` or `\`
or control characters, and cannot be `.` or `..`. Search input and prefixes
are normalized the same way, so `INVOICES` finds `invoices`. Sibling names
must be unique across both types (a folder and a file cannot share a name in
one folder); renaming to your own current name — including a case-only
change — succeeds. `scope=folder` requires `folderId`; `scope=all` ignores
it.

Every error is one envelope; `details` appears only on 400 and 409:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The request is invalid.",
    "details": [{"field": "name", "message": "Name must not be blank."}]
  }
}
```

Codes: `validation_failed` (400), `not_found` (404), `conflict` (409),
`internal_error` (500). Unknown JSON fields are ignored. The fixed error
codes and the shapes above are the contract the React frontend will mirror.

## Assumptions

- Single user, no authentication or authorization — scoped out by the task
  brief.
- A file is just its name; there is no content to upload or download.
- Root-level items carry the root's id as their `parentId` (their parent
  folder is the root); only the root itself has `parentId: null`.
- A file id given where a folder is addressed (listing, search scope) is a
  `404`, while a file given as a create-parent is a `400` — the addressed
  folder "does not exist", the referenced parent is the wrong type.
- Debug mode is a feature, not an accident: the brief requires the solution
  to run in debug mode, so the app container ships `APP_ENV=dev`,
  `APP_DEBUG=1`.
- `backend/.env` is never committed (`.env.example` mirrors it). Symfony's
  committed `.env.dev` carries a generated development-only `APP_SECRET`,
  which is the framework's own convention and holds nothing production-
  sensitive; `.env.test` similarly carries a fixed test secret.

## Known limitations & trade-offs

- No frontend and no CI yet — these are the next planned units, not omissions
  by accident.
- Status codes outside the envelope's vocabulary (unknown route 404, 405, 415)
  keep Symfony's default HTML error pages; the SPA never triggers them, so
  they stay outside the JSON contract on purpose.
- A non-integer `limit`/`offset` in the query string yields a 400 whose
  detail has an empty `field` — a Symfony query-denormalization artifact; the
  status, code and message are still correct.
- Concurrent duplicate-name writes resolve to exactly one `201` and one
  `409` via the database's unique index (the flush-time violation is
  translated to the envelope); there is no pre-check, so the check is
  race-free by construction but costs nothing on the happy path.
- **Testcontainers was swapped for a compose-managed test database (D14).**
  No stable, maintained Testcontainers client for PHP could be verified at
  scaffolding time; the pre-approved fallback from AGENTS.md §6 applies. The
  trade-off: the test database must be started with the compose command
  above instead of being spawned per test run.
- Doctrine's schema tool must never run against these databases: migrations
  own the schema. The ORM mapping intentionally does not declare the
  partial indexes, the `COLLATE "C"` column and `text_pattern_ops` — they
  are beyond what the mapping can express, which is why the migration is
  hand-written.
- The app image is a dev image: debug enabled, dev dependencies installed,
  `.env` conventions assumed. It is not a production image; a hardened
  multistage build is future work.
- `vendor/` inside the container lives in a named volume so a clean checkout
  still boots from one command; the trade-off is the manual `composer install`
  step after dependency changes (see above).
- PostgreSQL 18 moved its data layout: the volume mounts at
  `/var/lib/postgresql` (not `.../data`), per the image's own guidance.
- Host port 8080 (not 80) avoids privileged-port trouble on developer
  machines.

## What I'd improve with more time

- A production-shaped multistage image (no debug, no dev dependencies,
  optimized autoloader) next to the dev image.
- A dedicated health route so the app healthcheck can distinguish "framework
  answering" from "any HTTP response at all".
- Compose profiles so the future nginx/frontend container and any tooling
  containers start only when wanted.
- RFC 7807 (`application/problem+json`) error bodies if a non-browser
  consumer ever needs machine-readable error semantics beyond the fixed
  `code` vocabulary.
