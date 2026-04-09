# HarborBite System Design

## 1) Purpose and Context

HarborBite is an offline-first, on-premise restaurant ordering platform built as a modular monolith. It supports:

- Guest kiosk ordering (menu search, cart, checkout, order tracking)
- Staff operations (order lifecycle transitions, settlement)
- Admin operations (menu/promotions/users/security management, analytics, alerts)
- Risk and fraud controls (fingerprinting, rate limits, blacklist/whitelist, CAPTCHA triggers, immutable audit logs)

Primary stack:

- Backend: Laravel 13 (PHP 8.3), Livewire 4
- Frontend: Blade + Livewire + Alpine + Tailwind (Vite build)
- Database: PostgreSQL 16 (primary), SQLite fallback in tests/dev with degraded feature parity
- Containerization: Docker Compose (app + nginx + postgres + optional Playwright)

Design intent: maintain one authoritative write path for critical state changes (orders, payments, discounts) while preserving kiosk usability and local network operability.

---

## 2) Architecture Style

### 2.1 Modular Monolith with Hexagonal Influence

Code is organized into four principal layers:

- `app/Domain`: pure business logic (state machine, promotion evaluation, HMAC, risk rules)
- `app/Application`: orchestration/use cases (create order, transition order, payment confirmation, analytics)
- `app/Infrastructure`: persistence adapters, logging formatters, internal API dispatcher
- `app/Http` + `app/Livewire`: delivery layer (REST controllers, middleware, UI components)

This gives clear responsibility boundaries without introducing distributed-systems complexity.

### 2.2 Runtime Components

- **Nginx**: HTTP ingress (`:8080`)
- **PHP-FPM app**: Laravel runtime, queue/listeners as needed
- **PostgreSQL**: transactional data, logs, analytics, metrics
- **Browser clients**: kiosk and staff/admin UIs

### 2.3 Why This Shape

- Keeps all critical invariants in one deployable unit (order/payment coupling, audit logs)
- Supports strict transactional operations with DB locking + optimistic concurrency
- Allows REST API and Livewire UI to share same business rules

---

## 3) Request Lifecycle and Control Plane

Middleware stack (global append in `bootstrap/app.php`) runs in this order:

1. `TraceIdMiddleware` - inject/propagate `X-Trace-ID`
2. `DeviceFingerprintMiddleware` - computes fingerprint from UA + screen headers, persists/updates device record, enforces blacklist checks
3. `RateLimitMiddleware` - per-device and per-IP throttling with whitelist bypass
4. `RequestLoggingMiddleware` - structured logs + `request_metrics` persistence
5. `AnalyticsTrackingMiddleware` - tracks successful non-API page views
6. `ForcePasswordChangeMiddleware` - blocks flagged users until password update

Per-route middleware adds additional constraints:

- `auth` for staff APIs/pages
- `role:*` for role-gated actions
- `rate-limit:registration|checkout|login` for tighter endpoint-level throttles

Important behavior:

- API routes always return JSON errors for auth/role failures
- Security checks are enforced for both API and UI traffic (middleware is global)

---

## 4) Domain Model and Core Invariants

### 4.1 Roles

Roles: `cashier`, `kitchen`, `manager`, `administrator`.

- Staff routes: all roles
- Manager routes: manager + admin
- Admin routes: admin only
- Payment API: cashier/manager/admin (kitchen excluded)

### 4.2 Order Lifecycle

Order states (`OrderStatus`):

- `pending_confirmation`
- `in_preparation`
- `served`
- `settled`
- `canceled`

Allowed transitions:

- pending -> in_preparation|canceled
- in_preparation -> served|canceled
- served -> settled|canceled
- settled/canceled terminal

Role gates on target state:

- to `in_preparation`: cashier/manager/admin
- to `served`: kitchen/manager/admin
- to `settled`: cashier/manager/admin (but payment-confirmed prerequisite enforced)
- to `canceled`: cashier/manager/admin, with step-up PIN if canceling from `in_preparation` or `served`

Concurrency rule:

- All transitions require `expected_version`; stale version returns conflict semantics.

### 4.3 Settlement Invariants

- Direct settlement is forbidden unless confirmed payment intent exists.
- Payment confirmation routes settlement through `TransitionOrderUseCase` so state machine checks still apply.
- Ambiguous settlement (amount mismatch or non-served state) requires manager/admin role + correct manager PIN.

### 4.4 Promotion Invariants

- Active promotion window: `starts_at <= now <= ends_at`, `is_active = true`
- Evaluator supports `percentage_off`, `flat_discount`, `bogo`, `percentage_off_second`
- Best-offer-wins: only one promotion applied after exclusion-group filtering
- Automatic promotions are applied atomically during order creation
- Manual discount override is separate and step-up-gated when amount > $20

### 4.5 Cart and Pricing Invariants

- Cart is session-bound (`carts.session_id`)
- Order creation validates cart ownership against server session
- Cart items keep `unit_price_snapshot` for stable checkout math
- Notes are encrypted at rest
- Quantity bounds: 1..99
- Note length bound: <= 140 chars (before encryption)

---

## 5) Data Architecture

### 5.1 Core Transactional Tables

- Identity: `users`, `sessions`
- Risk: `device_fingerprints`, `security_blacklists`, `security_whitelists`, `rule_hit_logs`, `privilege_escalation_logs`
- Catalog/search: `menu_categories`, `menu_items`, `trending_searches`, `banned_words`, `tax_rules`
- Cart/order: `carts`, `cart_items`, `orders`, `order_items`, `order_status_logs`
- Promotions: `promotions`, `applied_promotions`
- Payments/reconciliation: `payment_intents`, `payment_confirmations`, `incident_tickets`
- Observability/analytics: `analytics_events`, `admin_alerts`, `request_metrics`

