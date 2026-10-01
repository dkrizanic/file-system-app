---
title: 'Frontend SPA: React scaffold, folder browser, search, nginx origin'
type: 'feature'
ticket: ''
created: '2026-10-01'
status: 'ready-for-dev'
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

**Problem:** `frontend/` holds only `.gitkeep` placeholders — no SPA, no nginx origin. The product has no UI and `docker compose up` brings up only backend + db.

**Approach:** Scaffold Vite + React + TS strict inside a `node:24` container; build the layered SPA (`src/api|components|hooks|pages|types`) strictly against the pinned AD-6 routes and AD-9 shapes; add the nginx container serving the built SPA and proxying `/api` so one `docker compose up` yields app + db + frontend on one origin; update the README run section to match.

## Boundaries & Constraints

**Always:** TypeScript strict, no `any`, no non-null assertion chains, early returns; fetch only inside `src/api`; function components + hooks, `ErrorBoundary` the single class; `src/types` mirrors AD-9 verbatim (camelCase); the error envelope is parsed into one typed `ApiError` carrying `code`, `message`, `details` — 400/409 `details` map to per-field form errors, every failure surfaces a meaningful message, never swallowed; pagination limit default 50 / cap 100, offset never past `total`; folders-first order is the API's — the UI renders `Page.items` in order; breadcrumbs render `ItemDetail.parentPath`; the app bootstraps at the published root id (`backend/src/Entity/Item.php:15`); all npm/node commands run in a `node:24` container, never the host; browser-native form validation attributes first — the backend write models stay the single source of truth (D11).

