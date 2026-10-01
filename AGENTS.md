# AGENTS.md — File System App

Working agreement for every AI agent (and human) touching this repo.
**Read this entire file before starting any task.** If a task conflicts with something
here, stop and ask — do not improvise.

---

## 1. Project context

- **What:** "File system app" — a take-home task for a PHP Developer interview.
- **Goal:** a small, complete, honest, performant application that runs end-to-end
  from the README and demonstrates craft: clean code, correct HTTP, validation,
  error handling, and meaningful tests.
- **Calibration:** this is an interview task and it has to be excellent in every
  way — but excellence here means **simple, performant, correct and complete**, not
  enterprise-heavy. Every abstraction must earn its place. Performance is designed
  in from the start (sensible indexes, no N+1 queries, pagination), never bolted on
  afterwards. Boring, working, fast solutions beat clever ones. When in doubt,
  choose the simpler approach — and record the trade-off in the README.

The exact task statement is not in the repo yet. If the official task text arrives,
paste it verbatim into §12. Until then, confirm feature scope with the user before
building anything domain-specific.

## 2. Evaluation criteria (from the task brief — all must be satisfied)

1. Solution runs end-to-end using only the instructions in the README.
2. Clean, readable PHP with clear naming, a sensible structure, and a sensible
   separation between API, data and UI layers.
3. A tidy repo where everything present is intentional — no dead code, no leftover
   scaffolding.
4. Appropriate use of HTTP methods and status codes.
5. Input validation and error handling.
6. Tests covering the main functionality: functional tests for the API and important
   flows; unit tests for validation and small pieces.
7. A README that clearly explains how to run and test the application.
8. An honest README: anything incomplete or traded away is stated up front, plus
   assumptions made and what would be improved given more time.

We deliberately aim high — clean, performant, well-tested work that survives senior
review. What stays out: speculative architecture (microservices, CQRS, event
sourcing), gold-plating, and abstraction without a second concrete use.
Over-engineering is as much an interview failure as sloppiness.

## 3. Architecture & stack (decided)

| Layer      | Choice |
|------------|--------|
| Backend    | Symfony, MVC, JSON API only — PHP 8.3 is the floor, newest stable wins, then pinned |
| Data       | PostgreSQL 16+ via Doctrine ORM |
| Cache      | Redis — **only when a concrete need appears** (see Decision log) |
| Frontend   | React + TypeScript SPA (Vite) on Node LTS, separate from the API |
| Infra      | Docker + docker compose for a one-command run |
| Tests      | PHPUnit with a real PostgreSQL via Testcontainers — backend only; no frontend tests (D6) |

**Versions policy:** use the newest **stable** versions at scaffolding time and pin
them (composer.json, package.json, Dockerfile, compose). No RC/beta versions.
Upgrades afterwards are deliberate, documented commits.

### Backend layering

`Controller → Service → Repository` — never skip over a layer, never call upwards.

- **Controllers** do exactly three things: validate input, call one service
  interface, return the read model as JSON. No business logic, no mapping, no
  Doctrine, no branching beyond the happy path.
- **Services** hold all business logic, accept write models and return read
  models, and throw domain exceptions (e.g. `DirectoryNotFoundException`,
  `DuplicateNameException`).
- **Repositories** are the only code that talks to the database.
- **Interfaces define the contracts** and live in `src/Contract/`: every service
  and repository gets one (e.g. `DirectoryServiceInterface`), while
  implementations keep natural names in their layer folders (e.g.
  `Service/DirectoryService`, `Repository/DoctrineDirectoryRepository`).
  Controllers depend on service interfaces, services on repository interfaces.
  Controllers, DTOs and entities stay concrete.
- Input arrives through **write-model DTOs** (`DTO/Write`) annotated with Symfony
  Validator constraints; responses are built from **read-model DTOs**
  (`DTO/Read`). Entities never appear in JSON directly. This is the validation
  layer — there is no separate one; rules live declaratively on the write models,
  and duplicating them elsewhere is a bug.
