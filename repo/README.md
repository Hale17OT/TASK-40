# HarborBite — Offline Restaurant Ordering & Risk Management System

**Project Type:** Fullstack web application (server-rendered UI + REST API, single deployment).

An offline-first, on-premise restaurant ordering system with promotion-aware checkout, secure payment capture, and end-to-end fraud controls. Built with Laravel 13 + Livewire 4 + Alpine.js + Tailwind CSS + PostgreSQL, designed to run entirely on a local restaurant network.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose v2)
- No other dependencies required

## Quick Start

```bash
# Start services (no .env file needed — secrets are auto-generated at runtime)
docker-compose up --build -d
```

> Docker Compose v2 and v1 are both supported; `docker compose up` (space) and `docker-compose up` (hyphen) are equivalent here.

> **Note:** `APP_KEY`, `PAYMENT_HMAC_KEY`, and `DEVICE_FINGERPRINT_SALT` are automatically generated on first startup. To provide your own values, set them as environment variables before running `docker-compose up`.

App available at **http://localhost:8080**

First startup runs migrations and seeds automatically.

## Verify the App Is Running

Once containers are up, verify the system is working:

1. **Kiosk UI** — Open `http://localhost:8080/` in a browser. You should see the menu browser with seeded items (burgers, salads, drinks).
2. **REST API health** — `curl http://localhost:8080/api/time-sync` returns JSON with current server time.
3. **Menu API** — `curl http://localhost:8080/api/menu/search` returns seeded menu items.
4. **Staff login** — Navigate to `http://localhost:8080/login`, sign in with the `admin` credentials below. You will be forced through the password-change flow on first login.
5. **Order a meal** — From the kiosk UI, add items to cart → checkout → pay. You will receive a tracking URL (`/order/{token}`) that polls order status in real time.

### Default Credentials (Non-Production Only)

> **WARNING:** These are seed-only credentials for initial setup. All seeded accounts require a mandatory password and PIN change on first login. Do NOT use these in production without completing the forced rotation flow.

| Role          | Username  | Password     | Manager PIN |
|---------------|-----------|--------------|-------------|
| Administrator | admin     | admin123     | 9999        |
| Manager       | manager   | manager123   | 1234        |
| Cashier       | cashier   | cashier123   | -           |
| Kitchen       | kitchen   | kitchen123   | -           |

## Testing

```bash
./run_tests.sh
```

Runs three test stages:
1. **Unit tests** — Pure PHP domain logic (no DB required)
2. **Feature/Integration tests** — SQLite in-memory (`phpunit.xml`). PG-specific tests (full-text search, partitioned tables) are skipped under SQLite.
3. **E2E tests** — Playwright browser tests against Docker app (PostgreSQL backend)

### E2E Test Coverage
| File | Tests | Coverage |
|------|-------|---------|
| 01-error-paths | 20 | HTTP errors, auth redirects, guest accessibility, UI consistency |
| 02-guest-ordering | 21 | Menu browsing, cart, checkout, order tracking, login page |
| 03-search-and-filter | 20 | Search, categories, price range, allergens, sort, trending, clear |
| 04-staff-order-flow | 16 | Authentication, order queue, status tabs, RBAC enforcement |
| 05-admin-features | 22 | Dashboard analytics, menu/promo/user/security management, nav |
| 06-payment-reconciliation | 12 | Time sync API, reconciliation access, checkout structure |

### Running tests manually

Every stage runs **inside Docker**. No host-side PHP, Node, npm, Playwright, browser, or any other install is required at any time. The Playwright image is pre-built with dependencies and browsers baked in at image-build time — nothing is installed at runtime.

```bash
# Unit + Feature (inside the app container)
docker-compose exec app php artisan test

# E2E (inside the pre-built Docker playwright service under the 'testing' profile)
docker-compose --profile testing run --rm playwright npx playwright test --reporter=list
```

## Architecture

Modular monolith with Hexagonal (Ports & Adapters) principles:

```
src/app/
├── Domain/           Pure PHP business logic (zero framework imports)
│   ├── Auth/         UserRole enum, StepUpVerifier
│   ├── Order/        OrderStatus enum, OrderStateMachine, exceptions
│   ├── Promotion/    PromoType enum, PromotionEvaluator (Resolution Tree)
│   ├── Payment/      HmacSigner, exceptions
│   ├── Risk/         DeviceFingerprintGenerator, RateLimitEvaluator, CaptchaTriggerEvaluator, ProfanityFilter
│   ├── Search/       SearchQuery value object, AllergenFilter
│   └── Cart/         CartValidator, TaxCalculator
├── Application/      Use case orchestration
│   ├── Order/        CreateOrderUseCase, TransitionOrderUseCase
│   ├── Payment/      CreatePaymentIntentUseCase, ConfirmPaymentUseCase
│   ├── Search/       SearchMenuUseCase
│   └── Analytics/    TrackEventUseCase, ComputeAnalyticsUseCase
├── Infrastructure/   Laravel implementations
│   ├── Persistence/  Eloquent models + repository implementations
│   ├── Services/     GregwarCaptchaService, LaravelEncryptionService
│   └── Logging/      StructuredLogFormatter, SensitiveDataScrubber
├── Http/             Controllers, middleware, form requests
├── Livewire/         UI components (Menu, Cart, Order, Checkout, Auth, Admin)
└── Console/          Artisan commands (reconcile, alerts, key rotation)
```

