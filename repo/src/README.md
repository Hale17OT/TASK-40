# HarborBite - Offline Restaurant Ordering System

Self-service kiosk and staff management platform for restaurant ordering with promotion-aware checkout, secure payment capture, anti-fraud controls, and local analytics.

## Architecture

- **Frontend**: Livewire components + Tailwind CSS (kiosk & admin UIs)
- **Backend API**: REST endpoints under `/api/` for menu, cart, order, payment operations
- **Domain Layer**: `app/Domain/` - business logic (order state machine, tax, promotions, risk, HMAC)
- **Application Layer**: `app/Application/` - use cases (create order, transitions, payments, analytics)
- **Infrastructure**: `app/Infrastructure/` - persistence (Eloquent), logging (structured JSON + scrubbing), external services

## Security Features

- **Device fingerprinting**: SHA-256 hash from user-agent + screen traits; globally enforced middleware
- **Rate limiting**: Per-device and per-IP; tighter limits on login (10/min) and checkout (30/10min)
- **Blacklist/whitelist**: Device, IP, and username blocking with CIDR range support
- **CAPTCHA**: Triggered after failed login attempts or rapid repricing
- **HMAC payment signatures**: SHA-256 signed payment intents with nonce + expiry
- **Manager PIN**: Bcrypt-hashed; required for step-up operations (cancel prepared/served orders, discount > $20)
- **Sensitive log scrubbing**: Monolog processor redacts password, PIN, note, token, credit card fields
- **Customer note encryption**: AES-256-CBC at rest in cart_items and order_items; included in key rotation
- **Order tracking tokens**: 64-char hex tokens prevent IDOR on public order tracker

## Roles

| Role | Access |
|---|---|
| `administrator` | All routes, admin dashboard, user/menu/promo/security/banned-word management |
| `manager` | Staff dashboard, reconciliation, step-up PIN approval, manual discount override |
| `cashier` | Staff dashboard, confirm orders (pending→in_preparation), settle via payment |
| `kitchen` | Staff dashboard, mark prepared orders as served (in_preparation→served) |

**Order lifecycle**: Kiosk creates order (pending) → Cashier confirms (in_preparation) → Kitchen marks served → Payment confirmation settles. Kitchen does not confirm orders; their workflow starts after cashier confirmation. Settlement requires a confirmed payment intent (no direct settle).

## API Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/api/time-sync` | No | Server time sync |
| GET | `/api/menu/search` | No | Search menu items |
| GET | `/api/menu/categories` | No | List categories |
| GET | `/api/menu/{id}` | No | Get menu item |
| GET | `/api/cart` | No | View cart |
| POST | `/api/cart/items` | No | Add item to cart |
| PATCH | `/api/cart/items/{id}` | No | Update cart item |
| DELETE | `/api/cart/items/{id}` | No | Remove cart item |
| DELETE | `/api/cart` | No | Clear cart |
| POST | `/api/orders` | No | Create order |
| GET | `/api/orders/{token}` | No | Track order by token |
| POST | `/api/orders/{id}/transition` | Yes | Transition order status |
| POST | `/api/payments/intent` | Yes | Create payment intent |
| POST | `/api/payments/confirm` | Yes | Confirm payment |

## Setup

### Non-Docker (local PostgreSQL)

Requires PostgreSQL 14+ with `pgcrypto` and `uuid-ossp` extensions.

```bash
cp .env.example .env
composer install
php artisan key:generate

# Configure DB_CONNECTION=pgsql in .env with your PostgreSQL credentials.
# The prevent_modification() function for immutable audit logs is created
# automatically by migration 0001_00_00_000000. Extensions are enabled via:
#   CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
#   CREATE EXTENSION IF NOT EXISTS "pgcrypto";
# Run these manually on your database if your PG user lacks superuser privileges.

php artisan migrate --seed
php artisan serve
```

### Docker

```bash
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

The Docker setup automatically provisions the `prevent_modification()` function and PG extensions via `docker/postgres/init.sql`.

## Test Credentials

| Role | Username | Password | PIN |
|---|---|---|---|
| Administrator | `admin` | `admin123` | `9999` |
| Manager | `manager` | `manager123` | `1234` |
| Cashier | `cashier` | `cashier123` | - |
| Kitchen | `kitchen` | `kitchen123` | - |

## Running Tests

```bash
# Unit + Feature tests
php artisan test

# With coverage
php artisan test --coverage

# Specific suite
php artisan test --filter=OrderLifecycleTest

# E2E (Playwright)
npx playwright test
```

## Observability

- **Request logging**: All requests logged with trace ID, method, path, status, duration, IP, fingerprint
- **Structured JSON logs**: `StructuredLogFormatter` + `SensitiveDataScrubber` processor
- **Alert thresholds**: Scheduled command `harborbite:check-alerts` monitors:
  - Failed logins per hour (threshold: 100)
  - Risk rule hits per hour (threshold: 50)
  - API error rate (threshold: 5%)
  - API p95 latency (threshold: 2000ms)
- **Request metrics**: Persisted to `request_metrics` table for aggregation

## Key Commands

### Scheduled (automatic via `php artisan schedule:work`)
```bash
php artisan harborbite:check-alerts          # Every 5 min — alert thresholds (logins, risk, error rate, latency)
php artisan harborbite:reconcile-payments    # Every 10 min — detect stuck payment states, expire pending intents
```

### Manual / Operational
```bash
php artisan harborbite:rotate-key            # Rotate encryption key (re-encrypts all sensitive fields)
php artisan harborbite:rotate-key --dry-run  # Preview what would be re-encrypted
```

Key rotation is an operational action triggered manually after an APP_KEY change, not scheduled automatically.
