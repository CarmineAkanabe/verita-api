# Verita API

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Backend API for **Verita**, an enterprise-grade anonymous workplace-reporting, ethics consultation, and case-management platform. It provides cryptographic anonymity for Case Reporters, role-partitioned JWT access for investigators, asynchronous AI triage via Gemini, real-time WebSocket messaging via Laravel Reverb, and ISO 37002-compliant immutable audit logging.

**Lead Developer & Architect:** [Carmine Akanabe](https://github.com/CarmineAkanabe)  
**Repository:** [https://github.com/CarmineAkanabe/verita-api](https://github.com/CarmineAkanabe/verita-api)

---

## Key Features

- **Anonymous Case Intake**: Case Reporters file incident reports with zero personal metadata retention. A cryptographic one-time tracking PIN is generated for secure follow-up.
- **Role-Partitioned Access**: Air-gapped JWT guards separating anonymous reporters from staff (`DEPARTMENT_HEAD`, `MANAGER`).
- **AI Case Analysis (Gemini 2.5 Flash)**: Background worker automatically structures narratives, generates executive summaries, extracts key contradictions, and synthesizes event timelines.
- **Real-Time Communication (Laravel Reverb)**: Asymmetric private WebSocket channels allowing secure, two-way dialogue between anonymous reporters and assigned Department Heads.
- **Cryptographic Audit Ledger**: Immutable audit trail logging status changes, investigator notes, evidence inspections, and AI events.
- **Enterprise Notifications**: Branded, anti-spam optimized transactional mailers for case assignments, readiness reviews, outcomes, and new messages.
- **Manager Governance Console**: Executive analytics, department CRUD, personnel provisioning, and case assignment dispatch.

---

## Documentation

| Document                                                                          | Description                                                                                                                                        |
| --------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| [API-DOCUMENTATION.md](API-DOCUMENTATION.md) / [API-DOCUMENT.md](API-DOCUMENT.md) | Comprehensive consumer contract for Vue 3 and Bruno: all endpoints, request/response schemas, auth guards, WebSocket events, and error structures. |
| [PROGRESS.md](PROGRESS.md)                                                        | Chronological development log, architectural decisions, and modification audits.                                                                   |

---

## System Requirements

- **PHP**: 8.3 or higher (with `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`)
- **Composer**: 2.x
- **Database**: PostgreSQL (or SQLite for testing)
- **Cache/Queue**: Redis (using Predis client)
- **Real-time Server**: Laravel Reverb
- **AI Integration**: Google Gemini API key

---

## Quick Start

### 1. Installation
```bash
git clone https://github.com/CarmineAkanabe/verita-api.git
cd verita-api
composer install
```

### 2. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure your `.env` with your database credentials, Gemini API key, and frontend URL:
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=verita_db
DB_USERNAME=postgres
DB_PASSWORD=secret

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis

GEMINI_API_KEY=your_gemini_api_key
```

### 3. Migrations & Seeding
```bash
php artisan migrate --seed
```

### 4. Running the Stack
In separate terminal tabs:
```bash
# 1. API Web Server
php artisan serve --port=8000

# 2. Asynchronous Queue Worker (AI Processing & Mailers)
php artisan queue:work

# 3. Real-time Reverb WebSocket Server
php artisan reverb:start --port=8080
```

---

## API Overview

### Public & Anonymous Reporter Endpoints
- `GET  /api/v1/ping` — Server health probe
- `POST /api/v1/cases` — Submit anonymous incident report
- `POST /api/v1/cases/{id}/verify-pin` — Exchange Case ID + PIN for session JWT
- `GET  /api/v1/cases/me` — Reporter case dashboard & AI review
- `POST /api/v1/cases/me/evidence` — Upload supplementary evidence
- `GET  /api/v1/cases/me/messages` — Fetch dialogue messages
- `POST /api/v1/cases/me/messages` — Post message to investigating officer
- `POST /api/v1/cases/me/escalate` — Escalate case to Executive Management

### Staff Endpoints (Department Head & Manager)
- `POST  /api/v1/auth/login` — Staff credential authentication
- `POST  /api/v1/auth/logout` — Terminate staff session
- `GET   /api/v1/account/dashboard` — Staff metrics & triage summary
- `PUT   /api/v1/account/profile` — Update name & avatar
- `GET   /api/v1/cases` — Investigation queue (department or manager view)
- `GET   /api/v1/cases/{id}` — Case detail & forensic evidence
- `POST  /api/v1/cases/{id}/claim` — Claim case into active investigation
- `PATCH /api/v1/cases/{id}/status` — Transition status with mandatory note
- `GET   /api/v1/cases/{id}/audit-logs` — Chronological case history & audit events
- `GET   /api/v1/audit-logs` — Organization-wide / departmental audit ledger
- `GET   /api/v1/cases/{id}/messages` — View consultation thread
- `POST  /api/v1/cases/{id}/messages` — Send message to anonymous reporter

### Manager-Only Endpoints
- `GET/POST/PUT/DELETE /api/v1/departments` — Department management
- `GET/POST/PUT/DELETE /api/v1/department-heads` — Personnel provisioning
- `GET/POST /api/v1/case-assignments` — Assign unassigned cases to officers
- `GET /api/v1/reports/user-engagement` — Executive analytics & compliance reporting

---

## Security & Architecture Standards

1. **Air-Gapped Privacy**: Anonymous reports have no foreign keys to user accounts or tracking IP records.
2. **Atomic Status Transitions**: Case claiming and status updates execute in strict database transactions.
3. **ISO 37002 Alignment**: Four-stage Case Reporting lifecycle (Receiving, Assessing, Addressing, Concluding).
4. **Deliverability Protection**: Transactional emails carry anti-spam headers (`X-Entity-Ref-ID`, `X-Auto-Response-Suppress`) and high-density text formats to guarantee inbox delivery.

---

## License
Open-source under the [MIT License](LICENSE). Built for academic defense and enterprise demonstration.