## Features Implemented

### Guest / Kiosk
- Menu browsing with full-text search (PostgreSQL tsvector)
- Price range, category, and allergen filtering (JSONB negative filters)
- Sort by relevance, newest, price
- Profanity/banned word blocking with trending term suggestions
- Cart with quantities, flavor preferences, 140-char notes
- Tax calculation per item (hot/cold/beverage/packaged rates)
- Promotion-aware checkout with discount breakdown
- Order tracking with real-time status polling

### Staff
- Order queue with status filter tabs
- Confirm, prepare, serve, settle lifecycle actions
- Manager PIN modal for protected actions
- Version conflict detection and display

### Admin
- Analytics dashboard (DAU, GMV, conversion funnel)
- Security rules (blacklist/whitelist by device, IP CIDR, username)
- Security audit log (rule hits + privilege escalations)
- Threshold-based alerts

### Security
- Device fingerprinting (salted SHA-256)
- Per-device and per-IP rate limiting
- CAPTCHA after 5 failed logins
- HMAC payment signing with nonce + 5-min expiry
- Idempotent payment processing
- Immutable audit logs (PostgreSQL triggers prevent UPDATE/DELETE)
- Encrypted customer notes and sensitive fields at rest
- Sensitive data scrubbed from logs

## API Endpoints

### Web Routes (Livewire UI)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | / | None | Menu browser (kiosk) |
| GET | /menu | None | Menu browser (alias of `/`) |
| GET | /cart | None | Cart page |
| GET | /checkout | None | Checkout page (rate-limited) |
| GET | /order/{trackingToken} | None | Order tracker (token-based) |
| GET | /login | None | Staff login (rate-limited) |
| POST | /logout | None | End the current staff session |
| GET | /password/change | Staff | Forced password-change flow on first login |
| GET | /staff/orders | Staff | Order queue |
| GET | /admin/dashboard | Admin | Analytics dashboard |
| GET | /admin/menu | Admin | Menu item management |
| GET | /admin/promotions | Admin | Promotion management |
| GET | /admin/users | Admin | User management |
| GET | /admin/security | Admin | Security rules (blacklist/whitelist) |
| GET | /admin/security/audit | Admin | Security audit log |
| GET | /admin/alerts | Admin | Alert dashboard |
| GET | /manager/reconciliation | Manager+ | Payment reconciliation queue |

### REST API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/time-sync | None | Server time for tablet sync |
| GET | /api/menu/search | None | Search menu items |
| GET | /api/menu/categories | None | List categories |
| GET | /api/menu/{id} | None | Get menu item detail |
| GET | /api/cart | None | View cart (session-based) |
| POST | /api/cart/items | None | Add item to cart |
| PATCH | /api/cart/items/{id} | None | Update cart item |
| DELETE | /api/cart/items/{id} | None | Remove cart item |
| DELETE | /api/cart | None | Clear cart |
| POST | /api/orders | None | Create order (returns tracking token) |
| GET | /api/orders/{token} | None | Track order by token (guest-safe DTO) |
| GET | /api/orders/{id}/detail | Staff | Full order detail incl. internal IDs and status log |
| POST | /api/orders/{id}/transition | Staff | Transition order status |
| POST | /api/orders/{id}/discount | Manager+ | Apply a manual discount (PIN required for >$20) |
| POST | /api/payments/intent | Cashier+ | Create payment intent |
| POST | /api/payments/confirm | Cashier+ | Confirm payment (manager PIN for ambiguous) |

## Services

| Service | Internal Port | External Port |
|---------|---------------|---------------|
| Nginx | 80 | 8080 |
| PHP-FPM | 9000 | - |
| PostgreSQL | 5432 | 5433 |

## Scheduled Tasks

| Command | Signature | Purpose |
|---------|-----------|---------|
| Reconcile Payments | `php artisan harborbite:reconcile-payments` | Detect stuck payment states, expire pending intents |
| Check Alerts | `php artisan harborbite:check-alerts` | Monitor failed logins, risk hits, API error rate, p95 latency |
| Rotate Key | `php artisan harborbite:rotate-key [--dry-run]` | Re-encrypt sensitive fields (manual/operational only, not scheduled) |

## Configuration

All configurable thresholds in `src/config/harborbite.php`:
- Rate limiting (registrations/hour, checkouts/10min)
- CAPTCHA triggers (failed logins, rapid re-pricings)
- HMAC expiry window (default 300s)
- Tax rates by category
- Alert thresholds (error rate, GMV drop, risk hits)
- Max trending terms (20)

## Logs

- Application logs: Docker volume `harborbite-logs`
- Format: Structured JSON with trace IDs
- Sensitive data: Automatically scrubbed (passwords, PINs, tokens, notes)
- Rotation: 14-day retention (daily channel, configurable via `LOG_DAILY_DAYS`)
