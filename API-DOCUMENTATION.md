# Verita API — Consumer Documentation

This is the implementation-derived contract for the Vue 3/TypeScript app and Bruno collection. It reflects the route table and source as audited on 2026-09-20; the planning documents are not the runtime contract.

## Connect

- Base URL: `{APP_URL}/api/v1` (for example, `http://localhost:8000/api/v1`).
- Send `Accept: application/json` on every request and `Content-Type: application/json` unless uploading files.
- All identifiers are UUID strings. Dates/times are Laravel JSON date values in UTC; `transactionDate` is a date.
- JSON uses camelCase except profile updates, which use `first_name`, `last_name`, and `profile_picture`.
- `GET /ping` is public and returns `{ "status": "ok" }`.

There is no repository-level CORS configuration file. Configure allowed Vue origins at deployment/proxy level before calling the API from a different origin.

## Authentication and roles

Staff authenticate with `POST /auth/login` and use `Authorization: Bearer <staffToken>`. Staff tokens carry either `MANAGER` or `DEPARTMENT_HEAD` access.

Anonymous case reporters first submit a case, retain its one-time tracking PIN, then exchange the pair with `POST /cases/{caseId}/verify-pin`. Use the returned case token as `Authorization: Bearer <caseToken>` only with `/cases/me/*` endpoints.

The active IP-based limits are: login 50/min, case submission 30/min, and PIN verification 10/min.

## Errors

API failures use RFC 9457-style problem details:

```json
{
  "type": "about:blank",
  "title": "Validation failed",
  "status": 422,
  "detail": "One or more fields failed validation.",
  "instance": "api/v1/cases",
  "errors": { "departmentId": ["The department id field is required."] }
}
```

Expect 401 for absent/invalid credentials, 403 for a valid token without permission, 404 for an unknown or intentionally hidden resource, 409 for a claim race, and 422 for validation. Successful single resources and collections are normally wrapped in `data`; exceptions are explicitly shown below.

## Values used by clients

| Field | Values |
| --- | --- |
| `role` | `MANAGER`, `DEPARTMENT_HEAD` |
| `status` | `SUBMITTED`, `AI_PROCESSING`, `AWAITING_REVIEW`, `UNDER_INVESTIGATION`, `RESOLVED`, `CLOSED`, `DISMISSED` |
| `category` | `FRAUD`, `HARASSMENT`, `SECURITY`, `OTHER` (new submissions are always `FRAUD`) |
| evidence `fileType` | `IMAGE`, `PDF` |
| message `senderType` | `CASE_REPORTER`, `DEPARTMENT_HEAD` |
| notification `status` | `UNREAD`, `READ` |
| notification `type` | `case_ready_for_review`, `case_assigned`, `new_message`, `case_resolved`, `case_escalated` |
| presence | `ONLINE`, `OFFLINE` |

## Public case-reporter endpoints

| Method and path | Body / headers | Result |
| --- | --- | --- |
| `POST /cases` | `Idempotency-Key` required; multipart fields below | `201 { data: { caseId, trackingPin, status } }` |
| `POST /cases/{caseId}/verify-pin` | `{ "pin": "123456" }` | `{ data: { token } }` |
| `GET /cases/me` | case token | reporter case dashboard |
| `POST /cases/me/evidence` | multipart `evidence[]` | updated reporter dashboard; AI is reprocessed |
| `GET /cases/me/evidence/{evidenceId}` | case token | private file stream |
| `GET /cases/me/messages` | case token | reporter-visible Department Head info and messages |
| `POST /cases/me/messages` | `{ "content": "..." }` | `201 { data: message }` |
| `POST /cases/me/escalate` | case token | `{ "escalatedAt": "..." }` |

`POST /cases` must be `multipart/form-data` and requires:

```text
departmentId              UUID
description               string
purposeOfTransaction      string, max 255
amountInvolved            number >= 0
personInvolved            string, max 255
transactionDate           valid date
concernsDepartmentHead    boolean
evidence[]                one or more JPG/JPEG/PNG/PDF files, each <= 10 MB
```

Store the returned `caseId` and `trackingPin` outside ordinary UI state until the reporter has verified the PIN. Reusing the same `Idempotency-Key` for the same POST path returns the cached successful response; use a fresh UUID for a new submission.

Reporter dashboard fields are `caseId`, `status`, `description`, `purposeOfTransaction`, `amountInvolved`, `personInvolved`, `transactionDate`, `evidence`, and `aiProcessingFailed`. `aiSummary`, `aiTimeline`, and `aiFindings` are omitted while AI processing is pending or failed. Each evidence item has `id`, `fileType`, `uploadedAt`, and `downloadUrl`; call the URL with the case token.

`GET /cases/me/messages` is deliberately not `data`-wrapped:

