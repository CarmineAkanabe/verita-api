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

## Phase 6 — AI Structuring (Gemini Integration) ✅ Complete

**Delivered:**
- `GeminiService::analyzeCase()` — builds the prompt from the Case's
  structured fields, attaches Evidence as inline multimodal parts (mime
  type read off disk via `Storage::mimeType()`, not guessed from the
  `IMAGE`/`PDF` enum), requests strict JSON via `responseMimeType` +
  `thinkingConfig.thinkingBudget => 0` (closes the cost/latency flag Phase 0
  raised).
- `AiCaseAnalysisData` (`app/DTO`) — `spatie/laravel-data`, per Phase 2's
  decision to make this the one exception to the plain-readonly-DTO default.
- `ProcessCaseWithAiJob` fleshed out from Phase 5's stub: parses the Gemini
  response into the DTO *before* touching the DB (malformed response never
  produces a partial save), writes `aiSummary`/`aiTimeline`/`aiFindings`,
  flips status to `AWAITING_REVIEW`, writes the `AuditLog` entry
  (`actorType: AI`, `action: AI_PROCESSED`) inside the same transaction,
  fires `CaseReadyForReview` after the transaction commits. `$tries = 3`,
  backoff `[10, 30, 60]`.
- `AuditLogService` — small wrapper Service, first use of the "automatic,
  never manual" AuditLog rule (§6.1); reused wherever else the spec names
  the AI job, `updateStatus()`, or escalation as auto-logging.
- `AiProcessingService::dispatch()` — the `reprocessWithAI()` entry point
  the master spec lists on `Case`, MVCS-relocated to a Service. Sets status
  back to `AI_PROCESSING` and re-dispatches the job; this is what Phase 7's
  Add Evidence flow will call, and what recovers a case after retries
  exhaust.
- **Phase 5 retrofit:** `CaseSubmissionService` now calls
  `AiProcessingService::dispatch()` instead of inlining its own
  status-flip-then-dispatch — collapses duplicated logic now that a shared
  entry point exists.
- Pest coverage: valid response maps onto the case + fires the event;
  malformed response throws (no partial save, case stays at
  `AI_PROCESSING`); reprocessing re-dispatches the same job.

**Corrections made before this phase closed:**
- **`CaseRecord::$fillable` was missing `ai_summary`/`ai_findings`.** Mass
  assignment silently dropped both until added — worth checking `$fillable`
  proactively whenever a future phase's `update()` call introduces a column
  Phase 1 didn't already write to (Phase 8's `resolutionSummary`/`assignedTo`
  are the next likely hit).
- **Malformed-response test asserts `Exception::class`, not `Throwable::class`.**
  Confirms `AiCaseAnalysisData::from()` throws a clean Exception subtype
  (spatie's own, from missing required keys) rather than a raw `TypeError` —
  good, means the `failed()`/retry path is catching a well-formed error, not
  an incidental PHP type error.
- **`GeminiService::endpoint()` already existed** — confirmed shape:
  `config('services.gemini.model', 'gemini-3.5-flash')` +
  `config('services.gemini.key')`, matches what `analyzeCase()` assumed.
- **Evidence tied to the case via plain `case_record_id` assignment**
  (`Evidence::factory()->create(['case_record_id' => $case->id])`), not a
  named relation factory state — sidesteps needing to know the exact
  relation method name.

**Not yet handled:**
- `failed()` still just calls `report($exception)` — no AuditLog entry (no
  clean `AuditAction` value fits a failure) and no Manager-visible surface
  beyond the case sitting at `AI_PROCESSING`. Flagged last phase too, still
  open.

## Phase 7 — Case Dashboard & Tracking (Case Reporter side) ✅ Complete

**Delivered:**
- `CaseRecord` made `Authenticatable` + `JWTSubject` directly — no separate
  auth model. One token serves this phase's Dashboard now and Phase 9's Chat
  later. New `case-api` guard/provider pair in `config/auth.php`.
- Verify Case ID + Tracking PIN → JWT: `CaseAuthService::verifyPin()` does a
  **manual** `CaseRecord::find()`, not route-model binding — binding would
  404 on a bad Case ID before the PIN check ever runs, which is a different
  response than the 401 for a right-ID-wrong-PIN case and would leak which
  Case IDs exist. Both failure modes collapse into the same generic 401.
  `pin-verify` rate limiter (Phase 0) attached.
- `GET /cases/me` — status-aware payload via `CaseReporterDashboardResource`:
  own fields + evidence always; `aiSummary`/`aiTimeline`/`aiFindings` only
  once status is past `SUBMITTED`/`AI_PROCESSING` (`$this->when()`, not a
  separate endpoint or screen).
- `POST /cases/me/evidence` — appends evidence, re-triggers AI processing via
  Phase 6's `AiProcessingService::dispatch()` (the `reprocessWithAI()` reuse
  the plan called for).
