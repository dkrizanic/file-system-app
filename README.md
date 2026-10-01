# File system app

A browser-based file system — folders and files in a tree, similar in spirit
to Dropbox's web interface — built as a PHP Developer interview take-home.

**Current state:** the backend is a Symfony 8.1 application running in debug
mode against PostgreSQL 18 under docker compose, now with its domain data
layer in place: the `item` table (folders and files in one tree), the
`ItemRepository` behind `src/Contract/ItemRepositoryInterface`, and a real
PHPUnit suite (unit + functional) against a throwaway PostgreSQL container.
There is no API and no frontend yet — those are the next units.

## What it does

The data layer boots and is tested; that is what this unit delivers:

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
- The root is a pre-seeded, well-known row with a fixed id:
  `1a0ef9c6-0000-7000-8000-000000000000` (`App\Entity\Item::ROOT_ID`). A
  partial unique index guarantees it stays the only parent-less item.

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

Then create the schema and check it is alive:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose ps                                   # app and db healthy
docker compose exec app php bin/console --version   # Symfony v8.1.x (env: dev, debug: true)
docker compose exec app php bin/console dbal:run-sql "SELECT id, parent_id, name FROM item"
curl -i http://localhost:8080/                      # 404 — no routes yet, expected
```

The `dbal:run-sql` line should show the seeded root row
(`1a0ef9c6-…-000000000000`, no parent). The migration is idempotent: running
it again is a no-op. The API answers on `http://localhost:8080` (FrankenPHP
serving the Symfony app in `APP_ENV=dev`, `APP_DEBUG=1`). PostgreSQL data
lives in the `db_data` named volume.

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

# run everything: 7 unit tests (NameNormalizer) + 17 functional tests (repository)
docker compose exec app php bin/phpunit

# stop the test database when done
docker compose -f compose.yaml -f compose.test.yaml --profile test stop db-test
```

The first test of a run applies the migrations to `db-test` automatically;
every test then works inside a transaction that is rolled back, so tests are
independent and order-safe and leave no data behind. Query-count assertions
pin the N+1 rule: children listing is exactly two statements, suggestions,
ancestor path and search exactly one each.

Host port 5433 mirrors `db-test` for psql debugging
(`psql postgresql://app:app@localhost:5433/app_test`).

## API overview

Empty for now. The endpoints (folders, files, listing, search, typeahead
suggestions, delete) land in the next units; when they do, this section
documents them.

## Assumptions

- Single user, no authentication or authorization — scoped out by the task
  brief.
- Debug mode is a feature, not an accident: the brief requires the solution
  to run in debug mode, so the app container ships `APP_ENV=dev`,
  `APP_DEBUG=1`.
- `backend/.env` is never committed (`.env.example` mirrors it). Symfony's
  committed `.env.dev` carries a generated development-only `APP_SECRET`,
  which is the framework's own convention and holds nothing production-
  sensitive; `.env.test` similarly carries a fixed test secret.

## Known limitations & trade-offs

- No API endpoints, no frontend, no CI yet — these are the next planned
  units, not omissions by accident.
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