```json
{
  "departmentHead": { "name": "Ada Lovelace", "presenceStatus": "OFFLINE" },
  "messages": { "data": [{ "id": "…", "senderType": "CASE_REPORTER", "content": "…", "sentAt": "…" }] }
}
```

`departmentHead` is `null` before assignment. Message content is required and limited to 2,000 characters.

## Staff endpoints

### Account and notifications (any staff token)

| Method and path | Body | Result |
| --- | --- | --- |
| `POST /auth/login` | `email`, `password` | `{ token, user }` |
| `POST /auth/logout` | — | `204` |
| `PUT /account/profile` | optional `first_name`, `last_name`, `email`, `password`, `password_confirmation`, `profile_picture` | `{ data: user }` |
| `GET /account/dashboard` | — | role-specific plain JSON object |
| `GET /notifications` | — | paginated `{ data, links, meta }` |
| `PATCH /notifications/{notificationId}` | — | `204` |

Profile image upload is multipart and accepts an image up to 2 MB. Passwords need a confirmation and at least 8 characters. A Department Head dashboard returns `role`, `department`, and `assignedCaseCount`; a Manager dashboard returns `role`, `departmentCount`, and `userCount`.

`user` contains `id`, `firstName`, `lastName`, `email`, `role`, `departmentId`, `staffId`, `profilePicture`, and `presenceStatus`. Notifications contain `id`, `type`, `title`, `message`, `status`, `channel`, and `sentAt`.

### Manager-only

| Method and path | Body / result |
| --- | --- |
| `GET /departments` | collection of `{ id, name }` |
| `POST /departments` | `{ name }` → `201 { data: department }` |
| `PUT /departments/{departmentId}` | `{ name }` → `{ data: department }` |
| `DELETE /departments/{departmentId}` | `204` |
| `GET /department-heads` | collection of users |
| `POST /department-heads` | `firstName`, `lastName`, `email`, `password`, `departmentId` → `201 { data: user }` |
| `PUT` or `PATCH /department-heads/{departmentHeadId}` | any of the preceding fields → `{ data: user }` |
| `DELETE /department-heads/{departmentHeadId}` | `204` |
| `GET /case-assignments` | unassigned conflict-of-interest case collection |
| `POST /case-assignments/{caseId}` | `{ departmentHeadId }` → `{ data: case }` |
| `GET /reports/user-engagement` | `{ data: analytics }` |

Department names are required, unique strings up to 255 characters. A Department Head password is at least 8 characters. `departmentHeadId` must identify a Department Head, although the implementation does not require that head to belong to the case's department.

Analytics is shaped as:

```json
{
  "data": {
    "caseVolumeByDepartment": [{ "department": "Finance", "count": 4 }],
    "averageResolutionDays": 3.0,
    "categoryBreakdownOverTime": [{ "month": "2026-09", "category": "FRAUD", "count": 4 }]
  }
}
```

### Department Head case work

| Method and path | Result / constraints |
| --- | --- |
| `GET /cases` | queue for the caller's department: only `AWAITING_REVIEW`, non-conflict cases |
| `GET /cases/{caseId}` | case detail if assigned to caller or still eligible to claim |
| `POST /cases/{caseId}/claim` | atomically assigns caller and changes status to `UNDER_INVESTIGATION` |
| `PATCH /cases/{caseId}/status` | `{ status, note, resolutionSummary? }` → `{ data: case }` |
| `GET /cases/{caseId}/evidence/{evidenceId}` | authorized private file stream |
| `GET /cases/{caseId}/messages` | `{ messages: { data: [...] } }` |
| `POST /cases/{caseId}/messages` | `{ content }` → `201 { data: message }` |

Every status update needs a `note` (max 2,000). `resolutionSummary` is additionally required for `RESOLVED` and `DISMISSED`. The API does not enforce a status-transition graph, so the UI must only offer transitions appropriate to its workflow.

Managers can `GET /cases/{caseId}`, its evidence, and its messages only after a reporter has escalated that case; they cannot claim, change status, or send messages.

Staff case fields are `id`, `category`, `status`, `description`, `purposeOfTransaction`, `amountInvolved`, `personInvolved`, `transactionDate`, `concernsDepartmentHead`, `assignedTo`, `resolutionSummary`, `createdAt`, `resolvedAt`, `escalatedAt`, `evidence`, `aiSummary`, `aiTimeline`, `aiFindings`, and `aiProcessingFailed`.

The `downloadUrl` nested in a staff case's evidence object currently points to the reporter-only `/cases/me/evidence/{id}` route. Do not use it from staff UI; construct the documented staff evidence path using the case ID and evidence ID.

## Real-time messaging — Laravel Reverb and Echo (Vue)

