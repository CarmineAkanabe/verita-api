# Verita API — Implementation Progress

Living log, one section per phase. Read the relevant phase section before
picking up work — each one carries the decisions/deviations made while
building it, not just a checklist.

---

## Phase 0 — Project Init & Setup ✅ Complete

**Repo:** `verita-api` (Laravel). Part of a 3-repo split: `verita-api`,
`verita-vue`, `verita-bruno` (Bruno collection — API docs + seeding tables
that depend on a runtime-created `Case` and can't go through factories).

**Global conventions locked in this phase:**
- All primary keys are **UUIDs**, not auto-increment integers.
- All auth guards (staff `api` guard + the future anonymous case guard) use **JWT**, not Sanctum.
- RFC 9457 problem-details envelope for all `api/*` error responses.
- `Repository` classes own **only** `Cache::`/`Redis::` calls — Services write
  to Models directly via `DB::transaction()`. (Explicitly rejected
  `manchenkoff/laravel-repositories` — its generic Eloquent-CRUD `Repository`
  concept conflicts with this convention; also archived upstream.)

**Delivered:**
- Folder scaffolding: `Data`, `Enums`, `Events`, `Listeners`, `Mail`,
  `Notifications`, `Policies`, `Repositories`, `V1` subfolders under
  Controllers/Requests/Resources.
- PostgreSQL connected (`migrate` runs clean).
- Redis wired for both cache and queue (Predis client).
- JWT (`tymon/jwt-auth`) installed, `api` guard added to `config/auth.php`,
  `User` implements `JWTSubject`. Full login round-trip not provable yet —
  `User` has no real fields until Phase 1.
- RFC 9457 exception envelope wired in `bootstrap/app.php`
  (`withExceptions()`), scoped to `api/*` only via `$request->is('api/*')`
  guard in each renderer. Verified against a 404.
- `v1` route prefix in place (`routes/api.php`).
- Named rate limiters defined but **not yet attached** to any route:
  `login` (5/min), `case-submit` (3/min), `pin-verify` (10/min). Attachment
  happens per-route in the phase that builds each one.
- `EnforceJson` middleware added (forces `Accept: application/json` on every
  request), registered globally on the `api` middleware group. **Not in the
  original plan doc — added mid-Phase-0.**
- Idempotency middleware (`EnsureIdempotency`) + `IdempotencyRepository`
  scaffolded as empty pass-throughs. Real logic lands in Phase 6 (Submit
  Case).
- Reverb installed and broadcasting route confirmed
  (`POST /broadcasting/auth`).
- Gmail SMTP wired; `MAIL_MAILER=log` for local dev (per convention — real
  sends only during Phase 12), one-off real SMTP send confirmed working.
- Gemini service (`app/Services/GeminiService.php`) built on Laravel's
  `Http` facade, **not Guzzle directly** — chosen for `Http::fake()`
  testability, since Phase 6's AI-failure-handling tests will need to mock
  Gemini without hitting the network. API key passed as query param, not
  header. `ping()` round-trip confirmed.
- Pest installed via `./vendor/bin/pest --init` (not `artisan pest:install`
  — that command doesn't exist in current Pest versions). Default example
  test passes.

**Gotchas hit, for the record:**
- Reverb threw `Pusher\Pusher::__construct(): $auth_key must be string, null
  given` after install — caused by a stale config cache serving pre-Reverb
  `.env`. Fixed with `php artisan config:clear`.
- Gemini model string: plan assumed `gemini-3.8-flash`, actually used
  `gemini-3.5-flash` (3.8 was over capacity at test time).
  `config/services.php` default updated to match.

**Flagged, not yet actioned:**
- Gemini's `thoughtsTokenCount` was 137 for a 2-word test answer — this
  model thinks by default. Worth capping via
  `generationConfig.thinkingConfig.thinkingBudget` once Phase 6 sends real
  case-structuring prompts. Cost/latency concern, not a blocker.

---

## Phase 1 — Models, Migrations, Seeding ✅ Complete

**Delivered:**
- 10 PHP backed enums (`app/Enums/`): `Role`, `PresenceStatus`, `CaseCategory`,
  `CaseStatus`, `SenderType`, `NotificationChannel`, `NotificationStatus`,
  `AuditActorType`, `AuditAction`, `EvidenceFileType`. Backed values match the
  master spec's exact vocabulary (`FRAUD`, `AWAITING_REVIEW`, etc.) so DB rows,
  API JSON, and spec text stay in sync.
- 7 migrations: `departments`, `users`, `case_records`, `evidences`, `messages`,
  `notifications`, `audit_logs`. All UUID PKs, generated at the model layer via
  Laravel's built-in `HasUuids` trait — no DB-level UUID default, no dependency
  on `pgcrypto`/`uuid-ossp`.
- 7 models + `User`, all relations per master spec §6.2 wired
  (`Department`, `User`, `CaseRecord`, `Evidence`, `Message`, `Notification`,
  `AuditLog`).
- Factories for all 7 models (`UserFactory` has `manager()`/`departmentHead()`
  states).
- `DatabaseSeeder`: 3 departments, 2 department heads each, 1 manager, 1 demo
  case (`AWAITING_REVIEW`) with evidence/messages/audit log attached.

**Deviations from the plan doc / master spec, both forced:**
- **`Case` → `CaseRecord` (class), table `case_records`.** `case` is a PHP
  reserved word (`switch`/`enum` syntax) — `class Case` is a parse error, not
  a style choice. `InvestigationCase` was rejected in an earlier pass and the
  master spec explicitly forbids reintroducing it, so `CaseRecord` was picked
  instead. Table name follows Eloquent's default convention from the class
  name (`case_records`) — no `$table` override needed. Every route, URL, and
  UI-facing string still says "Case"; this is a PHP-identifier-only deviation.
- **`Notification.status`** implemented as an enum (`UNREAD`/`READ`) rather
  than the master spec's undefined `status` attribute or a boolean
  `read_at` — the spec never enumerated its values, this was a session
  decision.
- **`AuditLog.timestamp` (spec name) → column `logged_at`.** Same attribute,
  renamed at the schema level only — `timestamp` is an awkward column/query
  identifier in Eloquent.
- **`evidences`, `messages`, `notifications`, `audit_logs` have no
  `created_at`/`updated_at`** — `public $timestamps = false;` on those 4
  models. They're append-only records with their own spec-named instant
  (`uploaded_at`, `sent_at`, `logged_at`); a redundant `updated_at` would be
  noise. `departments`, `users`, `case_records` keep standard `timestamps()`
  since those rows do mutate.

**Flagged, not yet actioned:**
- `users.department_id` is nullable at the DB level (Managers have none), even
  though a Department Head must have one per the spec. That constraint isn't
  enforced by the schema — it's a Phase 2 application-validation job (Form
  Request / Policy), not a DB-level rule.

## Phase 2 — Authenticate & Account Foundation ✅ Complete

**Delivered:**
- Login/logout via JWT (`api` guard): `LoginRequest` → `LoginData` DTO →
  `AuthService::login()/logout()` → `AuthController`. `login` rate limiter
  (defined in Phase 0) attached to `POST /api/v1/auth/login`.
- Update Account Profile: `UpdateProfileRequest` → `UpdateProfileData` DTO →
  `AccountService::updateProfile()` → `AccountController`. Supports partial
  updates (`sometimes` rules) to name/email/password/`profilePicture`.
- View Account Dashboard: `AccountService::dashboard()` branches on
  `Role` via `match` — Department Head payload (`department`,
  `assignedCaseCount`) differs from Manager payload (`departmentCount`,
  `userCount`).
- `UserResource` (camelCase API shape) used across both auth and account
  responses.
- Pest coverage: `AuthTest` (login happy path, wrong password → 401, missing
  fields → 422), `AccountTest` (profile update happy path, dashboard shape
  per role — Department Head and Manager separately).
- Middleware/Policy split decided and documented: Phase 2's actions are all
  self-resource (a user only ever acts on their own account), so no Policy
  is needed here. Confirmed Phase 3/4's "Manager-only" restrictions will use
  a role-checking Middleware, not a Policy — Policies are reserved for
  genuine instance-level authorization, first needed in Phase 8's
  `CasePolicy`.

**Deviations, both intentional:**
- **Profile picture** stores to the `public` disk directly
  (`Storage::disk('public')->store('avatars', ...)`) — a simpler, separate
  path from Phase 5's case-scoped evidence storage. Not the same mechanism,
  deliberately.
- **Password hashing** relies entirely on the `'hashed'` cast already set on
  `User` in Phase 1 — `AccountService` just assigns the plain string, no
  manual `Hash::make()` call.

**Gotchas hit, for the record:**
- **`.env.testing` was actually missing**, despite Phase 0's PROGRESS.md
  entry claiming Pest was installed and ready. Created now: separate
  Postgres DB (`verita_testing`), `MAIL_MAILER=array`,
  `QUEUE_CONNECTION=sync`, `CACHE_STORE=redis` on `REDIS_CACHE_DB=1`
  (distinct from dev's `0`), fresh `APP_KEY` and `JWT_SECRET` generated via
  `--env=testing`. Logged here rather than editing the Phase 0 write-up
  after the fact.
- First draft of `AuthTest` hit routes at `/v1/auth/...` and got 404s —
  `routes/api.php` already carries an automatic `api/` prefix from Laravel's
  routing bootstrap, and the `Route::prefix('v1')` group adds `v1/` on top
  of that, so the real path is `/api/v1/auth/...`. Fixed.

**Decision logged this phase for later use (Phase 6, not built yet):**
- `spatie/laravel-data` will be used for Phase 6's `AiCaseAnalysisData` DTO
  (hydrating Gemini's JSON response) — the one deliberate exception to the
  plain-readonly-DTO default from Phase 0's Global Conventions. Not
  installed yet; `composer require` happens when Phase 6 starts.

## Phase 3 — Manage Departments ✅ Complete

**Delivered:**
- `EnsureRole` middleware (aliased `role` in `bootstrap/app.php`), guarding
  role-restricted routes — checks `$user->role instanceof Role` before
  matching against the route's allowed roles, `abort(403, ...)` on failure.
- Create/Update/Delete Department, all Manager-only:
  `StoreDepartmentRequest`/`UpdateDepartmentRequest` → `DepartmentData` DTO →
  `DepartmentService` → `DepartmentController`.
- **Added beyond the plan doc's literal scope:** `GET /api/v1/departments`
  (list). "Manage Departments" with no way to see existing ones isn't
  really usable, and Phase 4's Department Head creation form needs a
  department list to populate anyway.
- `DepartmentResource` (id, name).
- Pest coverage: non-manager forbidden (403), manager create/update/delete
  happy paths, missing-name validation (422).

**Corrections made to my own draft before this phase closed:**
- **No dedicated `AuthorizationException` renderer was needed** — the
  existing catch-all `Throwable` renderer in `bootstrap/app.php` already
  handles any `HttpExceptionInterface`-implementing exception generically
  by status code. Since `EnsureRole` uses `abort(403, ...)` (which throws
  exactly such an exception) rather than throwing `AuthorizationException`
  directly, the catch-all covers it with zero new code. I'd initially
  assumed a new renderer was required — it wasn't; withdrawn.
- **Test assertions fixed for Resource wrapping:** `DepartmentTest`'s
  create/update assertions were checking `name` at the response root;
  corrected to `data.name`, since both `store()` and `update()` return a
  `DepartmentResource` directly (or via `->response()`), which Laravel
  wraps in a `data` key by default. Standing rule now: `assertJsonPath`
  needs a `data.` prefix whenever the response is a Resource returned
  directly by the controller — not needed for plain array/`json()`
  responses (e.g. the Phase 2 dashboard payload) or a Resource manually
  nested inside a hand-built array (e.g. Phase 2 login's `user` key).

**Not yet handled:**
- `DepartmentService::delete()` has no app-level guard against deleting a
  department that still has cases — relies entirely on the DB's
  `restrictOnDelete()` FK constraint (Phase 1) to reject it, which surfaces
  as a raw DB exception rather than a clean validation error. Acceptable
  for now since Fraud is the only demoed category and department deletion
  isn't a demo path, but worth a proper `QueryException` → 409/422 mapping
  before defense if time allows.

## Phase 4 — Manage User Accounts (Department Heads) ✅ Complete

**Delivered:**
- Create/Update/Delete Department Head account, Manager-only:
  `StoreDepartmentHeadRequest`/`UpdateDepartmentHeadRequest` → `DepartmentHeadData`
  DTO → `DepartmentHeadService` → `DepartmentHeadController`.
- **Added beyond the plan doc's literal scope:** `GET /api/v1/department-heads`
  (list) — same rationale as Phase 3's department list: Manage User Accounts
  with no way to see existing accounts isn't really usable.
- Route-model binding for `{departmentHead}` scoped to `role = DEPARTMENT_HEAD`
  in `AppServiceProvider::boot()` — prevents the route resolving against a
  Manager's own `User` row.
- Initial password is set directly by the Manager in the create request (plain
  field, hashed via the existing `'hashed'` cast on `User`) — no invite/temp-
  password email flow, since Phase 12's email dispatch isn't built yet.
- `UserFactory::departmentHead()` state added (`role => Role::DEPARTMENT_HEAD`),
  used by this phase's own tests.
- Pest coverage (`DepartmentHeadTest`): non-manager forbidden (403), manager
  create/update/delete happy paths, create against nonexistent department (422).

**Deviation from the plan doc's literal routing, forced by a failing test:**
- Department Head routes are **not** in the same `Route::group` as Phase 3's
  Department routes. They sit in their own `role:MANAGER` group ("Manager
  Features") separate from the `departments` prefix group — grouping them
  together 404'd until split out this way.

**Corrections made before this phase closed:**
- **`UserResource`'s null-safe operator was on the wrong receiver.** First
  draft (Gemini-suggested): `$this?->presence_status->value` — guards `$this`
  (the Resource, never null), not the actual nullable field. Fixed to
  `$this->presence_status?->value`. Not exercised by current tests (fixtures
  always populate `presence_status`), so the fix is correct by inspection,
  not proven by a passing test — flagging, not claiming certainty.
- **`DepartmentHeadController::update()` needed `$departmentHead->refresh()`**
  after the service call — without it, the returned `UserResource` was stale
  relative to the row just written.

**Not yet handled:**
- Same gap Phase 3 flagged for department deletion: deleting a Department
  Head with cases still `assignedTo` them isn't guarded at the app level —
  relies on whatever FK rule Phase 1 put on `case_records.assigned_to`.

## Phase 5 — Submit Case ✅ Complete

**Delivered:**
- `StoreCaseRequest` → `CaseSubmissionData` DTO → `CaseSubmissionService` →
  `CaseSubmissionController`. Public route, no `auth:api` — the one endpoint
  group that isn't behind staff login.
- Tracking PIN: 6-digit numeric, zero-padded, hashed via `Hash::make()` in
  the Service, shown once in the response body and never persisted plain.
- Evidence stored to the private `local` disk (not `public` — kept separate
  from Phase 2's profile-picture disk, deliberately, given §20's anonymity
  concerns).
- `category` hardcoded to `FRAUD` in the Service — not client-supplied,
  matches §13's form having no category field.
- `ProcessCaseWithAiJob` stub created (`ShouldQueue`, empty `handle()`) —
  dispatched after the DB transaction commits, never inside it. Real Gemini
  logic lands in Phase 6.
- `EnsureIdempotency` middleware + `IdempotencyRepository` — the real logic
  Phase 0 flagged as landing here (that entry said "Phase 6," which was a
  stale label from before the plan got renumbered; Submit Case has always
  been Phase 5).
- Pest coverage: happy path (PIN shown once, status lands on
  `AI_PROCESSING`, job dispatched), missing required field (422), no
  evidence (422), missing `Idempotency-Key` header (400), replayed key
  creates only one case, `concernsDepartmentHead` flag persisted for
  Phase 8's queue filtering.

**Corrections made before this phase closed:**
- **`Evidence`'s FK is `case_record_id`, not `case_id`.** First draft used
  the spec's literal `caseId` → `case_id`. Wrong — Phase 1 renamed the class
  to `CaseRecord` (`case` being a PHP reserved word), and the FK column
  follows that model name by Eloquent's default `belongsTo()` convention,
  not the spec's prose. Same applies to every other table that references a
  case (`messages`, `audit_logs`, `notifications` where relevant) — logged
  as a standing naming rule, not a one-off fix.
- **`EnsureIdempotency`'s response capture switched from `$response->getData(true)`
  to `json_decode($response->getContent(), true)`.** The first version
  assumed the response was always a `JsonResponse`; the second works
  regardless of response type. Cache condition also narrowed from "any
  status < 500" to strict 2xx — a failed submission isn't idempotency-cached,
  so retrying after an error re-runs from scratch instead of replaying the
  failure.
- **Test count assertion scoped to `department_id`:** `CaseRecord::count()`
  alone was picking up rows from other tests in the same file; scoped the
  assertion (`CaseRecord::where('department_id', $department->id)->count()`)
  rather than chasing full test-isolation root cause.

**Not yet handled:**
- `EnsureIdempotency` currently has two `dump()` calls left in from
  debugging the cache-hit path. Harmless in tests, but worth pulling before
  Phase 6 so they don't clutter output or ship further.