- `GET /cases/me/evidence/{evidence}` — streams the file via
  `Storage::response()`; ownership check returns 404 (not 403) on a mismatch,
  same information-hiding principle as the verify-pin 401 — a token doesn't
  get confirmation that evidence belonging to a different case even exists.
- **Second Phase 5 retrofit:** extracted `EvidenceService::store()` (file
  store + `Evidence::create()`) out of `CaseSubmissionService`, now shared
  with Add Evidence rather than duplicated a second time.
- **New this phase, not in the master spec's class model:** added
  `EVIDENCE_ADDED` to `AuditAction` (`actorType: SYSTEM`), logged whenever
  the Case Reporter adds evidence post-submission. The spec's five-value
  enum only covers AI/Department-Head-initiated events; a reporter-triggered
  state change (status flips back to `AI_PROCESSING`) was going untracked
  entirely, which undercuts the same traceability goal `ESCALATED` exists
  for. Same shape of exception, same justification. **Spec file update is
  the user's own follow-up, not done here.**
- Pest coverage: verify-pin happy path + matching 401 shape for both failure
  modes; dashboard payload differs correctly by status; add-evidence
  re-triggers reprocessing and writes the new audit entry; evidence download
  rejects a token for a different case.

**Corrections made before this phase closed:**
- **Token-minting in tests used `auth('case-api')->login($case)`, which
  produced spurious 201s on `GET`/`POST` calls (tests 3, 4, 5, 7).** Not a
  status-code quirk to special-case with `assertCreated()` — `login()`
  caches `$case` as the guard's current user, and Laravel's in-process test
  requests reuse that same cached object instead of re-resolving off the
  token, so it still carried `wasRecentlyCreated = true` from the factory
  call. `JsonResource` reads that flag for its status code. Production code
  never hits this (`$request->user('case-api')` always resolves fresh from
  the JWT), so the tests were asserting a status the real API can't produce.
  Fixed by minting with `JWTAuth::fromUser($case)` instead, which doesn't
  touch the guard's cached user — tests now correctly assert `assertOk()`.
  `CaseAuthService::verifyPin()` itself was untouched; `login()` there is
  correct, since authenticating *is* that endpoint's job.

**Not yet handled:**
- Phase 7's controllers skip the Request → DTO → Service pattern Phase 4-6
  used everywhere else (`CaseAuthService::verifyPin()` takes two scalars;
  `EvidenceService::store()` takes the authenticated `$case` + a raw file
  array) — neither arg set is really "a validated request body," so a DTO
  wrapper wouldn't earn its keep here the way `CaseSubmissionData` did.
  Flagged as a conscious call now, not silently dropped; revisit if strict
  cross-phase consistency matters more than this reasoning by defense time.

## Phase 8 — Department Head Case Management ✅ Complete