Messaging is also a WebSocket contract, not only REST. The backend broadcasts through
Laravel Reverb using the Pusher protocol; the frontend client uses `laravel-echo` and
`pusher-js`. Configure `BROADCAST_CONNECTION=reverb` on the API and run both:

```bash
php artisan queue:work    # MessageSent implements ShouldBroadcast
php artisan reverb:start
```

### Private channel and authorization

Subscribe through Echo to the private channel `case.{caseId}`:

```ts
window.Echo.private(`case.${caseId}`)
```

Laravel automatically authorizes that subscription at **`POST /broadcasting/auth`**.
This endpoint is intentionally outside `/api/v1`; do not call `/api/v1/broadcasting/auth`.
It has `auth:api,case-api` middleware and needs the Pusher-generated `socket_id` and
`channel_name`, which Echo sends automatically. The client must add the currently active
Bearer token to that authorization request.

| Subscriber | Authorized when |
| --- | --- |
| Case reporter | authenticated with `case-api` and the token's `caseId` matches the channel case ID |
| Department Head | authenticated with `api`, has role `DEPARTMENT_HEAD`, and is assigned to that case |
| Manager or unassigned Department Head | never authorized for the channel |

For a Vue client that does not use the backend's bundled `resources/js/echo.js`, configure
the authorization endpoint and header explicitly. Swap the header token when switching
between staff and reporter contexts:

```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: `${import.meta.env.VITE_API_ORIGIN}/broadcasting/auth`,
  auth: {
    headers: { Authorization: `Bearer ${activeToken}` },
  },
})
```

`VITE_API_ORIGIN` is the API origin (for example, `http://localhost:8000`), not the
`/api/v1` base URL. When the Vue and API applications use different origins, the HTTP
server/proxy must allow the Vue origin for `broadcasting/auth`; the repository currently
has no `config/cors.php` policy to do this.

### Events

The only broadcast event currently exposed is `MessageSent`, emitted after a message is
stored. It uses `broadcastAs('message.sent')`, so Echo listeners must include the leading
dot:

```ts
echo.private(`case.${caseId}`)
  .listen('.message.sent', (message: CaseMessage) => {
    // merge by message.id
  })
```

```json
{
  "id": "…",
  "caseId": "…",
  "senderType": "DEPARTMENT_HEAD",
  "content": "…",
  "sentAt": "…"
}
```

The sender receives its own broadcast. Merge/deduplicate REST responses and WebSocket
events by message `id`; do not assume the event excludes the sending client. Disconnect
or leave the private channel when the user changes case, logs out, or destroys the chat view.

## Bruno workspace collection guidance

Build the Verita collection as a workspace collection, following the referenced workshop's collection-level scripts, named environments, authentication chaining, and CLI-runner layout. Keep secrets and tokens in Bruno environments, not request files or committed examples. The workshop demonstrates collection-level pre-request scripts, environment selection, token chaining, and CLI execution/reporting. [Workshop repository](https://github.com/bruno-collections/Webinar-Best-Practices-Feb-2026)

Create at least `Local`, `Demo`, and `CI` environments with `baseUrl`, `staffEmail`, `staffPassword`, `staffToken`, `caseId`, `trackingPin`, `caseToken`, `departmentId`, `departmentHeadId`, and `idempotencyKey`. Mark credentials/tokens secret. Organize requests into `Health`, `Staff Auth`, `Manager`, `Reporter`, `Department Head`, `Notifications`, and `Realtime/Auth` folders.

Use scripts deliberately:

- A collection-level pre-request script can log the request and add `Accept: application/json`; requests needing authentication should inherit `Authorization: Bearer {{staffToken}}` or `{{caseToken}}` from their folder.
- The login post-response script should persist `res.body.token` as `staffToken`.
- The submit-case post-response script should persist `res.body.data.caseId` and `res.body.data.trackingPin`; its pre-request script should generate a fresh UUID `idempotencyKey` only when testing a new submission. Keep it unchanged for the replay test.
- The verify-PIN post-response script should persist `res.body.data.token` as `caseToken`.
- Add assertions for status codes, problem-details fields, `data` wrappers, and the AI state progression. File-upload requests must use Bruno's multipart body editor and key `evidence[]`.

For CI, seed a disposable database before `bru run`, select the `CI` environment, use a JSON data file for repeatable inputs where useful, and emit JSON/HTML reports. Do not run a collection against shared demo data if it creates/deletes departments or accounts.

## Operational dependencies

The full experience requires the app server, a queue worker (AI processing and queued notifications), and Reverb (chat). The database is PostgreSQL, cache/queue use Redis, Gemini is needed for live AI structuring, and SMTP is needed for mirrored mail. `php artisan demo:reset --force` resets the local demo data.