- **Mappers** (`src/Mapper/`) are the only place entity ↔ model translation
  happens: write model → entity, entity → read model. Stateless final classes
  without interfaces — pure transformations, an implementation detail of
  services. Controllers never touch mappers or entities.
- A central exception listener maps domain exceptions to HTTP status codes.
  No try/catch noise in controllers.

### Frontend rules

- TypeScript in strict mode. Function components + hooks only — the single
  exception is the **Error Boundary**, which React requires to be a class component.
- A top-level Error Boundary plus boundaries around risky subtrees; the user never
  sees a blank screen.
- Layers: `src/api` (typed HTTP client, no components) · `src/components`
  (presentational) · `src/hooks` · `src/pages`. No fetch/axios calls inside
  components — only through the API layer. `src/types` mirrors the backend's
  read/write models.
- No oversized pages or components. Pages are thin orchestrators that compose
  smaller pieces; any component that grows past roughly 100 lines or clearly mixes
  concerns gets split.
- Build with reuse in mind: shared UI (buttons, modals, lists, empty states, form
  fields) lives in `src/components` and is extracted at the second occurrence —
  never copy-pasted. Repeated logic becomes a custom hook.
- API errors are handled explicitly and surfaced with meaningful messages; never
  swallowed. No `any`, no non-null assertion chains, early returns over nesting.
- Form validation uses browser-native attributes first; the backend's write
  models are the single source of truth. The API layer maps the 400 error
  envelope's `details` to per-field form errors. No client-side validation
  library (Yup, Zod, …) unless a form genuinely outgrows native validation —
  then prefer Zod with react-hook-form, recorded in the Decision log.

## 4. API conventions

| Route          | Success        | Errors          |
|---------------|----------------|-----------------|
| GET collection | 200            | —               |
| GET item       | 200            | 404             |
| POST           | 201 + Location | 400, 409        |
| PUT / PATCH    | 200            | 400, 404, 409   |
| DELETE         | 204            | 404             |

- Validation failure → `400` with the error envelope below; duplicates/conflicts →
  `409`; unexpected failure → generic `500` that never leaks stack traces.
- Collection endpoints are paginated (`limit`/`offset` with sane defaults and a cap)
  and include pagination metadata (`total`, `limit`, `offset`). No endpoint ever
  returns an unbounded result set.
