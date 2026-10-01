---
title: 'Symfony skeleton + Docker dev environment'
type: 'feature'
ticket: ''
created: '2026-10-01'
status: 'built'
baseline_revision: '1fa95da'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/AGENTS.md', '{project-root}/_bmad-output/initiative-file-system-app/architecture-file-system-app/architecture-file-system-app.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The repo has empty `backend/`/`frontend/` folder skeletons and nothing runs; the brief requires the solution to build and run end-to-end in debug mode from the README alone.

**Approach:** Scaffold Symfony 8.1 (API-shaped) into `backend/`, pin the dependency platform to PHP 8.5 (container runtime), add Doctrine ORM + migrations + validator + serializer + monolog and the test pack, and bring up the dev environment with docker compose (FrankenPHP app container in `APP_ENV=dev` + PostgreSQL 18), verified by console, SQL, and HTTP checks. README gets its first real run instructions.

## Boundaries & Constraints

**Always:** AGENTS.md conventions govern; debug mode (`APP_ENV=dev`, `APP_DEBUG=1`) per the brief; `.env.example` mirrors `.env`; `.gitattributes` forces LF; work on `feature/symfony-skeleton`; no leftover generator scaffolding in the commit; spine AD-8 topology — this unit ships app+db, the nginx/frontend container lands with the frontend unit.

**Never:** no domain code (entity, migrations, endpoints, NameNormalizer — next unit); no frontend code; no CI/PHPStan/CS-Fixer (tooling unit); no Redis; never run `composer create-project` into non-empty `backend/` (scaffold in temp, then move); never commit `.env`, `vendor/`, `var/`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Environment up | `docker compose up -d --build` on a clean machine with Docker running | app + db containers healthy; `bin/console` responds in the app container; `dbal:run-sql "SELECT 1"` returns 1 | compose errors surface build logs; no silent skips |
| HTTP reachability | `GET http://localhost:8080/` | Any HTTP response (404 expected — no routes exist yet) | none needed at this unit |

</frozen-after-approval>

## Code Map

- `backend/**` -- only `.gitkeep` placeholders; keep placeholders in folders this unit doesn't fill (`src/Contract`, `src/Mapper`, `src/DTO/*`, `src/Exception`, `src/EventListener`, `tests/*`), delete ones the scaffold replaces
- `{project-root}/AGENTS.md` -- conventions authority: §3 layering/folders, §7 git, §8 Docker & LF rules, status checklist
- spine `AD-8` -- runtime topology and version pins (FrankenPHP 1.12, PHP 8.5, PostgreSQL 18, port strategy)
- Host facts (checked): PHP 8.4.5 + Composer 2.8.6 local → scaffold works, but `composer.json` must pin `config.platform.php 8.5.0` + `require php >=8.5` so resolution targets the container; Docker Desktop installed but not running — implementation must start it; Node 22 local (unused this unit)

## Tasks & Acceptance

**Execution:**
- [x] `git` -- create `feature/symfony-skeleton` off main
- [x] `backend/composer.json` + skeleton files -- `composer create-project symfony/skeleton:"8.1.*"` into temp, move into `backend/`, then require `doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle symfony/validator symfony/serializer symfony/monolog-bundle` and dev `symfony/test-pack`; set platform pin and php floor
- [x] `backend/.env` + `backend/.env.example` -- `APP_ENV=dev`, `APP_DEBUG=1`, `DATABASE_URL=postgresql://app:app@db:5432/app?serverVersion=18&charset=utf8`
- [x] `backend/Dockerfile` -- `FROM dunglas/frankenphp:1.12-php8.5` (fallback `-bookworm` variant tag if the short tag misses), composer install + copy app; document in-file that the container is the authoritative PHP 8.5 runtime
- [x] `compose.yaml` + `compose.override.yaml` -- `app` (build backend, `8080:80`, env from `.env`, `depends_on` db healthy) + `db` (`postgres:18`, `pg_isready` healthcheck, named volume); override volume-mounts `./backend` for live dev
- [x] `.gitattributes` + `.editorconfig` -- `* text=auto eol=lf`, basic editor settings per AGENTS.md §8
- [x] `README.md` -- replace stub: What it does (current state), Requirements, How to run (compose, verified steps), explicit "not yet" notes for frontend/tests per the honest-README rule
- [x] `AGENTS.md` -- tick "Symfony skeleton + Docker dev environment" in Project status

