# File system app

A browser-based file system — folders and files in a tree, similar in spirit
to Dropbox's web interface — built as a PHP Developer interview take-home.

**Status:** the PRD's feature set is delivered end-to-end. A Symfony 8.1
backend (debug mode, FrankenPHP, PostgreSQL 18) serves a JSON API; a React 19
+ TypeScript SPA browses and edits the tree through it on one origin; docker
compose boots the whole stack with one command; the PHPUnit suite (108 tests)
runs against a real second PostgreSQL and is green, as are PHPStan (level
max) and PHP-CS-Fixer. There is no CI pipeline (D16) — the quality gates run
locally before every push, and this README was written by running every
command and request it shows. Known trade-offs are listed under [Known
limitations & trade-offs](#known-limitations--trade-offs).

## What it does

For the user:

- Create folders and subfolders, create files (a file is just its name).
- Browse: a folder's contents, folders first, paginated; breadcrumbs from the
  item's ancestor path.
- Rename any folder or file in place. Rename is the single feature beyond the
  task brief — sanctioned by explicit user decision and recorded in the PRD
  (FR-4); see [Known limitations](#known-limitations--trade-offs).
- Delete a file, or delete a folder and its whole subtree — with an explicit
  cascade warning before a folder delete.
- Search files by exact name, scoped to a folder's subtree or across all
  files; matching is case-insensitive (`INVOICES` finds `invoices`).
- Typeahead in the search box: the top 10 files whose name starts with the
  typed prefix, each with its location; picking one opens its folder.

How it is built:

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
- A React 19 + TypeScript (strict) SPA built with Vite. Routing has zero
  dependencies: the open folder id lives in the URL hash — `#/` is the root,
  `#/folders/{id}` deep-links anywhere. Search results are view state.
- All HTTP goes through one typed API layer (`frontend/src/api/client.ts`),
  one function per endpoint, which parses the backend's error envelope into a
  single `ApiError` carrying `code`, `message` and the 400/409 field
  `details`. Forms render those details inline under the `name` field;
  everything else surfaces in a dismissible banner. Nothing is swallowed.
- An `ErrorBoundary` (the one class component) wraps the whole page, the
  search box and the form area — a render crash never blanks the screen.
- The name grammar is server-owned: forms use native `required` /
  `maxLength=255` only, send raw input, and let the API's validation decide.
- nginx serves the built SPA and proxies `/api` to the Symfony app, so the
  browser sees one origin.

## Requirements

- Docker: Docker Desktop (Windows/macOS) or Docker Engine with the compose
  plugin.
- A checkout of this repository.

Nothing else is needed on the host: no PHP, no Composer, no Node. The
containers carry the authoritative runtimes: PHP 8.5 (`backend/composer.json`
pins `config.platform.php: 8.5.0`), Node 24 and nginx 1.30.

## How to run

```bash
docker compose up -d --build --wait
```

`--wait` holds the command until every service has passed its healthcheck,
so the stack is genuinely serving when the prompt comes back. Then create
the schema and check the stack is alive:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose ps                                   # app, db and frontend healthy
```

Open **http://localhost:8080** — nginx serves the SPA and proxies `/api` to
the app on one origin. Sanity checks, with real output:

```bash
docker compose exec app php bin/console --version
# Symfony v8.1.8 (env: dev, debug: true)

docker compose exec app php bin/console dbal:run-sql "SELECT id, parent_id, name FROM item"
# one row: 1a0ef9c6-0000-7000-8000-000000000000 | (null) | Root

curl -si -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d '{"name":"Docs"}'
# HTTP/1.1 201 Created ... Location: /api/items/01a0f892-c6f8-7345-a972-2fd61cc98bfb
# {"id":"01a0f892-c6f8-7345-a972-2fd61cc98bfb","type":"folder","name":"Docs","parentId":"1a0ef9c6-..."}
```

The migration is idempotent: running it again is a no-op. The app container
runs FrankenPHP serving Symfony in `APP_ENV=dev`, `APP_DEBUG=1`; PostgreSQL
data lives in the `db_data` named volume. The API answers on
`http://localhost:8080` through nginx — host port 8080 belongs to nginx (the
entry point); the dev override additionally exposes the app itself on 8081
for direct reachability. `vendor/` and `node_modules/` live in named volumes;
a fresh volume is seeded from the image's own `composer install` output on
first boot, so a clean checkout really does come up from the one command
above (verified by deleting the volume and booting).

Everyday commands:

```bash
docker compose logs -f app          # tail application logs
docker compose down                 # stop (keeps the database volume)
docker compose down -v              # stop and wipe the database volume
```

### Frontend development

The `node` service (compose profile `tools`, used only through `run`) gives a
container-based dev workflow against the bind-mounted `./frontend`:

```bash
# first time, and after pulling changes that touch package.json/lock
docker compose run --rm node npm ci

# dev server with hot reload on http://localhost:5173
docker compose run --rm --publish 5173:5173 node npm run dev

# production build (strict tsc + vite build)
docker compose run --rm node npm run build
```

The Vite dev server proxies `/api` into the compose network
(`API_PROXY_TARGET=http://app:80` set by the override) — the page and the API
both answer on `http://localhost:5173`. `node_modules/` lives in a named
volume seeded by `npm ci`, so the Windows/OneDrive host filesystem never sees
it. `frontend/vite.config.ts` defaults the proxy to `http://localhost:8081`,
so running Vite on a host against the published dev port works too.

## How to test

The backend suite runs inside the app container against `db-test`, a second
PostgreSQL 18 under the compose `test` profile — a real database, never a mock
or an in-memory substitute.

```bash
# start the throwaway test database (tmpfs: nothing survives its removal)
docker compose -f compose.yaml -f compose.test.yaml --profile test up -d --wait db-test

# run everything: 108 tests, ~15 s
#   31 unit tests   — name grammar (7) + write-model validation rules (24)
#   77 functional   — repository against PostgreSQL (17) + HTTP-level API tests (60)
docker compose exec app php bin/phpunit

# stop the test database when done
docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test

# static analysis: PHPStan at level max, zero errors, over src/tests/migrations
docker compose exec app composer phpstan

# code style: check only (composer cs-fix applies the ruleset)
docker compose exec app composer cs-check
```

The first test of a run applies the migrations to `db-test` automatically;
every test then works inside a transaction that is rolled back, so tests are
independent and order-safe and leave no data behind. Query-count assertions
pin the N+1 rule at both levels — repository calls and HTTP requests run a
constant number of statements (all-scope search and suggestions are exactly
one query each; listing a constant three).

There are no frontend tests (D6): the testing effort goes to the backend per
the project decision. The frontend gate is the strict `tsc -b && vite build`
above; interactive flows have been exercised by hand during development only.

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

One worked example per route (ids abbreviated; every response below is real
output from the running stack):

```bash
curl -si -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d '{"name":"Docs"}'
# 201 Created · Location: /api/items/{folderId}
# {"id":"{folderId}","type":"folder","name":"Docs","parentId":"1a0ef9c6-..."}

curl -si -X POST http://localhost:8080/api/files -H "Content-Type: application/json" \
  -d '{"parentId":"{folderId}","name":"invoice-2026.txt"}'
# 201 Created · Location: /api/items/{fileId}
# {"id":"{fileId}","type":"file","name":"invoice-2026.txt","parentId":"{folderId}"}

curl -s "http://localhost:8080/api/folders/{rootId}/items"
# {"items":[{"id":"{folderId}","type":"folder","name":"Docs","parentId":"1a0ef9c6-..."}],
#  "total":1,"limit":50,"offset":0}

curl -s "http://localhost:8080/api/items/{fileId}"
# {"id":"{fileId}","type":"file","name":"invoice-2026.txt","parentId":"{folderId}",
#  "parentPath":[{"id":"1a0ef9c6-...","name":"Root"},{"id":"{folderId}","name":"Docs"}]}

curl -si -X PATCH http://localhost:8080/api/items/{fileId} -H "Content-Type: application/json" \
  -d '{"name":"Invoice 2026.txt"}'
# 200 OK
# {"id":"{fileId}","type":"file","name":"Invoice 2026.txt","parentId":"{folderId}"}

curl -si -X DELETE http://localhost:8080/api/items/{fileId}
# 204 No Content (empty body; repeating it answers 404)

curl -s "http://localhost:8080/api/search?name=INVOICE%202026.TXT&scope=all"
# {"items":[{"id":"{fileId}","type":"file","name":"Invoice 2026.txt","parentId":"{folderId}",
#  "parentPath":[...]}],"total":1,"limit":50,"offset":0}

curl -s "http://localhost:8080/api/suggestions?prefix=invo"
# {"items":[{"id":"{fileId}","name":"Invoice 2026.txt",
#  "parentPath":[{"id":"1a0ef9c6-...","name":"Root"},{"id":"{folderId}","name":"Docs"}]}]}
```

Shapes: a listing page is `{"items": [...], "total": int, "limit": int,
"offset": int}`; a summary is `{"id", "type": "folder"|"file", "name",
"parentId"}`; a detail adds `"parentPath": [{"id", "name"}, ...]`. Search
returns details; suggestions return `{"items": [{"id", "name", "parentPath"}]}`
with no page metadata. `limit` defaults to 50 and is validated to 1–100
(values outside the range, or non-integers, are a `400`); `offset` ≥ 0; an
offset past the end is `200` with empty `items` and the true `total`.

Names are trimmed, must be non-blank, ≤ 255 characters, without `/` or `\`
or control characters, and cannot be `.` or `..`. Search input and prefixes
are normalized the same way, so `INVOICES` finds `invoices`. Sibling names
must be unique across both types (a folder and a file cannot share a name in
one folder); renaming to your own current name — including a case-only
change — succeeds. `scope=folder` requires `folderId`; `scope=all` ignores
it. A file id given where a folder is addressed (listing, search scope) is a
`404`, while a file given as a create-parent is a `400`.

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

```bash
curl -s -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d '{"name":"Docs"}'   # again, same parent
# 409 Conflict
# {"error":{"code":"conflict","message":"An item with this name already exists in the parent folder.",
#  "details":[{"field":"name","message":"An item with this name already exists in the parent folder."}]}}
```

Codes: `validation_failed` (400), `not_found` (404), `conflict` (409),
`internal_error` (500). Unknown JSON fields are ignored. The fixed error
codes and the shapes above are the contract the React frontend mirrors
(`frontend/src/types`).

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
- No router, state-management or validation-library dependency on the
  frontend: the open folder id lives in the URL hash, forms use native
  validation attributes (`required`, `maxLength=255`) mirroring the backend's
  write models, and the name grammar stays server-owned. Search results are
  view state, not URL.
- No visual design effort: the brief scopes the frontend down to
  functionality, so the styles are plain utility CSS.

## Known limitations & trade-offs

- **One beyond-brief feature: rename.** The task brief lists create, search
  and delete only. Rename was added by explicit user decision and is
  recorded in the PRD as FR-4 — it is a sanctioned addition, not scope
  creep. Everything else stays inside the brief.
- **No CI pipeline (D16).** GitHub Actions is deliberately out of scope — a
  user decision (2026-10-01), not an omission by accident: nothing lives
  under `.github/`, so no machine re-runs the checks on the remote. What
  replaces it: the local gates above (`composer phpstan`, `composer
  cs-check`, the test suite) run before every push. The trade-off is that a
  skipped gate is caught by no machine — the enforcement is convention, not
  automation.
- `backend/symfony.lock` intentionally references scaffolding files that were
  deleted from the repo after scaffolding. Leave the file alone: it changes
  only when a Composer/Flex recipe run forces it, and never run `composer
  recipes:update` against it without pruning those references first.
- Status codes outside the envelope's vocabulary (unknown route 404, 405, 415)
  keep Symfony's default HTML error pages; the SPA never triggers them, so
  they stay outside the JSON contract on purpose.
- Concurrent duplicate-name writes resolve to exactly one `201` and one
  `409` via the database's unique index (the flush-time violation is
  translated to the envelope); there is no pre-check, so the check is
  race-free by construction but costs nothing on the happy path.
- **Performance is designed in but not measured.** The architecture's NFR
  targets — suggestions within ~200 ms p95 at 100,000 files (NFR-2) and a
  10,000-item cascade delete within 10 seconds (NFR-1) — were to be verified
  once at delivery by a seeded benchmark script. That script was deferred and
  never built. What pins performance instead: the targeted indexes in the
  hand-written migration and the constant query-count assertions in the test
  suite — the structural half of the targets, without a latency number.
- **Testcontainers was swapped for a compose-managed test database (D14).**
  No stable, maintained Testcontainers client for PHP could be verified at
  scaffolding time; the pre-approved fallback from AGENTS.md §6 applies. The
  trade-off: the test database must be started with the compose command
  above instead of being spawned per test run.
- **No frontend tests (D6).** The SPA is covered by the strict TypeScript
  build gate only; the interactive flows are verified by hand.
- **No ESLint/Prettier yet** — strict `tsc` covers the most valuable part in
  the meantime.
- Doctrine's schema tool must never run against these databases: migrations
  own the schema. The ORM mapping intentionally does not declare the
  partial indexes, the `COLLATE "C"` column and `text_pattern_ops` — they
  are beyond what the mapping can express, which is why the migration is
  hand-written.
- The app image is a dev image: debug enabled, dev dependencies installed,
  `.env` conventions assumed. It is not a production image; a hardened
  multistage build is future work.
- `vendor/` and `node_modules/` live in named volumes (seeded automatically
  on first boot — see How to run). The trade-off: after a change to
  `composer.json`/`package.json` on a running stack, refresh by hand with
  `docker compose run --rm app composer install` and `docker compose run
  --rm node npm ci`.
- PostgreSQL 18 moved its data layout: the volume mounts at
  `/var/lib/postgresql` (not `.../data`), per the image's own guidance.
- Host port 8080 (nginx, the entry point) and 8081 (dev-only app) avoid
  privileged-port trouble on developer machines.
- Search results do not survive a page reload: they are view state while the
  open folder is a URL hash. Refresh lands you back in the folder view.

## What I'd improve with more time

- Move items between folders — the most natural next feature (the PRD lists
  it first among deferred quality-of-life candidates).
- Trash with restore instead of immediate, unrecoverable deletes.
- A CI pipeline on GitHub Actions so the test suite, PHPStan and
  PHP-CS-Fixer run on the remote on every push (superseded for now by D16).
- A production-shaped multistage app image (no debug, no dev dependencies,
  optimized autoloader) next to the dev image.
- FrankenPHP worker mode and opcache tuning once the app leaves debug mode.
- Build the seeded benchmark script the architecture deferred: a 100,000-item
  corpus with a worst-case shared prefix, timing suggestions (200 ms p95) and
  a 10,000-item cascade delete (10 s) against the NFR targets.
- Revisit Redis (D2) if a concrete need appears — caching or rate limiting
  would be the first candidates.
- A Symfony 8.2 LTS hop when it is released (the stack pins 8.1 today).
- A dedicated health route so the app healthcheck can distinguish "framework
  answering" from "any HTTP response at all".
- Compose profiles so the tooling containers start only when wanted (the
  `node` service already has one).
- RFC 7807 (`application/problem+json`) error bodies if a non-browser
  consumer ever needs machine-readable error semantics beyond the fixed
  `code` vocabulary.
- Keyboard navigation and ARIA roles for the suggestions dropdown, and the
  search query in the URL so results can be shared and refreshed.
