# File system app

A browser-based file system — folders and files in a tree. 
A Symfony JSON API backed by PostgreSQL serves a
React + TypeScript SPA; docker compose boots the whole stack on one origin.

## What it does

- Create folders and subfolders, create files (a file is just its name).
- Browse a folder's contents — folders first, paginated — with breadcrumbs.
- Rename any folder or file in place.
- Delete a file, or delete a folder and its whole subtree (with an explicit
  cascade warning).
- Search files by exact name, case-insensitive (`INVOICES` finds
  `invoices`), scoped to a folder's subtree or across all files.
- Typeahead in the search box: the top 10 files whose name starts with the
  typed prefix, each with its location.

## Requirements

- Docker Desktop (Windows/macOS) or Docker Engine with the compose plugin.
- A checkout of this repository.

Nothing else is needed on the host — PHP, Composer, Node, nginx and
PostgreSQL all run in containers.

## How to run

```bash
docker compose up -d --build --wait
docker compose exec app php bin/console doctrine:migrations:migrate -n
```

`--wait` returns once every service has passed its healthcheck. The
migration creates the schema (and seeds the root folder) and is idempotent.
Then open **http://localhost:8080**.

Sanity check:

```bash
curl -si -X POST http://localhost:8080/api/folders -H "Content-Type: application/json" -d '{"name":"Docs"}'
# HTTP/1.1 201 Created ... Location: /api/items/{id}
```

Everyday commands:

```bash
docker compose logs -f app          # tail application logs
docker compose down                 # stop (keeps the database volume)
docker compose down -v              # stop and wipe the database volume
```

### Frontend development (optional)

```bash
docker compose run --rm node npm ci                       # first time
docker compose run --rm --publish 5173:5173 node npm run dev   # hot reload on :5173
docker compose run --rm node npm run build                # strict tsc + production build
```

The Vite dev server proxies `/api` into the compose network, so page and API
both answer on http://localhost:5173.

## How to test

The backend suite runs inside the app container against `db-test`, a second
PostgreSQL started by compose — a real database, never a mock.

```bash
# start the throwaway test database (tmpfs: nothing survives its removal)
docker compose -f compose.yaml -f compose.test.yaml --profile test up -d --wait db-test

# run everything: 32 tests, ~3 s
docker compose exec app php bin/phpunit

# stop the test database when done
docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test

# static analysis: PHPStan at level max, zero errors
docker compose exec app composer phpstan

# code style: check only (composer cs-fix applies the ruleset)
docker compose exec app composer cs-check

# frontend gate: the strict TypeScript build (there is no frontend test suite)
docker compose run --rm node npm run build
```

The suite is intentionally small: one happy-path and one bad-path test per
endpoint, one end-to-end flow test, and unit tests for validation and the
name normalizer. Tests run inside a transaction that is rolled back, so they
are independent and leave no data behind. There are no frontend tests — the
strict TypeScript build is the frontend gate.

### Performance benchmark

`app:benchmark` measures the two performance targets against a real
database: suggestion latency on a 100,000-file corpus where every file
shares one prefix (the worst case), and an atomic 10,101-item cascade
delete. Run it against the throwaway test database:

```bash
docker compose -f compose.yaml -f compose.test.yaml --profile test up -d --wait db-test

docker compose exec -e DATABASE_URL="postgresql://app:app@db-test:5432/app_test?serverVersion=18&charset=utf8" \
    app php bin/console app:benchmark

docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test
```

Last measured run (developer machine, Docker Desktop, PostgreSQL 18):

```text
Suggestions "report" x50:       p50 24.3 ms · p95 29.7 ms · max 61.1 ms   (target ~200 ms p95) PASS
Cascade delete 10,101 items x3: 196.5 / 187.1 / 179.4 ms                 (target 10 s)        PASS
```

The command exits non-zero if either target is missed and leaves the
database as it found it (only the seeded root folder). It also refuses
to run against a database whose name does not contain "test", so a
plain `app:benchmark` in the app container cannot wipe the dev data.

## API overview

JSON only, camelCase fields, on `http://localhost:8080`:

| Method & path | Purpose | Success | Errors |
| --- | --- | --- | --- |
| `POST /api/folders` | Create folder; `parentId` omitted = root | `201` + `Location` | 400, 404, 409 |
| `POST /api/files` | Create file; `parentId` required | `201` + `Location` | 400, 404, 409 |
| `GET /api/folders/{id}/items?limit&offset` | List folder contents, folders first | `200` + page | 400, 404 |
| `GET /api/items/{id}` | Item detail incl. `parentPath` | `200` | 404 |
| `GET /api/root-folder` | Root folder summary | `200` | 404 |
| `PATCH /api/items/{id}` | Rename (`{"name": "..."}`) | `200` | 400, 404, 409 |
| `DELETE /api/items/{id}` | Delete file / cascade folder | `204` | 400, 404 |
| `GET /api/search?name=&scope=folder\|all&folderId=` | Exact search, files only | `200` + page | 400, 404 |
| `GET /api/suggestions?prefix=` | Top-10 files starting with the prefix | `200` | 400 |

A listing page is `{"items": [...], "total", "limit", "offset"}`; a summary
is `{"id", "type": "folder"|"file", "name", "parentId"}`; a detail adds
`"parentPath"` (root first, item excluded). `limit` defaults to 50 (1–100),
`offset` ≥ 0. Names are trimmed, non-blank, ≤ 255 characters, without `/`
or `\`, and cannot be `.` or `..`. Sibling names must be unique across
folders and files.

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
`internal_error` (500).

## Assumptions

- Single user — no authentication or authorization, by decision.
- A file is just its name; there is no content to upload or download.
- The root folder is pre-seeded with a fixed id and cannot be renamed or
  deleted; the SPA resolves its id at startup via `GET /api/root-folder`.
- No visual design effort: the UI is deliberately plain utility CSS.

## Known limitations & trade-offs

- **Rename is the one extra feature.** The core set is create, search and
  delete; rename was added by explicit user decision (PRD FR-4), not scope
  creep.
- **No CI pipeline.** GitHub Actions is deliberately out of scope; the local
  gates above (test suite, PHPStan, PHP-CS-Fixer, frontend build) run before
  every push.
- **Small test suite.** 32 tests — one happy + one bad path per endpoint,
  the end-to-end flow, and unit tests for the documented validation rules;
  other edge cases (pagination boundaries, Unicode variants) are not
  pinned. No frontend tests either — the strict TypeScript build covers
  the frontend.
- **Names sort bytewise.** Listings order names by the normalized name
  under the `"C"` collation, so accented initials (Č, Š, Ž…) come after
  every ASCII letter. That collation is what lets the suggestions prefix
  scan use its index; locale-aware ordering would trade that away.
- **Dev image only.** The app container runs in debug mode with dev
  dependencies; a hardened production image is future work.
- **Migrations own the schema.** Doctrine's schema tool must never run
  against these databases — the partial indexes and collation live only in
  the hand-written migration.
- Search results are view state: they do not survive a page reload.

## What I'd improve with more time

- Move items between folders — the most natural next feature.
- Trash with restore instead of immediate deletes.
- A CI pipeline so the gates also run on every push.
- A production-shaped app image (no debug, no dev dependencies).