**Acceptance Criteria:**
- Given a clean checkout with Docker running, when `docker compose up -d --build`, then both containers go healthy and `bin/console --version` works in the app container.
- Given the environment up, when `GET http://localhost:8080/`, then an HTTP response arrives.
- Given `git status` after the unit, when inspecting tracked files, then no `.env`, `vendor/`, or `var/` content is staged.
- Given only the README, a newcomer starts the environment without asking questions.

## Implementation Notes

## Plan Change Log

- 2026-10-01 (execution): required `doctrine/orm` in addition to the listed packages — the doctrine recipe's `config/packages/doctrine.yaml` enables `doctrine.orm`, and `bin/console` fails to boot without the package (acceptance gate). Resolved to `^3.7`, matching the spine's stack pin.
- 2026-10-01 (execution): the `db` volume mounts at `/var/lib/postgresql` instead of `/var/lib/postgresql/data` — the `postgres:18` image requires the 18+ layout (docker-library/postgres#1259) and exits at startup otherwise.
- 2026-10-01 (execution): deleted the doctrine recipe's scaffolded `backend/compose.yaml`/`compose.override.yaml` (standalone postgres 16 dev db) — the root compose stack owns the runtime; keeping both would be dead scaffolding.
- 2026-10-01 (execution): app env reaches the container as `ENV` defaults in the Dockerfile mirroring `.env.example` (self-sufficient image on a clean checkout where `.env` does not exist yet); the mounted `.env` carries the same values.

## Review Triage Log

- 2026-10-01, lens quick, 1 pass (0 loopbacks): 4 findings → 3 patched, 1 rejected-low. Verdicts: high 1, medium 1, low 2, false 0, maybe-false 0.
- **high** (patched, `4d9402d`) — clean-checkout boot failure: Symfony Runtime boots Dotenv, which throws at `vendor/symfony/dotenv/Dotenv.php:668` when `backend/.env` is absent (gitignored, so every fresh clone hits it), and `APP_SECRET` was missing from the image ENV. Verified empirically in the running container by hiding `.env`. Fix: ENV gains dev-only `APP_SECRET`, `APP_RUNTIME_OPTIONS='{"disable_dotenv":true}'` (single-quoted — bare quotes are eaten by the ENV parser and fatal `json_decode`), and `DEFAULT_URI` (web entry 500s without it). Re-verified on a bind-mount-free standalone container: console boots with no `/app/.env`.
- **medium** (patched, `4d9402d`) — `app` service had no healthcheck, so the README's and AC's "both services healthy" was impossible. Fix: `curl` installed in the image, healthcheck `curl -s -o /dev/null http://localhost/` (any HTTP answer counts, no `-f`). Re-verified: `docker compose ps` shows app `(healthy)`.
- **low** (patched, `4d9402d`) — `backend/.editorconfig` was a generator duplicate of the root file with a dead compose indent rule (AGENTS.md §5). Deleted.
- **low** (rejected) — `backend/symfony.lock` records recipe files that were intentionally deleted (`AGENTS.md`, `CLAUDE.md`, `src/Controller/.gitignore`), so the lock disagrees with the tree and a future `recipes:update` could resurrect scaffolding. Real but only bites on a rare manual command, and the fix (hand-editing the flex lock) is riskier than the defect — rejected per the low-severity rule, evidence preserved here for the tooling unit.

## Design Notes

Port 8080 (not 80) — Windows dev machine, no admin-socket conflicts. `compose.override.yaml` is compose's auto-loaded dev layer: volume mount lives there, prod-shaped `compose.yaml` stays clean. Scaffold via temp dir because `create-project` refuses non-empty targets; move preserves the pre-made `src/` folder structure.

## Verification

**Commands:**
- `docker compose up -d --build` -- expected: both services running/healthy
- `docker compose exec app php bin/console --version` -- expected: Symfony 8.1.x string
- `docker compose exec app php bin/console dbal:run-sql "SELECT 1"` -- expected: 1
- `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` -- expected: any HTTP code (404 fine)

**Manual checks (if no CLI):**
- README steps match what was actually executed, command for command.