**Delivered:**
- Migration: `audit_logs` gets a new nullable `note` text column — the addendum's mandatory-note-per-status-change had nowhere to live without it (`previousValue`/`newValue` stay clean status values). Same shape of deviation as Phase 7's `EVIDENCE_ADDED`.
- `CaseRecordPolicy` (`app/Policies/`) — first real Policy in the project, as flagged back in Phase 2. Model is `CaseRecord`, not `Case`, so Laravel's convention-based discovery doesn't find it automatically — registered explicitly via `Gate::policy(CaseRecord::class, CasePolicy::class)` in `AppServiceProvider::boot()`. Three methods: `claim` (unclaimed, right department, not conflict-of-interest, `AWAITING_REVIEW`), `view` (assigned DH, or still-unclaimed queue-eligible DH — pre-claim read access), `updateStatus` (strictly the assigned DH).
- `Route::model('case', CaseRecord::class)` added to `AppServiceProvider::boot()` for the `{case}` route parameter.
- `UpdateCaseStatusRequest` → `UpdateCaseStatusData` DTO → `CaseManagementService` → `CaseManagementController`.
- Claim modeled as its own action/endpoint, deliberately not folded into Update Case Status — different concurrency handling (atomic conditional `UPDATE ... WHERE assigned_to IS NULL`, checked via affected-row count inside `DB::transaction()`) and no note required.
- `CaseAlreadyClaimedException` (extends `ConflictHttpException`) — no dedicated renderer needed, same as Phase 3's finding: the generic `HttpExceptionInterface` catch-all already turns it into a 409 RFC 9457 envelope.
- Update Case Status: unconditionally writes an `AuditLog` entry (`STATUS_CHANGED`) regardless of new status; `resolutionSummary`/`resolvedAt` only written when the new status is `RESOLVED`/`DISMISSED`; `CaseResolved` event fired only when `RESOLVED`, after the transaction closure returns (no listener yet — Phase 12).
- `AuditLogService::log()` extended with an optional `?string $note = null` param, written to the new column.
- `CaseDetailResource` — staff-facing case view (structured fields, evidence, AI summary/timeline/findings).
- Evidence-serving endpoint for staff, gated by the same `CasePolicy::view` check as the case itself.
- Pest coverage: queue correctly scoped by department, excludes conflict-of-interest cases; status update requires a note (422); `AuditLog` written on every transition regardless of status; `CaseResolved` fires only on `RESOLVED`.

**Corrections made before this phase closed:**
- **`CasePolicy::claim()` was briefly reduced to just role + `assigned_to === null`, dropping department scoping and the conflict-of-interest exclusion.** The reduction happened chasing a concurrency test that expected a 409 from two *sequential* `postJson()` calls — but Pest/PHPUnit has no real concurrency, so by the second call the first has already committed, and the full Policy correctly (and desirably) denies it with a 403 before the request ever reaches the Service. The 409 path was never reachable through that test to begin with; trimming the Policy didn't fix anything, it just quietly reopened two real holes (cross-department claim, conflict-of-interest case claimable directly by ID). Restored the Policy in full. Split the test in two instead: an HTTP-level test now asserts the correct 403 on a second sequential attempt; a separate Service-level test proves the actual atomic-update race by claiming through two pre-fetched (stale) `CaseRecord` instances directly, bypassing Policy/HTTP, which is the only way to genuinely exercise `CaseAlreadyClaimedException`.

**Not yet handled — confirmed out of scope, not an oversight:**
- No enforcement of a strict status-transition graph (nothing stops `UNDER_INVESTIGATION → DISMISSED → RESOLVED`). Neither the master spec nor the addendum specifies allowed transitions beyond the linear lifecycle in §8. Raised at end of phase, confirmed this doesn't matter for defense — leaving as-is, not revisiting unless asked.

## Phase 9 — Real-time Chat ✅ Complete