### 5.2 PostgreSQL-Specific Enhancements

- Full-text generated `search_vector` on `menu_items` + GIN index
- GIN index for JSONB `attributes`
- Partitioned `analytics_events` by month
- Immutable audit log enforcement via `prevent_modification()` trigger function on:
  - `rule_hit_logs`
  - `privilege_escalation_logs`
  - `order_status_logs`

SQLite fallback exists but does not provide full parity for PG-native features.

### 5.3 Encryption and Sensitive Fields

Encrypted at rest (application-layer crypt):

- `cart_items.note`
- `order_items.note`
- `payment_confirmations.notes`
- `incident_tickets.receipt_reference`
- `device_fingerprints.user_agent`
- `device_fingerprints.screen_traits`

Operational key rotation command re-encrypts these fields.

---

## 6) API and UI Integration Strategy

Livewire components call REST endpoints through `InternalApiDispatcher` (`/api/*`) instead of writing DB state directly for critical operations. This provides:

- Shared validation and authorization path
- Consistent error contracts
- Reduced drift between browser-driven and API-driven behavior

Examples:

- Cart Livewire -> `/api/cart/*`
- Checkout Livewire -> `/api/orders`, `/api/cart`
- Staff discount override UI -> `/api/orders/{id}/discount`

---

## 7) Security and Fraud Controls

### 7.1 Fingerprinting

- Fingerprint hash = SHA-256(salt + normalized UA + screen traits)
- Screen traits injected from frontend headers (`X-Screen-Width`, etc.)
- Device records are upserted and last-seen updated each request

### 7.2 Blacklist/Whitelist Policy

Blacklist dimensions:

- device hash
- IP/CIDR
- username

Whitelist bypasses rate limiting for:

- device
- IP/CIDR
- username

Blacklist denials return 403 with `BLACKLISTED` and log immutable rule hits.

### 7.3 Rate Limiting

Action windows:

- `registration`: default 10/hour
- `checkout`: default 30/10 minutes
- `login`: 10/minute
- `general`: default 60/minute

Applied per device and per IP (whitelist bypass). Exceeded limits return 429 + `Retry-After`.

### 7.4 CAPTCHA Triggers

- Failed login threshold (default 5)
- Rapid repricing threshold (default 3 events in 60s window)

Rapid repricing events are recorded only on real pricing input changes:

- menu price drift
- tax-rule changes affecting totals
- promotion-effective-total changes at checkout

### 7.5 Payment Integrity

- HMAC-SHA256 over deterministic payload + nonce + timestamp
- Nonce expiry window default 300s
- Stored `signed_at` used for verification stability
- Nonce replay protection via `nonce_used_at`
- Idempotent confirm behavior for already-confirmed intents

### 7.6 Forced Credential Rotation

Seeded users are created with `force_password_change=true`.

- Web: redirected to password change route
- API: blocked with 403 `FORCE_PASSWORD_CHANGE`

---

## 8) Observability and Operations

### 8.1 Logging

- Structured JSON logs via custom Monolog formatter
- Sensitive context keys redacted (`password`, `pin`, `token`, `note`, etc.)
- Daily rotation with configurable retention (`LOG_DAILY_DAYS`, default 14)

### 8.2 Request Metrics

Every request records method/path/status/duration into `request_metrics` for alerting queries.

### 8.3 Alerting

Scheduled command (`harborbite:check-alerts`) checks:

- failed login count
- risk hit volume
- API 5xx rate
- API p95 latency

Alerts persist in `admin_alerts` with deduping against unacknowledged recent alerts.

### 8.4 Reconciliation

Scheduled command (`harborbite:reconcile-payments`) finds:

- served orders with confirmed payment but not settled -> open incident ticket + alert
- expired pending payment intents -> mark failed

Manager reconciliation UI can resolve tickets and attempt settlement if payment evidence is valid.

---

## 9) Deployment and Runtime Model

Docker Compose services:

- `app` (Laravel/PHP-FPM)
- `nginx` (public endpoint)
- `postgres` (data store)
- optional `playwright` profile for E2E tests

Startup path (`docker/app/entrypoint.sh`):

1. wait for DB
2. run migrations
3. seed data
4. build assets if needed
5. clear stale Laravel caches

Critical startup secret checks in `AppServiceProvider` (non-testing):

- `PAYMENT_HMAC_KEY` required
- `DEVICE_FINGERPRINT_SALT` required

---

## 10) Testing Strategy

Three test layers are present:

- Unit tests (domain logic)
- Feature tests (API, middleware, use case integration, security behavior)
- E2E Playwright tests (guest/staff/admin/payment flows)

Notable assertions covered by tests:

- JSON error contract consistency for API auth/role failures
- optimistic concurrency conflict behavior
- settlement preconditions and ambiguous step-up paths
- data minimization on guest tracking endpoint
- blacklist/whitelist + CIDR semantics
- redaction of sensitive logs

---

## 11) Known Tradeoffs and Constraints

- SQLite fallback uses LIKE and JSON extraction; PostgreSQL gives full-text + JSONB GIN + immutable triggers.
- Some cart item cross-ownership update/delete calls return 200 no-op rather than explicit 403/404 (behavior is safe but less explicit).
- Internal API dispatcher uses in-process request dispatch; good for consistency, but not a substitute for external API gateway concerns.
- Payment ambiguity logic intentionally favors operational safety over speed (step-up required for mismatches/non-standard state).

---

## 12) Extension Points

Likely future evolution areas:

- stronger reconciliation state machine (`reconciling` workflow lifecycle)
- richer device fingerprint entropy and risk scoring
- explicit API contract versioning (`/api/v1`)
- background analytics aggregation/materialized views
- external payment processor adapter while keeping HMAC/domain invariants
