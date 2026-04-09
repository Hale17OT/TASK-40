# HarborBite — Offline Restaurant Ordering & Risk Management System

An offline-first, on-premise restaurant ordering system with promotion-aware checkout, secure payment capture, and end-to-end fraud controls. Built with Laravel 13 + Livewire 4 + Alpine.js + Tailwind CSS + PostgreSQL, designed to run entirely on a local restaurant network.

## Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose v2)
- No other dependencies required

## Quick Start

```bash
# 1. Copy and configure environment secrets
cp .env.example .env
# Edit .env — set these required values:
#   APP_KEY          (generate with: docker compose run --rm app php artisan key:generate --show)
#   DB_PASSWORD      (choose a strong password)
#   PAYMENT_HMAC_KEY (generate with: openssl rand -hex 32)
#   DEVICE_FINGERPRINT_SALT (generate with: openssl rand -hex 16)

# 2. Start services
docker compose up --build -d
```

App available at **http://localhost:8080**

First startup runs migrations and seeds automatically.

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

```bash
# Unit + Feature (inside Docker)
docker compose exec app php artisan test

# E2E only (requires app running + Playwright installed)
cd src/tests/E2E && npx playwright test
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
| GET | /cart | None | Cart page |
| GET | /checkout | None | Checkout page (rate-limited) |
| GET | /order/{trackingToken} | None | Order tracker (token-based) |
| GET | /login | None | Staff login (rate-limited) |
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
| GET | /api/orders/{token} | None | Track order by token |
| POST | /api/orders/{id}/transition | Staff | Transition order status |
| POST | /api/payments/intent | Staff | Create payment intent |
| POST | /api/payments/confirm | Staff | Confirm payment (manager PIN for ambiguous) |

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