**Delivered:**
- `routes/channels.php`: `case.{caseId}` private channel, authorized for two different user types on one channel — a `CaseRecord` (must own the case) or a `User` with `Role::DEPARTMENT_HEAD` (must be `assigned_to` that case). Declared with explicit `['guards' => ['case-api', 'api']]` — see the correction below, this wasn't optional.
- `bootstrap/app.php`: `->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:api,case-api']])`.
- `CaseRecordPolicy::communicate()` — assigned-DH-only, kept separate from `updateStatus` even though the boolean logic is currently identical; different semantic concern, may diverge later.
- `MessageSent` event (`ShouldBroadcast`, `broadcastAs('message.sent')`, explicit `broadcastWith()`), dispatched after the transaction commits, never `->toOthers()` — sender receives their own broadcast, matching the forge lesson from the plan doc.
- `MessageService::send()` — creates the `Message` inside `DB::transaction()`, writes an `AuditLog` entry (`action: MESSAGE_SENT`) in the same transaction, dispatches the event after commit.
- Two separate controllers, not one shared with a role branch: `MessageController` (staff, `auth:api` + `role:DEPARTMENT_HEAD`) and `CaseReporterMessageController` (`auth:case-api`) — mirrors the addendum's deliberate view asymmetry (reporter gets the DH's real name + `presenceStatus`; staff gets nothing extra, since Vue derives `Case<ID>Reporter` client-side from the case ID it already has).
- Routes: staff messaging endpoints inside Phase 8's `role:DEPARTMENT_HEAD` group; reporter's `cases/me/messages` under `auth:case-api`, registered **before** any `/cases/{case}` wildcard route to avoid the routing-order collision flagged when this phase started.
- Pest coverage: DH sends a message; Case Reporter sends a message via `case-api` token (minted with `JWTAuth::fromUser()`, not `auth()->login()` — same Phase 7 rule); broadcast auth succeeds for an assigned DH; broadcast auth succeeds for a case-api token on its own case; broadcast auth rejects a case-api token for a different case.

**Corrections made before this phase closed:**
- **`Broadcast::channel()` needed an explicit `guards` option — this wasn't a style nicety, the channel was actually broken without it.** Without it, the broadcaster resolves `$user` via `$request->user()` with no guard argument, which falls back to the app's *default* guard (`web`) — a session guard. `withBroadcasting()`'s middleware override replaces the whole stack for `/broadcasting/auth` with `auth:api,case-api`, so `StartSession` never runs; the session guard then throws trying to read a session that was never started, surfacing as an uncaught 500. The `auth:api,case-api` middleware authenticating the request and the channel closure resolving `$user` turned out to be two separate resolution paths — fixed by pointing the channel explicitly at `['guards' => ['case-api', 'api']]`, which resolves `$user` via those two stateless JWT guards directly and never touches a session. Any channel added later on this app needs the same explicit `guards` option — the default-guard trap isn't specific to this one channel.
- **Two `postJson('/broadcasting/auth', ...)` test calls needed a `socket_id` field, not because of a security gap but because of what the endpoint actually is.** After `channels.php` authorizes the subscription, Laravel hands off to the Pusher-protocol broadcaster (Reverb speaks that protocol) to sign the response — that signing call takes `socket_id` as a required, typed string param with no Laravel-level validation in front of it. A real client (Echo) always supplies a real `socket_id` as part of establishing the WebSocket connection before it ever calls this endpoint, so the field is never actually optional in production; the test was just the one client that could omit it, which is why it 500'd instead of 401/403'd. Confirmed this doesn't mask the guards fix above — the case that's supposed to fail (`case-api` token for a different case) still fails correctly with no `socket_id` at all, because it's rejected inside the `channels.php` closure before the signing step is ever reached. Added `'socket_id' => '12345.12345'` (a placeholder is fine — this is an HTTP-layer test of the authorization closure, not a live WebSocket round-trip) to the two tests that get past authorization and reach signing.

**Open question carried into Phase 12, not solved here:** nothing in the master spec or addendum says who actually flips `presence_status` between `ONLINE`/`OFFLINE` — Phase 9's job was exposing the field on the reporter's chat payload, not maintaining it. Two realistic options for Phase 12: set it in `AuthService::login()`/`logout()` (cheap, wrong the moment a tab closes without logging out) or wire Reverb presence-channel `here`/`joining`/`leaving` callbacks (correct, but a second channel type to keep in sync with this one). Decide when Phase 12's offline-email trigger actually needs it to be right — not before.

## Phase 10 — Assign Case ✅ Complete