**Never:** no router, state-management, or validation-library dependencies; no frontend tests (D6); no CSS framework or design polish (brief scope-down); no auth, no file content; no edits under `backend/` and no inspection of the parallel API branch (`feature/api-endpoints`) — the spine's AD-6/AD-9 is the only contract; no CORS configuration anywhere; no client-side copy of the name grammar (native `required`/`maxLength` only); no unbounded listings rendered.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Create folder/file | valid name in open form | item appears in the listing immediately (refetch current page) | none |
| Duplicate sibling | name matching a sibling of either type | form stays open | 409 `conflict` → inline `name` message from `details` |
| Grammar violation | blank, >255, `/` `\` | submit blocked natively; server rules win | 400 → per-field errors from `details` |
| Unknown/deleted folder | stale id in URL | not-found state with link back to root | 404 envelope → state, never a blank screen |
| Empty folder / zero hits | no items; search without matches | explicit empty states ("empty", "no matches") | 200 empty `items` — never shown as an error |
| Typeahead | typing in the search box | debounced (~300 ms) `GET /api/suggestions`, ≤ 10 rows in a dropdown; stale responses discarded (abort/sequence); no request before a non-space char | failure → banner; suggestions just hide |
| Exact search | Enter submits; scope toggle current-folder (`scope=folder` + `folderId`) vs all (`scope=all`) | results view lists `ItemDetail`s with `parentPath`; blank input never sends | 400 envelope surfaced |
| Delete | confirmation dialog; folders warn everything inside is deleted | 204 → listing refetches | 404 → listing refreshes to current truth |
| Backend unreachable / 500 | any call | banner with envelope `message` or generic text; boundary catches render crashes | never silent, never a raw stack |

</frozen-after-approval>

## Code Map

- `frontend/src/**` -- `.gitkeep` placeholders only (verified); this unit fills the tree
- `compose.yaml:7` -- app publishes 8080:80 today; nginx inherits that port, app moves to the override
- `compose.override.yaml:7-9` -- backend bind-mount + vendor-volume pattern; extend with the node tooling service and the app dev port
- `backend/Dockerfile` -- image conventions to mirror: pinned base, env defaults, curl installed
- `backend/src/Entity/Item.php:15` -- `ROOT_ID` `1a0ef9c6-0000-7000-8000-000000000000`, the SPA entry constant (AD-4)
- `README.md:6-11,42-110` -- current-state, run and test sections rewritten by this unit
- `_bmad-output/.../architecture-file-system-app.md:116-125,141-149,151-174` -- AD-6 routes, AD-8 origin topology, AD-9 shapes: the pinned contract

## Tasks & Acceptance

**Execution:**
- [x] `git` -- branch `feature/frontend-spa` off `main`
- [x] `frontend/` -- scaffold Vite + React + TS in a `node:24` container (bind mount + `npm create vite` + `npm install`); pin react 19.3 / vite 8 / node 24, commit `package-lock.json`, strict `tsconfig`, remove `.gitkeep` placeholders as real files land
- [x] `frontend/src/types/` -- `ItemSummary`, `ItemDetail`, `PathEntry`, `Page<T>`, `Suggestion`, `ApiError` — AD-9 verbatim
- [x] `frontend/src/api/` -- typed client: one function per AD-6 route; throws `ApiError` parsed from the envelope (`details` only on 400/409); no component touches fetch
- [x] `frontend/src/hooks/` -- `useFolderItems` (paged fetch + refetch after writes), `useItemDetail` (breadcrumb path), `useDebouncedValue`, `useSuggestions` (abort stale requests), `useHashFolderId` (routing)
- [x] `frontend/src/components/` -- `Breadcrumbs`, `ItemList`, `Pagination`, `CreateItemForm` (folder/file), `RenameForm`, `DeleteConfirm` (cascade warning), `SearchBox` (suggestions dropdown + scope toggle), `EmptyState`, `ErrorBanner`, `LoadingState` — presentational, props in / events out, split past ~100 lines
- [x] `frontend/src/components/ErrorBoundary.tsx` -- top-level class boundary; wrap the search subtree and the form area
- [x] `frontend/src/pages/FolderBrowserPage.tsx` -- thin orchestrator: hash folder id (root default), breadcrumb + listing + forms + search; exact-search results replace the listing view
- [x] `frontend/src/main.tsx`, `index.html` -- boundary at the top, page mounted, minimal styles
- [x] `frontend/Dockerfile` -- multistage: `node:24` build stage (`npm ci`, `npm run build`) → `nginx:1.30-alpine` serve stage
- [x] `nginx/default.conf` -- SPA `try_files ... /index.html`; `location /api/` → `http://app:80`
- [x] `compose.yaml` -- add the `nginx` service publishing `8080:80`, depending on `app`; `app` stops publishing a host port
- [x] `compose.override.yaml` -- `app` publishes `8081:80`; add the `node` tooling service (`node:24`, `./frontend` bind mount, `/app` workdir)
- [x] `frontend/vite.config.ts` -- dev proxy `/api` → `API_PROXY_TARGET` env (default `http://localhost:8081`)
- [x] `README.md` + `AGENTS.md` -- run/test rewritten for the three-service stack and the container dev workflow; tick the "React app" project-status item

**Acceptance Criteria:**
- Given a clean checkout, when `docker compose up -d --build`, then app, db and nginx are healthy and `http://localhost:8080` serves the SPA on one origin.
- Given the node container, when `npm run build` runs, then strict tsc + vite build exit 0.
- Given the dev override, when the Vite dev server runs, then `/api` proxies to the app with hot reload.
- Given any failed API call, when the UI renders, then the envelope message/details appear (per-field where applicable) and no error is swallowed.

## Implementation Notes

## Plan Change Log

- **Compose service named `frontend`, not `nginx`.** The task said "add the `nginx` service"; the frontend image build gate addresses the service as `frontend` (`docker compose build frontend` failed with `no such service: frontend`). The service key is now `frontend` — it still builds `frontend/Dockerfile` and serves the SPA with nginx, and nothing depends on the old name.
- **Branch name.** The ask for this unit names the branch `feature/frontend`; the task above says `feature/frontend-spa`. Followed the ask; the branch is `feature/frontend`, created off `main` (37bd3a1).
- **Worktree instead of in-place checkout.** The parallel API unit owns the main working checkout (uncommitted backend changes, live `file-system-app` stack on host port 8080), so switching branches there would destroy its in-flight work. `feature/frontend` was created in a linked git worktree at `../fsa-frontend` (same repository). All verification ran under compose project `fsa-verify` with nginx on host port 18080 (port 8080 is occupied by the parallel stack); the port shift was a throwaway overlay file outside the repo, since deleted. On a clean checkout the documented ports (8080/8081) apply unchanged. The build gates run against the main checkout, not the worktree, so once the API unit closed as built and the main checkout moved to `main`, the branch was checked out in the main checkout and the worktree removed — the round-2 gate failure (`no such service: frontend` again, despite the rename) was the gate not seeing the branch, not the service name.
- **Scaffold: generator-informed, hand-placed, strict added.** `npm create vite@latest` (react-ts) was run in a `node:24-alpine` container to capture current template reality, but the files were written by hand from that output: the 2026-10 template generates no explicit `"strict"` and ships an oxlint config. `strict: true` is set in both tsconfigs; oxlint is dropped (lint arrives with the CI unit, per Design Notes). Locked versions: react/react-dom 19.3.0, vite 8.3.2, typescript 6.0.3, @vitejs/plugin-react 6.1.1, node:24-alpine.
- **Create/Rename forms share one component.** Per the pre-approved Design Note, `CreateItemForm` + `RenameForm` are one `ItemForm` (label/submitLabel/initialName/error props, one `onSubmit` promise contract) used three ways in `FolderView`.
- **`src/utils/` and `public/` dropped.** Nothing landed there (page math stays inside `Pagination`, no favicon); empty placeholder directories are dead weight, so both were removed with their `.gitkeep` files.
- **Node service profile-gated + `frontend_node_modules` volume.** `compose up` must start exactly three services, so `node` carries `profiles: ["tools"]` (verified `docker compose run --rm node …` still works). `node_modules/` sits in a named volume, mirroring the backend's `app_vendor` pattern, so Windows/OneDrive bind-mount churn never sees it. Verified `run` auto-activates the profile.
- **nginx healthcheck: curl, and IPv6 listen.** busybox `wget` connected the healthcheck to `::1` while the server block listened IPv4-only (observed: container `unhealthy`, `connection refused`). Healthcheck now uses `curl` like the app service (verified present in `nginx:1.30-alpine`), and the conf adds `listen [::]:80;`. After the fix: `app`, `db`, `nginx` all `healthy`.
- **Verification expectation for `/api/suggestions`.** The plan expects "JSON answered by the app" — on `main` no API routes exist yet, so the proxied call returns the Symfony dev 404 page (HTML). That response comes from the app container through nginx, which is the proof the gate exists for (proxy path). Recorded as satisfied-in-intent; JSON responses arrive with the API branch.
- **The production-build gate is the image build, not the runtime container.** This unit sat blocked because its gate ran `npm run build` inside the running frontend container — `nginx:1.30-alpine` has no npm, so it exited 127. The branch never needed that: `frontend/Dockerfile` compiles the SPA in the `node:24-alpine` builder stage (`npm ci` at `frontend/Dockerfile:9`, `npm run build` at `:12`, which is `tsc -b && vite build` per `frontend/package.json`), and the nginx stage only copies `dist/`. So `docker compose build frontend` IS the production TypeScript gate; nothing in `frontend/` changed. Verified on the rebased branch: the gate passes (exit 0, `npm run build` re-executed after a source change), and a strict type error (TS2322) and a syntax error (TS1351) injected into `frontend/src/constants.ts` each failed the image build (exit 1, `process "/bin/sh -c npm run build" did not complete successfully: exit code: 2`); the probe file was reverted untouched.
- **Rebased onto main (bead8f2) with the merged API unit.** The three commits replayed; conflicts resolved keeping both sides' intent. `AGENTS.md`: kept both status ticks (API endpoints and React app delivered). `README.md`: the API overview keeps main's full endpoint table (last line now says the frontend mirrors the contract, `frontend/src/types`); the run section keeps this branch's three-service flow with main's API curl examples unchanged — nginx on 8080 proxies `/api`, so they work verbatim; the limitations list keeps main's API caveats and drops the now-false "endpoints not on this branch yet" entry, leaving "No CI yet" as the only next-unit gap; improvements merge main's RFC 7807 with this branch's entries. `compose.yaml`/`compose.override.yaml` needed no resolution (only this branch had touched them); `plan-frontend.md` resolved automatically (this branch's first commit carried the copy identical to main's). Runtime re-verified per AD-8 after the rebase: `docker compose up -d` → `app`, `db`, `frontend` all healthy; `curl -sI http://localhost:8080/` → 200 from nginx with the built SPA shell; a create/list/delete round trip through the proxied `/api` returned 201, the JSON page, and 204 (probe deleted, the `/api/suggestions` expectation above is now met with real JSON). Not merged, not pushed.

## Review Triage Log

## Design Notes

Plan-level decisions (pre-approved, no open questions):
- No router dependency: the folder id lives in the URL hash (`#/folders/{id}`, root `#/`), read by `useHashFolderId` — one screen, deep-linkable, zero dependencies. Search results are view state, not URL.
- Selecting a suggestion navigates to the file's parent folder (the last `parentPath` entry), which "locates the file" (FR-9) without a detail screen.
- nginx inherits host port 8080, the README's existing entry point; the app moves to 8081 in the dev override — base compose is the one-origin runtime (AD-8), the override adds dev reachability.
- All name matching and casing stay server-side (`normalized_name`, AD-3); the client sends raw input and renders results, never normalizing.
- Create and rename share one form component with a type prop — the second use exists at first build, so the AGENTS §3 extraction rule applies immediately.
- No eslint/prettier in this unit: strict tsc plus the build gate cover it; lint arrives with the CI unit (README trade-off).

## Verification

**Commands:**
- `docker compose run --rm node npm run build` -- expected: strict tsc + vite build exit 0
- `docker compose up -d --build && docker compose ps` -- expected: app, db, nginx all healthy
- `curl -sI http://localhost:8080/` -- expected: 200, text/html — the SPA shell from nginx
- `curl -s http://localhost:8080/api/suggestions?prefix=a` -- expected: JSON answered by the app, proving the proxy path
- Manual: once the parallel API branch lands, click through UJ-1/UJ-2 (create, rename, delete, conflict, search, suggestions) in a browser; merge is not blocked on it — the contract is pinned by AD-6/AD-9.