- JSON only. Field names are camelCase (D3).
- Error envelope, used everywhere, no exceptions:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The request body is invalid.",
    "details": [
      { "field": "name", "message": "Name must not be blank." }
    ]
  }
}
```

The `code` vocabulary is fixed: `validation_failed` (400), `not_found` (404),
`conflict` (409), `internal_error` (500). New codes are added only through the
Decision log.

## 5. Code style

- Naming: descriptive, no abbreviations. Methods are verbs; booleans read as
  questions (`hasChildren`, `isEmpty`).
- No dead code, no commented-out code, no leftover framework scaffolding (demo
  controllers, unused config, default templates). If a file's only reason to exist
  is "the generator made it", delete it.
- PHP: `declare(strict_types=1)`, fully typed properties/params/returns, constructor
  promotion, `final` by default (except Doctrine entities where the ORM requires
  otherwise), enums for fixed value sets, no cleverness.
- Keep files small and single-purpose; extract before they grow past a screen or two.

### Performance principles

- Indexes on every column used in WHERE / ORDER BY / uniqueness checks; they are part
  of the schema migrations, not an afterthought.
- No N+1 queries: fetch-join or batch-load related data. On critical endpoints,
  assert the query count in the functional test where practical.
- All collection endpoints paginated; never load unbounded result sets into memory.
- Payloads contain only what the UI needs — no god-objects, no lazy-everything
  serialization surprises.
- Frontend: stable list keys, state derived rather than duplicated, no needless
  re-renders; memoize only when a real, observed cost justifies it.
- No premature micro-optimization: correct and simple first, then targeted,
  measured fixes.

### No AI-generated comments

- Never write comments that narrate code, restate a signature, or mark progress
  ("// now we loop…", "// this method returns…", "// added validation").
- Comments are allowed **only** for constraints the code cannot express: non-obvious
  domain rules, workarounds with their reason, surprising edge cases.
- Prefer renaming or extracting over commenting.
- No TODO/FIXME comments — unfinished work is tracked in the README's "Known
  limitations / What I'd improve" sections instead.
- No PHPDoc that merely repeats the signature; document only genuinely useful
  extras (invariants, thrown domain exceptions).

## 6. Testing strategy

- **Functional tests** — real HTTP requests against the API with a **real
  PostgreSQL container** started by Testcontainers. The database is never mocked.
- **Unit tests** — validation rules, small pure logic; fast, no container.

Principles:

- Arrange–Act–Assert; one behavior per test.
- Test names describe behavior: `create_directory_with_blank_name_returns_400`.
- Tests are independent and order-safe; each test creates its own data; no shared
  mutable state.
- Test public behavior, not implementation details.
- Cover happy paths and the meaningful edge cases: blank/oversized input, missing
  resources, name conflicts, boundary values.
- Every bug fix ships with a regression test that fails without it.

Fallback: if the Testcontainers PHP library proves unusable with Docker Desktop on
Windows, switch to a compose-managed throwaway test database — and record it as a
trade-off in the README and the Decision log.

## 7. Git conventions

- Default branch: `main`.
- Branch names: `<type>/<short-imperative-slug>` in kebab-case —
  `feature/create-directory-endpoint`, `fix/duplicate-name-409`,
  `test/import-validation`, `docs/readme-tradeoffs`, `chore/docker-setup`,
  `refactor/extract-storage-service`.
- Docs/convention-only changes may go directly on `main`; anything else gets a branch.
- Commits follow **Conventional Commits**:
  - Types: `feat`, `fix`, `refactor`, `test`, `docs`, `build`, `chore`.
  - Format: `type(scope): subject` — scope optional, e.g. `feat(api): create directory endpoint`.
  - Subject ≤ 72 characters, imperative mood, lowercase, no trailing period.
  - Body explains **why** whenever the change is non-obvious.
  - Commit granularity: one commit per meaningful unit of work — a feature, a
    bug fix, or one coherent docs/refactor change. Do not split a single task
    into micro-commits per file or per tweak; equally, unrelated changes never
    share a commit.
  - Never: "update files", "wip", "final fixes", or any mention of AI tooling.
- Never commit secrets, `.env`, `vendor/`, `node_modules/`, `var/`, or build output.
  `.env.example` stays in sync with `.env`.
- Before every push: the full test suite is green.

## 8. Tooling & CI

- **PHPStan** at the highest practical level, zero unresolved issues — static
  analysis is part of done, not a nice-to-have.
- **PHP-CS-Fixer** with a committed ruleset (`.php-cs-fixer.dist.php`); CI fails on
  a formatting diff. No hand-formatted code, no editor-style debates.
- **CI: GitHub Actions** runs on every push — Composer/npm install, PHPStan,
  PHP-CS-Fixer check, frontend build, and the full Testcontainers suite (Docker is
  available on hosted runners).
- Code that does not pass locally does not get committed; CI is the backstop, not
  the first line of defense.

## 9. Docker & environments

- `docker compose up` is the single entry point the README promises: backend,
  PostgreSQL, and frontend all come up together.
- The dev environment and the container images use the same pinned versions.
- Add `.gitattributes` (`* text=auto eol=lf`) and `.editorconfig` at scaffolding
  time — Windows dev machine, LF everywhere in the repo.

## 10. README contract

The README is part of the deliverable and must never drift from reality:

- Required sections: What it does · Requirements · How to run (end-to-end,
  copy-pasteable) · How to test · API overview · **Assumptions** ·
  **Known limitations & trade-offs** · **What I'd improve with more time**.
- Any trade-off, simplification or unfinished piece is written there up front —
  the reviewer must not discover it.
- The README is updated in the same commit as the change that affects it.

## 11. How agents must work

1. Read this file and the README before every task.
2. Use plain English everywhere: conversations, questions, commits, README, docs,
   and the code itself. Short sentences, everyday words, technical terms only when
   they are the precise ones. No filler, no marketing tone, no untranslated phrases.
3. **Ask before guessing** — the user expects detailed questions on important
   decisions, with concrete options and a recommendation. Ask when unsure about:
   task-spec interpretation, data-model decisions, adding dependencies, deviations
   from this file, or anything that would create hidden README debt.
4. Follow the git conventions (§7) for every change.
5. Keep this file current: new decisions → Decision log; new conventions → the
   relevant section; progress → Project status.
6. Definition of done for any change: tests green · static analysis clean · no
   scaffolding or dead code · README still truthful · conventions respected.

### Decision log (append-only, newest at the bottom)

| #  | Decision | Why | Date |
|----|----------|-----|------|
| D1 | MVC split as: Model+Controller in the Symfony JSON API, View in the React SPA | Brief demands layer separation and a modern frontend | 2026-09-30 |
| D2 | No Redis for now | No concrete need yet (no caching/rate-limiting requirement); revisit when one appears | 2026-09-30 |
| D3 | JSON fields are camelCase | Matches TypeScript and PHP naming without a mapping layer | 2026-09-30 |
| D4 | Repo hosted private at `dkrizanic/file-system-app` on GitHub | Interview task, private by default | 2026-09-30 |
| D5 | Quality bar: simple + performant + complete — full stop; over-engineering still banned | The task must be excellent in every way; deliberate simplicity is the vehicle, not the excuse | 2026-09-30 |
| D6 | No frontend tests; testing effort goes to the backend | The company evaluates the PHP side; frontend stays lean. Trade-off to be stated in the README | 2026-09-30 |
| D7 | PHPStan + PHP-CS-Fixer + GitHub Actions CI enforce the conventions | Conventions that nothing enforces drift; CI also shows reviewers a green pipeline | 2026-09-30 |
| D8 | DTOs split into read models (responses) and write models (validated input); interfaces for every service and repository | Responses never leak entities; contracts between layers are explicit and mockable | 2026-09-30 |
| D9 | Interfaces collected in `src/Contract/`; implementations keep natural names in their layer folders | Java-style contract/implementation separation with one glanceable folder and no `*Impl` naming noise | 2026-09-30 |
| D10 | `src/Mapper/` owns all entity ↔ model translation; services use mappers, controllers never do | One place for translation; controllers stay validation + one interface call | 2026-09-30 |
| D11 | No separate backend validation layer and no Yup on the frontend | Symfony Validator on write DTOs is the single source of truth; frontend renders the 400 envelope and uses native validation first, adding Zod only if a form outgrows it | 2026-09-30 |

### Project status

- [x] Git repo, GitHub remote, AGENTS.md
- [x] Folder structure: `backend/` (Symfony MVC) + `frontend/` (React)
- [ ] Symfony skeleton + Docker dev environment (compose with PostgreSQL)
- [ ] Domain model & migrations (file-system domain)
- [ ] API endpoints + validation + error handling
- [ ] React app: structure, API layer, error boundary
- [ ] Testcontainers functional suite + unit tests
- [ ] Tooling & CI: PHPStan, PHP-CS-Fixer, GitHub Actions pipeline
- [ ] README polish: assumptions, trade-offs, improvements

## 12. Task brief (verbatim, when available)

_To be filled with the official company task statement once provided. Until then,
treat §2 as the source of truth and confirm scope with the user._