**Delivered:**
- `case-assignments` given its own top-level route prefix rather than nesting further under `/cases/{case}/...` — that prefix already carries `/cases/{case}`, `/cases/{case}/claim`, `/cases/{case}/status`, `/cases/{case}/messages`, and Phase 7's `/cases/me`; a distinct segment sidesteps a repeat of the registration-order issue already hit once.
- `AssignCaseRequest` → `AssignCaseData` DTO → `CaseAssignmentService` → `CaseAssignmentController`, Manager-only via `role:MANAGER` middleware (no Policy needed — matches Phase 3/4's precedent that role-level, non-instance checks stay at the middleware layer).
- `departmentHeadId` validated against `users` scoped to `role = DEPARTMENT_HEAD` — a nonexistent ID or a Manager's own ID both fail with a clean 422 before ever reaching the Service.
- `CaseAssignmentService::assign()` — single-column write, not wrapped in `DB::transaction()` (below the Global Conventions' multi-write threshold); fires `CaseAssigned` (no listener yet — Phase 12). Reassignment falls out for free — `assign()` doesn't branch on whether `assigned_to` was already set.
- **No `AuditLog` entry written** — per the plan doc's explicit instruction, matching the master spec's five-value `AuditAction` enum, which doesn't include assignment.
- `awaitingAssignment()` lists conflict-of-interest cases with `assigned_to IS NULL`; no separate status filter needed since a case can't reach `RESOLVED`/`DISMISSED` without an assigned Department Head in the first place (`CasePolicy::updateStatus` — now `CaseRecordPolicy` — already enforces that).
- Pest coverage: non-Manager forbidden; happy-path assignment sets `assignedTo` and fires the event; no `AuditLog` row created; invalid `departmentHeadId` → 422; reassigning an already-assigned case succeeds; queue listing excludes non-conflict cases and already-assigned ones.

**Not yet handled:** nothing flagged this phase — no surprises, no deviations from the plan doc.

## Phase 11 — Escalation Path ✅ Complete

**Delivered:**
- `escalated_at` (nullable timestamp) added to `case_records` — same shape as the existing `resolved_at` milestone column. Escalation isn't a `status` transition (the case keeps whatever status it had), so there's no enum value to represent it; flagged as a deviation the same way as the other implementation-level additions.
- `CaseRecordPolicy::view()` extended: a Manager passes only when `escalated_at !== null`; a Department Head's branch is unchanged. `claim` and `updateStatus` were left untouched on purpose — escalation grants read access only, never investigative authority.
- `CaseEscalated` event — no listener yet, Phase 12 attaches it, same pattern as `CaseReadyForReview`/`CaseResolved`/`CaseAssigned`.
- `EscalationService::escalate()` — inside `DB::transaction()`: sets `escalated_at`, writes an `AuditLog` entry (`actorType: SYSTEM`, `action: ESCALATED`); event dispatched after commit.
- `CaseReporterEscalationController` (`case-api` guard) — no Request/DTO, matches Phase 7's precedent that an empty-body action isn't worth wrapping.
- `CaseDetailResource` — `escalatedAt` field added.
- **Route retrofit on Phase 8/9's case routes** — the blanket `role:DEPARTMENT_HEAD` group made Manager access to an escalated case unreachable regardless of what the Policy said, since the middleware would 403 first. Split into two groups: a DH-only group (`index`, `claim`, `updateStatus`, message `store`) and a `role:DEPARTMENT_HEAD,MANAGER` group (`show`, `evidence`, message `index`) — the Policy still does the actual per-case gating, this only decides who can reach the controller at all.
- `MessageController::index()` — `authorize()` call changed from `communicate` to `view`; reading the chat log is what escalation grants, sending stays DH-only (`store()` untouched — no `MANAGER` value exists on `SenderType`, out of scope here).
- **Verified, not just assumed:** `EnsureRole` was already variadic (`string ...$roles`) with a correct `in_array` check from Phase 3 — `role:DEPARTMENT_HEAD,MANAGER` worked with zero middleware changes needed.
- Pest coverage: Case Reporter escalates → audit entry + event fired; Manager granted access to an escalated case, denied on an ordinary one; Manager's access extends to that case's chat log; Manager still denied DH-only actions (claim) even on an escalated case.

**Not yet handled — carried forward, not this phase's job:** `EVIDENCE_REVIEWED` still has no write site anywhere in Phases 8–11 despite being one of the five `AuditAction` values. Explicitly Phase 14's responsibility per the plan doc ("confirming each value is actually written where the spec says it should be"), not patched in sideways here.

## Phase 12 — Notifications (In-App + Email) ✅ Complete

**Delivered:**
- New enum `NotificationType` (`app/Enums/`): `CASE_READY_FOR_REVIEW` / `CASE_ASSIGNED` /
  `NEW_MESSAGE` / `CASE_RESOLVED` / `CASE_ESCALATED` — the master spec never enumerated
  `Notification.type`'s values, same shape of implementation delta as Phase 1's
  `Notification.status` enum.
- `presence_status` finally gets a writer — the question Phase 9 carried forward. Set in
  `AuthService::login()`/`logout()`, not Reverb presence-channel callbacks. Known gap
  accepted: stays `ONLINE` if a client disconnects without an explicit logout; fine for
  demo scope, not revisited.
- `NotificationService::record()` — writes the in-app `Notification` row only. Deliberately
  does not send mail itself; email dispatch is a separate `Mail::to()->queue()` call made
  by each Listener, keeping "record" and "mirror to email" independently testable.
- Five `ShouldQueue` Listeners, one per event already firing since Phases 6/8/9/10/11
  (`CaseReadyForReview`, `CaseAssigned`, `MessageSent`, `CaseResolved`, `CaseEscalated`).
  Recipient/condition map:
  - `CaseReadyForReview` → every Dept Head in the case's department, unconditional
  - `CaseAssigned` → the assigned Dept Head, unconditional
  - `MessageSent` → the case's assigned Dept Head, only if sender is `CASE_REPORTER`
    **and** that Dept Head's `presence_status` is `OFFLINE` (DH→Reporter never notifies —
    Reporter has no account/email to notify)
  - `CaseResolved` → every Manager, unconditional
  - `CaseEscalated` → every Manager, unconditional
- Four Mailables: `CaseReadyForReviewMail`, `CaseAssignedMail`, `NewMessageMail`,
  `CaseOutcomeMail` — the last shared by both `CaseResolved`/`CaseEscalated`, parametrized
  by a `$reason` string rather than two near-identical classes.
- Logo: `public/images/verita-logo.png`, wired into
  `resources/views/vendor/mail/html/header.blade.php` (published via
  `vendor:publish --tag=laravel-mail`) — every markdown mail inherits it automatically,
  no per-template markup.
- `GET /notifications` (paginated, own records only) and
  `PATCH /notifications/{notification}` (mark read; 403 on someone else's) —
  `NotificationController` + `NotificationResource`.
- Pest coverage (`NotificationsTest`): one test per trigger point (row + mail queued),
  the two `MessageSent` non-triggering branches (DH online; DH sends instead of reporter),
  and the two endpoint tests (own-notifications-only listing, mark-as-read + forbidden).

**Corrections made before this phase closed:**
- Test draft assumed `CaseAssigned`'s constructor took `($case, $head)` — actual signature
  is `(public readonly CaseRecord $case)`, matching `CaseReadyForReview`/`CaseResolved`/
  `CaseEscalated`'s shared shape. Dropped the extra arg once checked against source.
- **Adding `ShouldQueue` to the five Listeners made all five event-driven tests fail
  silently — 0 notifications, no exception — even though the identical assertions passed
  with `ShouldQueue` removed.** A queued listener's dispatch just pushes onto
  `queue.default` and returns; pushing never throws, so if the effective queue connection
  at test runtime wasn't actually synchronous, every side effect vanishes with no error
  signal beyond the missing assertion. Root mechanism not conclusively isolated (leading
  hypothesis: a stale `bootstrap/cache/config.php` from an earlier `config:cache` run,
  overriding `.env.testing`'s `QUEUE_CONNECTION=sync`) — fixed by pinning explicitly in
  `NotificationsTest`'s `beforeEach()` rather than by confirming the cache theory:
```php
  beforeEach(fn () => config([
      'queue.default' => 'sync',
      'broadcasting.default' => 'null',
  ]));
```
  `broadcasting.default` was pinned in the same call; whether it was actually load-bearing
  for *this* failure (versus `queue.default` alone) wasn't isolated separately — both
  changed together and all ten tests pass. Worth a separate check later if it matters
  elsewhere, not urgent now.
- **General lesson for later phases:** this is very likely the first place in the project
  a queued Listener's real dispatch path (not a directly-called `handle()`, the way Phase 6's
  AI job test almost certainly worked) got exercised end-to-end in a test. Any future test
  asserting on a `ShouldQueue` side effect should pin `queue.default` explicitly rather than
  trust ambient `.env.testing` state.

**Not yet handled — confirmed out of scope, not an oversight:**
- `NotificationChannel::IN_APP` (no email mirror) is defined but never produced by this
  phase's Listeners — every trigger implemented here is one of the master spec's four
  Gmail-mirrored trigger points, so `IN_APP_AND_EMAIL` is the only value this code path
  ever writes. Matches the spec's framing (those four *are* what generates a notification
  at all), not a gap.

## Phase 13 — Manager Analytics (Generate User Engagement Report) ✅ Complete

**Delivered:**
- `AnalyticsService::generateUserEngagementReport()` — three straight DB aggregates, no
  AI, no Repository layer (Global Conventions reserve Repository for `Cache::`/`Redis::`
  ownership specifically; this is a plain query against the Model):
  - `caseVolumeByDepartment` — `Department::withCount('cases')`, mapped to
    `{department, count}`.
  - `averageResolutionDays` — `AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 86400)`
    over cases where `resolved_at` is set; overall figure, not per-department, matching
    the plan doc's singular wording. Returns `null` (not `0.0`) when nothing's resolved
    yet — a fresh seed shouldn't misreport as "resolved instantly."
  - `categoryBreakdownOverTime` — grouped by calendar month (`to_char(created_at, 'YYYY-MM')`)
    × `category`. Only `FRAUD` will ever appear given current scope, but the query itself
    doesn't hardcode that — Harassment/Security/OTHER fall out for free if ever built.
- `UserEngagementReportController` — single-action/invokable, matches Phase 11's
  `CaseReporterEscalationController` precedent for a one-coherent-action endpoint.
- `GET /reports/user-engagement`, `role:MANAGER` middleware — no Policy needed, same
  role-level-not-instance-level precedent as Phases 3/4/10.
- **No dedicated `JsonResource`** — the Service already returns a hand-built aggregate
  array; a Resource would just pass it through unchanged. Wrapped manually under `data`
  to match the shape every other endpoint's tests assume. Same "not worth wrapping" call
  as Phase 7/11's Request/DTO skips.
- **No caching added** — dataset's small for a defense demo, query is cheap. Revisit only
  if this becomes a real cost.
- Pest coverage (`AnalyticsTest`): non-Manager forbidden; case volume + average resolution
  match hand-computed figures against seeded data; `null` average when nothing's resolved;
  category/month breakdown matches hand-computed counts.

**Corrections made before this phase closed:**
- **`assertJsonPath('data.averageResolutionDays', 3.0)` failed with "3 is identical to
  3.0" despite the Service correctly computing `3.0`.** Not a logic bug — PHP's
  `json_encode()` drops the decimal point on any whole-number float unless
  `JSON_PRESERVE_ZERO_FRACTION` is passed, so `3.0` went over the wire as `3`; the test's
  `assertJsonPath()` does a strict `===` compare, and `json_decode('3')` comes back an
  `int`. Not actually a problem for the real consumer — JSON/JS have no int/float
  distinction, Vue renders `3` and `3.0` identically — but worth fixing anyway so the
  field doesn't silently change shape (`3` some days, `4.2` others) if the frontend ever
  formats it as always-one-decimal text. Fixed by passing the flag on this one response
  only (`response()->json($data, 200, [], JSON_PRESERVE_ZERO_FRACTION)`), not globally —
  nothing else in the app currently emits a bare computed float.

**Not yet handled:** nothing flagged this phase — assumption about `Department::cases()`
already existing from Phase 1 held with no changes needed.
