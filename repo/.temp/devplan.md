# HarborBite Offline Restaurant Ordering & Risk Management System

## Development Plan — Zero-to-One Production Implementation

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Architecture Choice & Reasoning](#2-architecture-choice--reasoning)
3. [Domain Model](#3-domain-model)
4. [Data Model](#4-data-model)
5. [Interface Contracts](#5-interface-contracts)
6. [State Transitions](#6-state-transitions)
7. [Permission & Access Boundaries](#7-permission--access-boundaries)
8. [Failure Paths](#8-failure-paths)
9. [Logging Strategy](#9-logging-strategy)
10. [Testing Strategy](#10-testing-strategy)
11. [Docker Execution Assumptions](#11-docker-execution-assumptions)
12. [UI/UX Design System](#12-uiux-design-system)
13. [Module Breakdown](#13-module-breakdown)
14. [Module Implementation Order & Dependency Graph](#14-module-implementation-order--dependency-graph)
15. [README & Operational Documentation](#15-readme--operational-documentation)
16. [Compliance Verification — Original Prompt Traceability](#16-compliance-verification--original-prompt-traceability)

---

## 1. System Overview

**HarborBite** is a fully offline, on-premise Restaurant Ordering & Risk Management system deployed on a local restaurant network. It powers tablet/kiosk interfaces for guests and staff terminals for order management, payments, analytics, and fraud control — with zero internet dependency.

### Core Capabilities

| Capability | Description |
|---|---|
| **On-Premise Menu Discovery** | Guests browse, search (keyword, price-range, category attributes, allergen exclusions), filter, and sort a menu on tablet/kiosk interfaces |
| **Promotion-Aware Checkout** | Automatic "best offer wins" promotion engine with Resolution Tree logic, mutual exclusions, and time-windowed offers |
| **Secure In-Store Payment Capture** | Offline payment intents with HMAC integrity, nonce+expiry replay prevention, idempotent processing, and manager-reviewed reconciliation |
| **End-to-End Fraud Controls** | Device fingerprinting, per-device/IP rate limiting, offline CAPTCHA, blacklists/whitelists, step-up manager PIN verification, immutable audit logs |
| **Order Lifecycle Management** | Strict state machine (Pending Confirmation -> In Preparation -> Served -> Settled | Canceled) with optimistic version control and real-time conflict resolution |
| **Local Analytics & Observability** | DAU, GMV, conversion funnels, retention — all computed from local PostgreSQL event tracking with materialized views, rotating logs, and threshold-based alerts |

### Actors

| Actor | Role |
|---|---|
| **Guest** | Browses menu on tablet/kiosk, builds cart, checks out. No login required. Identified by device fingerprint. |
| **Cashier** | Confirms pending orders, marks orders as Served. Cannot cancel after In Preparation. |
| **Kitchen** | Marks orders In Preparation. Views preparation queue. Cannot modify prices. |
| **Manager** | Overrides discounts >$20, cancels orders after In Preparation (PIN required), settles ambiguous payments, handles incidents. |
| **Administrator** | Full access: user management, system configuration, analytics, security rules, promotions, menu management. |

### Constraints

- **Offline-only**: No external internet, no cloud services, no third-party OAuth, no CDN.
- **Single-site deployment**: One PostgreSQL instance, one application server, N tablets on local network.
- **All timestamps UTC in DB**, displayed in configured local timezone.
- **All encryption keys stored only on-premise**.

---

## 2. Architecture Choice & Reasoning

### Pattern: Modular Monolith with Hexagonal (Ports & Adapters) Principles

**Why not microservices?** This is a single-site offline system running on one server. Microservices would add network overhead, deployment complexity, and operational burden with zero benefit.

**Why hexagonal within a monolith?** Business logic (promotions, state machine, HMAC signing, fraud rules) must be unit-testable without Laravel, without PostgreSQL, without Livewire. The hexagonal approach gives us:

1. **Domain layer** — pure PHP classes with zero framework imports. Business rules live here.
2. **Application layer** — orchestrates domain objects, defines use cases as service classes.
3. **Infrastructure layer** — Laravel-specific implementations: Eloquent repositories, middleware, Livewire components, PostgreSQL queries.
4. **Interface layer** — Livewire components (views), REST-style internal endpoints, Blade templates.

### Directory Structure

```
repo/
├── .temp/                          # Planning artifacts (this file)
├── docker/
│   ├── app/
│   │   ├── Dockerfile              # PHP-FPM + Node (for asset build)
│   │   └── supervisord.conf        # FPM + queue worker + scheduler
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── default.conf
│   └── postgres/
│       └── init.sql                # Extensions, partition setup
├── docker-compose.yml              # Canonical startup
├── run_tests.sh                    # Canonical test entrypoint
├── src/                            # Laravel application root
│   ├── app/
│   │   ├── Domain/                 # Pure PHP domain logic — NO framework imports
│   │   │   ├── Order/
│   │   │   │   ├── OrderStatus.php           # Backed enum
│   │   │   │   ├── OrderStateMachine.php     # State transition logic
│   │   │   │   ├── OrderEntity.php           # Domain entity (not Eloquent)
│   │   │   │   └── Exceptions/
│   │   │   │       ├── StaleVersionException.php
│   │   │   │       ├── InvalidTransitionException.php
│   │   │   │       └── KitchenLockException.php
│   │   │   ├── Promotion/
│   │   │   │   ├── PromoType.php             # Backed enum
│   │   │   │   ├── PromotionEvaluator.php    # Resolution Tree logic
│   │   │   │   ├── PromotionRule.php         # Value object
│   │   │   │   └── Exceptions/
│   │   │   ├── Payment/
│   │   │   │   ├── HmacSigner.php            # Nonce + timestamp + HMAC
│   │   │   │   ├── PaymentIntentEntity.php
│   │   │   │   └── Exceptions/
│   │   │   │       ├── ExpiredNonceException.php
│   │   │   │       ├── ReplayedNonceException.php
│   │   │   │       └── TamperedSignatureException.php
│   │   │   ├── Risk/
│   │   │   │   ├── DeviceFingerprintGenerator.php
│   │   │   │   ├── RateLimitEvaluator.php
│   │   │   │   ├── ProfanityFilter.php
│   │   │   │   └── CaptchaTriggerEvaluator.php
│   │   │   ├── Search/
│   │   │   │   ├── SearchQuery.php           # Value object
│   │   │   │   └── AllergenFilter.php        # Negative filter logic
│   │   │   ├── Cart/
│   │   │   │   ├── CartEntity.php
│   │   │   │   ├── TaxCalculator.php         # Tax-Rule Table logic
│   │   │   │   └── CartValidator.php
│   │   │   └── Auth/
│   │   │       ├── UserRole.php              # Backed enum
│   │   │       └── StepUpVerifier.php        # Manager PIN logic
│   │   │
│   │   ├── Application/             # Use case orchestration — may import Domain
│   │   │   ├── Order/
│   │   │   │   ├── ConfirmOrderUseCase.php
│   │   │   │   ├── TransitionOrderUseCase.php
│   │   │   │   ├── CancelOrderUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── OrderRepositoryInterface.php
│   │   │   ├── Promotion/
│   │   │   │   ├── ApplyPromotionUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── PromotionRepositoryInterface.php
│   │   │   ├── Payment/
│   │   │   │   ├── CreatePaymentIntentUseCase.php
│   │   │   │   ├── ConfirmPaymentUseCase.php
│   │   │   │   ├── ReconcilePaymentsUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── PaymentRepositoryInterface.php
│   │   │   ├── Risk/
│   │   │   │   ├── EvaluateRateLimitUseCase.php
│   │   │   │   ├── CheckDeviceFingerprintUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       ├── RiskLogRepositoryInterface.php
│   │   │   │       └── BlacklistRepositoryInterface.php
│   │   │   ├── Search/
│   │   │   │   ├── SearchMenuUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── MenuRepositoryInterface.php
│   │   │   ├── Cart/
│   │   │   │   ├── ManageCartUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── CartRepositoryInterface.php
│   │   │   ├── Analytics/
│   │   │   │   ├── TrackEventUseCase.php
│   │   │   │   ├── ComputeAnalyticsUseCase.php
│   │   │   │   └── Ports/
│   │   │   │       └── AnalyticsRepositoryInterface.php
│   │   │   └── Admin/
│   │   │       ├── ManageMenuUseCase.php
│   │   │       ├── ManagePromotionsUseCase.php
│   │   │       ├── ManageUsersUseCase.php
│   │   │       └── ManageSecurityRulesUseCase.php
│   │   │
│   │   ├── Infrastructure/          # Laravel-specific implementations
│   │   │   ├── Persistence/
│   │   │   │   ├── Models/           # Eloquent models
│   │   │   │   │   ├── User.php
│   │   │   │   │   ├── MenuItem.php
│   │   │   │   │   ├── MenuCategory.php
│   │   │   │   │   ├── Cart.php
│   │   │   │   │   ├── CartItem.php
│   │   │   │   │   ├── Order.php
│   │   │   │   │   ├── OrderItem.php
│   │   │   │   │   ├── OrderStatusLog.php
│   │   │   │   │   ├── Promotion.php
│   │   │   │   │   ├── AppliedPromotion.php
│   │   │   │   │   ├── PaymentIntent.php
│   │   │   │   │   ├── PaymentConfirmation.php
│   │   │   │   │   ├── DeviceFingerprint.php
│   │   │   │   │   ├── RateLimitEvent.php
│   │   │   │   │   ├── RuleHitLog.php
│   │   │   │   │   ├── PrivilegeEscalationLog.php
│   │   │   │   │   ├── SecurityBlacklist.php
│   │   │   │   │   ├── SecurityWhitelist.php
│   │   │   │   │   ├── TrendingSearch.php
│   │   │   │   │   ├── BannedWord.php
│   │   │   │   │   ├── TaxRule.php
│   │   │   │   │   ├── AnalyticsEvent.php
│   │   │   │   │   ├── IncidentTicket.php
│   │   │   │   │   └── AdminAlert.php
│   │   │   │   └── Repositories/     # Eloquent implementations of Port interfaces
│   │   │   │       ├── EloquentOrderRepository.php
│   │   │   │       ├── EloquentPromotionRepository.php
│   │   │   │       ├── EloquentPaymentRepository.php
│   │   │   │       ├── EloquentMenuRepository.php
│   │   │   │       ├── EloquentCartRepository.php
│   │   │   │       ├── EloquentRiskLogRepository.php
│   │   │   │       ├── EloquentBlacklistRepository.php
│   │   │   │       └── EloquentAnalyticsRepository.php
│   │   │   ├── Services/             # Framework-bound service implementations
│   │   │   │   ├── LaravelEncryptionService.php
│   │   │   │   ├── GregwarCaptchaService.php
│   │   │   │   └── TimeSyncService.php
│   │   │   └── Logging/
│   │   │       ├── StructuredLogFormatter.php
│   │   │       └── SensitiveDataScrubber.php
│   │   │
│   │   ├── Http/                     # Web/API layer
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/
│   │   │   │   │   ├── TimeSyncController.php
│   │   │   │   │   ├── MenuApiController.php
│   │   │   │   │   ├── CartApiController.php
│   │   │   │   │   ├── OrderApiController.php
│   │   │   │   │   └── PaymentApiController.php
│   │   │   │   └── Admin/
│   │   │   │       ├── MenuManagementController.php
│   │   │   │       ├── PromotionController.php
│   │   │   │       ├── UserController.php
│   │   │   │       ├── SecurityConfigController.php
│   │   │   │       └── AnalyticsController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── DeviceFingerprintMiddleware.php
│   │   │   │   ├── RateLimitMiddleware.php
│   │   │   │   ├── RoleMiddleware.php
│   │   │   │   ├── TraceIdMiddleware.php
│   │   │   │   └── RequestLoggingMiddleware.php
│   │   │   ├── Requests/             # Form Request validation classes
│   │   │   │   ├── StoreMenuItemRequest.php
│   │   │   │   ├── UpdateOrderStatusRequest.php
│   │   │   │   ├── CreatePaymentIntentRequest.php
│   │   │   │   ├── ConfirmPaymentRequest.php
│   │   │   │   ├── StorePromotionRequest.php
│   │   │   │   ├── SearchMenuRequest.php
│   │   │   │   └── StepUpVerificationRequest.php
│   │   │   └── Resources/            # API response transformers
│   │   │       ├── MenuItemResource.php
│   │   │       ├── OrderResource.php
│   │   │       ├── CartResource.php
│   │   │       └── PaymentIntentResource.php
│   │   │
│   │   ├── Livewire/                 # Livewire UI components
│   │   │   ├── Menu/
│   │   │   │   ├── MenuBrowser.php
│   │   │   │   └── MenuItemDetail.php
│   │   │   ├── Cart/
│   │   │   │   ├── CartManager.php
│   │   │   │   └── CartSummary.php
│   │   │   ├── Order/
│   │   │   │   ├── OrderTracker.php      # Guest-facing, wire:poll
│   │   │   │   └── OrderList.php         # Staff-facing queue
│   │   │   ├── Checkout/
│   │   │   │   ├── CheckoutFlow.php
│   │   │   │   └── PaymentConfirmation.php
│   │   │   ├── Auth/
│   │   │   │   ├── LoginForm.php
│   │   │   │   └── CaptchaChallenge.php
│   │   │   ├── Admin/
│   │   │   │   ├── Dashboard.php
│   │   │   │   ├── PromotionManager.php
│   │   │   │   ├── MenuManager.php
│   │   │   │   ├── UserManager.php
│   │   │   │   ├── SecurityAuditLog.php
│   │   │   │   ├── ReconciliationQueue.php
│   │   │   │   └── SecurityRulesManager.php
│   │   │   └── Shared/
│   │   │       ├── StepUpModal.php       # Manager PIN verification modal
│   │   │       ├── ConflictBanner.php    # Version conflict notification
│   │   │       └── ZombieRedirect.php    # Stale checkout redirect
│   │   │
│   │   ├── Console/
│   │   │   └── Commands/
│   │   │       ├── ReconcilePaymentsCommand.php
│   │   │       ├── AggregateAnalyticsCommand.php
│   │   │       ├── CreateMonthlyPartitionCommand.php
│   │   │       ├── RotateEncryptionKeyCommand.php
│   │   │       └── CheckAlertThresholdsCommand.php
│   │   │
│   │   └── Providers/
│   │       ├── AppServiceProvider.php
│   │       ├── DomainServiceProvider.php  # Binds Port interfaces to Eloquent repos
│   │       └── EventServiceProvider.php
│   │
│   ├── bootstrap/
│   │   ├── app.php
│   │   └── providers.php
│   │
│   ├── config/
│   │   └── harborbite.php               # All app-specific config
│   │
│   ├── database/
│   │   ├── migrations/                  # Ordered per module
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php
│   │       ├── RoleSeeder.php
│   │       ├── BannedWordSeeder.php
│   │       ├── TaxRuleSeeder.php
│   │       └── DemoMenuSeeder.php
│   │
│   ├── resources/
│   │   ├── views/
│   │   │   ├── components/
│   │   │   │   └── layouts/
│   │   │   │       ├── kiosk.blade.php   # Full-viewport tablet layout
│   │   │   │       ├── staff.blade.php   # Staff terminal layout
│   │   │   │       └── admin.blade.php   # Admin panel layout
│   │   │   └── livewire/                # Component Blade views
│   │   │       ├── menu/
│   │   │       ├── cart/
│   │   │       ├── order/
│   │   │       ├── checkout/
│   │   │       ├── auth/
│   │   │       ├── admin/
│   │   │       └── shared/
│   │   ├── css/
│   │   │   └── app.css                  # Tailwind @theme tokens + custom design system
│   │   └── js/
│   │       └── app.js                   # Alpine.js + device fingerprint collector
│   │
│   ├── routes/
│   │   ├── web.php                      # Livewire page routes
│   │   └── api.php                      # REST endpoints (time-sync, internal)
│   │
│   └── tests/
│       ├── Unit/
│       │   ├── Domain/
│       │   │   ├── Order/
│       │   │   │   ├── OrderStateMachineTest.php
│       │   │   │   └── OrderStatusTest.php
│       │   │   ├── Promotion/
│       │   │   │   ├── PromotionEvaluatorTest.php
│       │   │   │   └── PromoTypeTest.php
│       │   │   ├── Payment/
│       │   │   │   └── HmacSignerTest.php
│       │   │   ├── Risk/
│       │   │   │   ├── ProfanityFilterTest.php
│       │   │   │   ├── RateLimitEvaluatorTest.php
│       │   │   │   ├── DeviceFingerprintGeneratorTest.php
│       │   │   │   └── CaptchaTriggerEvaluatorTest.php
│       │   │   ├── Cart/
│       │   │   │   ├── TaxCalculatorTest.php
│       │   │   │   └── CartValidatorTest.php
│       │   │   ├── Search/
│       │   │   │   └── AllergenFilterTest.php
│       │   │   └── Auth/
│       │   │       └── StepUpVerifierTest.php
│       │   └── Application/
│       │       ├── ConfirmOrderUseCaseTest.php
│       │       ├── TransitionOrderUseCaseTest.php
│       │       ├── ApplyPromotionUseCaseTest.php
│       │       ├── CreatePaymentIntentUseCaseTest.php
│       │       └── SearchMenuUseCaseTest.php
│       ├── Feature/                     # Integration tests (hit real DB in Docker)
│       │   ├── Menu/
│       │   │   ├── MenuSearchTest.php
│       │   │   ├── MenuCrudTest.php
│       │   │   └── AllergenFilteringTest.php
│       │   ├── Cart/
│       │   │   ├── CartManagementTest.php
│       │   │   └── TaxCalculationTest.php
│       │   ├── Order/
│       │   │   ├── OrderLifecycleTest.php
│       │   │   ├── VersionConflictTest.php
│       │   │   ├── KitchenLockTest.php
│       │   │   └── ZombieStateTest.php
│       │   ├── Promotion/
│       │   │   ├── PromotionApplicationTest.php
│       │   │   ├── MutualExclusionTest.php
│       │   │   └── TimeWindowTest.php
│       │   ├── Payment/
│       │   │   ├── PaymentIntentTest.php
│       │   │   ├── HmacVerificationTest.php
│       │   │   ├── IdempotencyTest.php
│       │   │   └── ReconciliationTest.php
│       │   ├── Risk/
│       │   │   ├── RateLimitingTest.php
│       │   │   ├── DeviceFingerprintTest.php
│       │   │   ├── BlacklistTest.php
│       │   │   ├── CaptchaTriggerTest.php
│       │   │   └── StepUpVerificationTest.php
│       │   ├── Auth/
│       │   │   ├── LoginTest.php
│       │   │   └── RoleAccessTest.php
│       │   ├── Analytics/
│       │   │   ├── EventTrackingTest.php
│       │   │   └── AggregationTest.php
│       │   └── Admin/
│       │       ├── UserManagementTest.php
│       │       ├── PromotionManagementTest.php
│       │       └── SecurityRulesTest.php
│       └── E2E/                         # Playwright tests
│           ├── playwright.config.ts
│           ├── guest-ordering.spec.ts
│           ├── staff-order-flow.spec.ts
│           ├── manager-overrides.spec.ts
│           ├── admin-dashboard.spec.ts
│           ├── payment-flow.spec.ts
│           ├── search-and-filter.spec.ts
│           ├── promotion-display.spec.ts
│           ├── error-paths.spec.ts
│           └── security-enforcement.spec.ts
│
├── .env.example
├── .env.docker                          # Docker-specific env
└── README.md
```

### Why This Structure

| Principle | How It's Applied |
|---|---|
| **Clean Architecture** | `Domain/` has zero Laravel imports. `Application/` depends only on Domain and Port interfaces. `Infrastructure/` implements those ports. |
| **Vertical Slicing** | Each module (Order, Payment, Promotion, etc.) is a complete vertical slice from Domain through Application through Infrastructure through UI. |
| **Testability** | Domain logic unit-testable with plain PHP. Integration tests use real DB. Playwright E2E tests verify full stack. |
| **No half-implemented modules** | Each module is completed before the next begins. No empty folders or stub files. |

### Technology Stack

| Component | Technology | Version | Reasoning |
|---|---|---|---|
| Framework | Laravel | 12.x | Best PHP framework for rapid development; strong ecosystem |
| Reactive UI | Livewire | 4.x | Server-rendered reactivity; `wire:model.live`, `wire:poll`, form objects |
| Client interactivity | Alpine.js | 3.x | Bundled with Livewire; handles counters, toggles, fingerprint collection |
| CSS Framework | Tailwind CSS | 4.x | Utility-first; `@theme` directive for design tokens; 8px grid system |
| Database | PostgreSQL | 16+ | JSONB, full-text search, declarative partitioning, triggers |
| CAPTCHA | Gregwar/Captcha | latest | Offline image/math CAPTCHA, no external service |
| Containerization | Docker + Docker Compose | latest | Canonical runtime; one-command startup |
| E2E Testing | Playwright | latest | Cross-browser; can verify UI + backend responses |
| PHP Testing | Pest | 3.x | Modern PHP testing; Laravel integration |
| Asset Build | Vite | latest | Laravel default; bundles Tailwind + Alpine |
| Process Manager | Supervisord | latest | Runs PHP-FPM + queue worker + scheduler in one container |

---

## 3. Domain Model

### Aggregates and Entities

```
┌─────────────────────────────────────────────────────────────────────┐
│                        MENU AGGREGATE                               │
│  MenuCategory (1) ──► (N) MenuItem                                  │
│  MenuItem has: sku, name, description, price, attributes (JSONB),   │
│                tax_category, is_active                               │
│  MenuCategory has: name, sort_order, is_active                      │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                        CART AGGREGATE                                │
│  Cart (1) ──► (N) CartItem                                          │
│  Cart has: session_id, device_fingerprint_id                        │
│  CartItem has: menu_item_id, quantity, flavor_preference,           │
│               note (140 chars), unit_price_snapshot                  │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                       ORDER AGGREGATE                                │
│  Order (1) ──► (N) OrderItem                                        │
│  Order (1) ──► (N) OrderStatusLog                                   │
│  Order (1) ──► (0..1) AppliedPromotion                              │
│  Order (1) ──► (0..1) PaymentIntent ──► (0..1) PaymentConfirmation  │
│  Order has: order_number, status (enum), version (int),             │
│            subtotal, tax, discount, total,                           │
│            confirmed_by, prepared_by, served_by, settled_by,        │
│            canceled_by, cancel_reason                                │
│  OrderItem has: menu_item_snapshot (name, price, sku),              │
│                quantity, flavor_preference, note, locked_at          │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                     PROMOTION AGGREGATE                              │
│  Promotion has: name, type (enum), rules (JSONB),                   │
│                exclusion_group, starts_at, ends_at, is_active       │
│  AppliedPromotion has: order_id, promotion_id, discount_amount,     │
│                        description                                   │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      PAYMENT AGGREGATE                               │
│  PaymentIntent has: order_id, reference (UUID), amount,             │
│                     hmac_signature, nonce (unique), expires_at,      │
│                     status (pending/confirmed/failed/canceled/       │
│                     reconciling)                                     │
│  PaymentConfirmation has: payment_intent_id, confirmed_by,          │
│                           method, notes (encrypted), created_at      │
│  IncidentTicket has: order_id, payment_intent_id, type,             │
│                      status, assigned_to, resolution_reason_code,    │
│                      receipt_reference (encrypted), resolved_by,     │
│                      resolved_at                                     │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                        RISK AGGREGATE                                │
│  DeviceFingerprint has: fingerprint_hash (unique), user_agent       │
│                         (encrypted), screen_traits (encrypted),      │
│                         first_seen_at, last_seen_at                  │
│  RuleHitLog has: type, device_fingerprint_id, ip_address,           │
│                  details (JSONB), created_at [IMMUTABLE]             │
│  PrivilegeEscalationLog has: action, order_id, manager_id,          │
│                              manager_pin_hash, reason, metadata,     │
│                              created_at [IMMUTABLE]                  │
│  SecurityBlacklist has: type (device/ip/username), value,            │
│                         reason, created_by, expires_at               │
│  SecurityWhitelist has: type, value, reason, created_by              │
│  RateLimitEvent has: type, identifier, count, window_start          │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      ANALYTICS AGGREGATE                             │
│  AnalyticsEvent has: event_type, device_fingerprint_id,             │
│                      session_id, payload (JSONB), trace_id,          │
│                      created_at [PARTITIONED MONTHLY]                │
│  AdminAlert has: type, severity, message, threshold_value,          │
│                  actual_value, acknowledged_by, created_at           │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      SUPPORT ENTITIES                                │
│  User has: name, username (unique), password (bcrypt),              │
│            manager_pin (bcrypt, nullable), role (enum), is_active    │
│  TrendingSearch has: term, location_id, sort_order, pinned_by       │
│  BannedWord has: word (unique)                                       │
│  TaxRule has: category, rate, effective_from, effective_to           │
└─────────────────────────────────────────────────────────────────────┘
```

### Value Objects (in Domain layer, no persistence)

- `SearchQuery` — keyword, price range, attribute filters, sort option, page
- `PromotionRule` — parsed from JSONB: threshold, percentage, target SKUs, etc.
- `MoneyAmount` — decimal wrapper with cent-precision arithmetic
- `DeviceTraits` — user agent + screen dimensions for fingerprint generation

---

## 4. Data Model

### Migration Ordering (by module)

```
MODULE A — Foundation:
  0001_create_users_table
  0002_create_sessions_table
  0003_create_cache_table
  0004_create_jobs_table

MODULE B — Menu & Search:
  0010_create_menu_categories_table
  0011_create_menu_items_table (with JSONB attributes, tsvector, GIN indexes)
  0012_create_trending_searches_table
  0013_create_banned_words_table
  0014_create_tax_rules_table

MODULE C — Cart:
  0020_create_carts_table
  0021_create_cart_items_table (note varchar(140) CHECK)

MODULE D — Orders:
  0030_create_orders_table (with version integer, status enum)
  0031_create_order_items_table (with locked_at)
  0032_create_order_status_logs_table (with PG trigger: no UPDATE/DELETE)

MODULE E — Promotions:
  0040_create_promotions_table (type enum, rules JSONB, exclusion_group)
  0041_create_applied_promotions_table

MODULE F — Payments:
  0050_create_payment_intents_table (nonce UNIQUE, reference UUID UNIQUE)
  0051_create_payment_confirmations_table (notes encrypted cast)
  0052_create_incident_tickets_table (resolution_reason_code, receipt_reference encrypted)

MODULE G — Risk & Security:
  0060_create_device_fingerprints_table (fingerprint_hash UNIQUE)
  0061_add_device_fingerprint_id_to_carts
  0062_create_rate_limit_events_table
  0063_create_rule_hit_logs_table (with PG trigger: no UPDATE/DELETE)
  0064_create_privilege_escalation_logs_table (with PG trigger: no UPDATE/DELETE)
  0065_create_security_blacklists_table
  0066_create_security_whitelists_table

MODULE H — Analytics:
  0070_create_analytics_events_partitioned_table (raw SQL for PG partitioning)
  0071_create_materialized_view_daily_analytics (raw SQL)
  0072_create_admin_alerts_table
```

### Key Schema Details

**orders table — Optimistic version control:**
```sql
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    cart_id BIGINT REFERENCES carts(id),
    status VARCHAR(30) NOT NULL DEFAULT 'pending_confirmation',
    version INTEGER NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    confirmed_by BIGINT REFERENCES users(id),
    prepared_by BIGINT REFERENCES users(id),
    served_by BIGINT REFERENCES users(id),
    settled_by BIGINT REFERENCES users(id),
    canceled_by BIGINT REFERENCES users(id),
    cancel_reason TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_orders_status_created ON orders(status, created_at);
```

**menu_items table — Full-text search + JSONB:**
```sql
CREATE TABLE menu_items (
    id BIGSERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    menu_category_id BIGINT REFERENCES menu_categories(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    tax_category VARCHAR(50) NOT NULL DEFAULT 'hot_prepared',
    is_active BOOLEAN NOT NULL DEFAULT true,
    attributes JSONB NOT NULL DEFAULT '{}',
    search_vector TSVECTOR GENERATED ALWAYS AS (
        to_tsvector('english', coalesce(name,'') || ' ' || coalesce(description,''))
    ) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_menu_items_search ON menu_items USING GIN(search_vector);
CREATE INDEX idx_menu_items_attributes ON menu_items USING GIN(attributes);
```

**Immutable audit tables — PostgreSQL trigger:**
```sql
CREATE OR REPLACE FUNCTION prevent_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Modification of audit log records is prohibited';
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Applied to: rule_hit_logs, order_status_logs, privilege_escalation_logs
CREATE TRIGGER no_modify_rule_hit_logs
    BEFORE UPDATE OR DELETE ON rule_hit_logs
    FOR EACH ROW EXECUTE FUNCTION prevent_modification();
```

**analytics_events — Monthly partitioning:**
```sql
CREATE TABLE analytics_events (
    id BIGSERIAL,
    event_type VARCHAR(50) NOT NULL,
    device_fingerprint_id BIGINT,
    session_id VARCHAR(255),
    payload JSONB,
    trace_id UUID NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Initial partitions created by migration + monthly command
CREATE TABLE analytics_events_2026_04 PARTITION OF analytics_events
    FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');
```

**Materialized view for analytics:**
```sql
CREATE MATERIALIZED VIEW mv_daily_analytics AS
SELECT
    date_trunc('day', created_at) AS day,
    COUNT(DISTINCT session_id) AS dau,
    COUNT(DISTINCT device_fingerprint_id) AS unique_devices,
    SUM(CASE WHEN event_type = 'order_settled' THEN (payload->>'total')::decimal ELSE 0 END) AS gmv,
    COUNT(CASE WHEN event_type = 'page_view' THEN 1 END) AS page_views,
    COUNT(CASE WHEN event_type = 'add_to_cart' THEN 1 END) AS add_to_carts,
    COUNT(CASE WHEN event_type = 'checkout_started' THEN 1 END) AS checkouts_started,
    COUNT(CASE WHEN event_type = 'order_placed' THEN 1 END) AS orders_placed,
    COUNT(CASE WHEN event_type = 'order_settled' THEN 1 END) AS orders_settled
FROM analytics_events
GROUP BY 1
WITH DATA;

CREATE UNIQUE INDEX idx_mv_daily_analytics_day ON mv_daily_analytics(day);
```

---

## 5. Interface Contracts

### REST-Style API Endpoints

All endpoints are consumed by Livewire components via internal HTTP calls or direct service injection. They are also available for direct tablet communication.

#### Menu & Search

```
GET  /api/menu/search
  Query: ?q=burger&price_min=5&price_max=20&category=main&allergen_exclude=nuts,gluten
         &sort=relevance|newest|price_asc|price_desc&page=1
  Response 200: { items: MenuItemResource[], total: int, trending: string[] }
  Response 422: { message: "Blocked term", suggestion: "Try: [trending terms]" }

GET  /api/menu/items/{id}
  Response 200: MenuItemResource
  Response 404: { message: "Item not found" }

GET  /api/menu/categories
  Response 200: { categories: CategoryResource[] }
```

#### Cart

```
POST /api/cart/items
  Body: { menu_item_id: int, quantity: int, flavor_preference?: string, note?: string }
  Response 201: CartResource
  Response 422: { errors: { note: ["Max 140 characters"] } }

PATCH /api/cart/items/{id}
  Body: { quantity?: int, flavor_preference?: string, note?: string }
  Response 200: CartResource
  Response 404: { message: "Cart item not found" }

DELETE /api/cart/items/{id}
  Response 200: CartResource

GET /api/cart
  Response 200: CartResource (with itemized subtotal, tax breakdown, discount preview)
```

#### Orders

```
POST /api/orders
  Body: { cart_id: int }
  Response 201: OrderResource (status: pending_confirmation)
  Response 409: { message: "Cart has been modified", current_version: int }

PATCH /api/orders/{id}/status
  Body: { status: string, version: int, manager_pin?: string, cancel_reason?: string }
  Response 200: OrderResource
  Response 409: { message: "Version conflict", current_version: int, current_status: string }
  Response 403: { message: "Manager PIN required for this action" }
  Response 422: { message: "Invalid transition from {current} to {requested}" }

GET /api/orders/{id}
  Response 200: OrderResource (includes status, version, items with locked_at)

GET /api/orders?status=pending_confirmation,in_preparation&role=kitchen
  Response 200: { orders: OrderResource[] }
```

#### Payments

```
POST /api/payments/intents
  Body: { order_id: int }
  Response 201: { reference: uuid, amount: decimal, hmac: string, nonce: string, expires_at: timestamp }
  Response 409: { message: "Order status changed — payment intent invalidated" }
  Response 422: { message: "Order not in payable state" }

POST /api/payments/confirm
  Body: { reference: uuid, hmac: string, nonce: string, method: string, notes?: string }
  Response 200: { confirmation_id: int, order_status: "settled" }
  Response 400: { message: "HMAC verification failed" }
  Response 410: { message: "Payment intent expired" }
  Response 409: { message: "Nonce already used (idempotent: returning existing confirmation)" , confirmation_id: int }

GET /api/payments/reconciliation
  Response 200: { tickets: IncidentTicketResource[] }
  Auth: Manager, Administrator

PATCH /api/payments/reconciliation/{id}
  Body: { action: "settle"|"cancel", reason_code: string, receipt_reference?: string, manager_pin: string }
  Response 200: IncidentTicketResource
  Response 403: { message: "Manager PIN required" }
```

#### Risk & Security

```
GET  /api/time-sync
  Response 200: { server_time: int (Unix timestamp) }

POST /api/captcha/verify
  Body: { answer: string, challenge_id: string }
  Response 200: { valid: true }
  Response 422: { valid: false, message: "Incorrect answer" }
```

#### Admin (all require role:administrator)

```
CRUD /api/admin/menu/items        — Standard CRUD
CRUD /api/admin/menu/categories   — Standard CRUD
CRUD /api/admin/promotions        — Standard CRUD
CRUD /api/admin/users             — Standard CRUD
CRUD /api/admin/security/blacklist — Standard CRUD
CRUD /api/admin/security/whitelist — Standard CRUD
CRUD /api/admin/trending-searches  — Standard CRUD (max 20 enforced)
CRUD /api/admin/banned-words       — Standard CRUD
CRUD /api/admin/tax-rules          — Standard CRUD

GET  /api/admin/analytics/dashboard
  Query: ?from=2026-04-01&to=2026-04-05
  Response 200: { dau: [], gmv: [], conversion: [], funnel: {}, retention: {} }

GET  /api/admin/security/audit-log
  Query: ?type=rate_limit|blacklist|captcha|step_up&page=1
  Response 200: { logs: RuleHitLogResource[], escalations: PrivilegeEscalationLogResource[] }

GET  /api/admin/alerts
  Response 200: { alerts: AdminAlertResource[] }
```

### Error Response Format (Global)

All errors follow a consistent JSON structure:

```json
{
  "message": "Human-readable error message",
  "error_code": "STALE_VERSION",
  "errors": {},
  "trace_id": "uuid"
}
```

HTTP status codes used:
- `400` — Bad request (HMAC failure, malformed input)
- `401` — Unauthenticated
- `403` — Forbidden (role check failed, step-up required)
- `404` — Resource not found
- `409` — Conflict (version mismatch, duplicate nonce)
- `410` — Gone (expired payment intent)
- `422` — Validation error
- `429` — Rate limited

---

## 6. State Transitions

### Order State Machine

```
                    ┌──────────────────────────────────────────────────────┐
                    │              ORDER STATE MACHINE                      │
                    │                                                       │
                    │  ┌─────────────────────┐                             │
                    │  │ Pending Confirmation │                             │
                    │  └────────┬────────────┘                             │
                    │           │                                           │
                    │    Cashier/Manager confirms                          │
                    │           │                                           │
                    │           ▼                                           │
                    │  ┌─────────────────────┐                             │
                    │  │  In Preparation     │◄── items locked_at set      │
                    │  └────────┬────────────┘                             │
                    │           │                                           │
                    │    Kitchen marks ready                                │
                    │           │                                           │
                    │           ▼                                           │
                    │  ┌─────────────────────┐                             │
                    │  │      Served         │                             │
                    │  └────────┬────────────┘                             │
                    │           │                                           │
                    │    Payment confirmed                                  │
                    │           │                                           │
                    │           ▼                                           │
                    │  ┌─────────────────────┐                             │
                    │  │      Settled        │                             │
                    │  └─────────────────────┘                             │
                    │                                                       │
                    │  CANCELLATION PATHS:                                  │
                    │  Pending Confirmation ──► Canceled (Cashier/Manager)  │
                    │  In Preparation ──► Canceled (Manager PIN ONLY)       │
                    │  Served ──► Canceled (Manager PIN ONLY)               │
                    │  Settled ──► NOT CANCELLABLE                          │
                    │  Canceled ──► TERMINAL                                │
                    └──────────────────────────────────────────────────────┘
```

### Transition Rules Table

| From | To | Allowed Roles | Step-Up Required | Side Effects |
|---|---|---|---|---|
| pending_confirmation | in_preparation | Cashier, Manager | No | Set `confirmed_by`; set `locked_at` on all order items |
| in_preparation | served | Kitchen, Manager | No | Set `prepared_by` |
| served | settled | Cashier, Manager | Only if payment ambiguous | Set `settled_by`; requires payment confirmation |
| pending_confirmation | canceled | Cashier, Manager | No | Set `canceled_by`, `cancel_reason` |
| in_preparation | canceled | Manager | **Yes — Manager PIN** | Set `canceled_by`, `cancel_reason`; write to privilege_escalation_logs |
| served | canceled | Manager | **Yes — Manager PIN** | Set `canceled_by`, `cancel_reason`; write to privilege_escalation_logs |

### Every transition MUST:
1. Run inside a DB transaction
2. Check `$request->version === $order->version` (reject with 409 if stale)
3. Increment `$order->version`
4. Append to `order_status_logs`
5. Check role authorization
6. Check step-up requirement if applicable

### Payment Intent State Machine

```
  pending ──► confirmed (on payment confirmation)
  pending ──► failed (on HMAC/nonce failure)
  pending ──► canceled (on order status change / zombie detection)
  pending ──► expired (after 5-minute window)
  confirmed ──► (terminal)
  expired ──► reconciling (if order shows as paid externally)
  reconciling ──► confirmed (after manager review)
  reconciling ──► failed (after manager review)
```

---

## 7. Permission & Access Boundaries

### Route-Level Guards

```php
// web.php route groups
Route::middleware(['web'])->group(function () {
    // Guest/kiosk routes — no auth required, device fingerprint tracked
    Route::middleware(['device.fingerprint', 'rate.limit'])->group(function () {
        // Menu browsing, cart, checkout, order tracking
    });

    // Staff routes — auth required
    Route::middleware(['auth', 'role:cashier,kitchen,manager,administrator'])->group(function () {
        // Order list, order actions
    });

    // Admin routes — admin only
    Route::middleware(['auth', 'role:administrator'])->group(function () {
        // All admin CRUD, analytics, security config
    });

    // Manager+ routes — manager or admin
    Route::middleware(['auth', 'role:manager,administrator'])->group(function () {
        // Reconciliation queue, incident handling
    });
});
```

### Object-Level Authorization (Policies)

Every API call validates resource ownership / access:

- **OrderPolicy**: Guests can only view their own orders (matched by session/device fingerprint). Staff can view all orders. Only the correct role can perform state transitions.
- **CartPolicy**: Guests can only modify their own cart (session-bound). Staff cannot modify guest carts directly.
- **PaymentPolicy**: Only Cashier/Manager can confirm payments. Only Manager/Admin can access reconciliation queue.
- **Admin resources**: Only Administrator can CRUD menu items, promotions, users, security rules.

### Step-Up Verification Boundaries

These actions require Manager PIN even if the user has the Manager role:

| Action | Why |
|---|---|
| Cancel order after In Preparation | Prevents food waste; requires deliberate authorization |
| Override discount > $20.00 | Revenue protection |
| Settle order when payment is ambiguous | Cash fraud prevention |

Every step-up writes to `privilege_escalation_logs` with the specific manager, the hashed PIN used, and the action taken.

### Tenant / Data Isolation

Single-restaurant system — no multi-tenancy. However:
- Guest sessions are isolated by session ID + device fingerprint
- Staff actions are attributed to the authenticated user
- No guest can see another guest's cart or order
- Device fingerprint blacklisting is per-device, not per-session

---

## 8. Failure Paths

### Comprehensive Failure Path Catalog

#### Menu & Search
| Failure | Handling | HTTP Code |
|---|---|---|
| Search with banned/profanity term | Block query, return inline message + trending suggestions | 422 |
| Search with empty query | Return trending terms + popular items | 200 |
| Menu item not found | 404 with clear message | 404 |
| Invalid price range (min > max) | Validation error | 422 |
| Allergen filter with no results | Return empty set with "No items match your filters" | 200 |

#### Cart
| Failure | Handling | HTTP Code |
|---|---|---|
| Add inactive/deleted menu item | Reject with "Item no longer available" | 422 |
| Note exceeds 140 characters | Validation error | 422 |
| Quantity <= 0 | Validation error | 422 |
| Price changed since item was added | Flag with `price_changed: true` on CartResource; guest must acknowledge | 200 (with warning) |
| Cart item not found | 404 | 404 |

#### Orders
| Failure | Handling | HTTP Code |
|---|---|---|
| Version conflict (stale version) | Reject with 409, return current version + status | 409 |
| Invalid state transition | Reject with 422 explaining the invalid path | 422 |
| Insufficient role for transition | 403 with role requirement message | 403 |
| Manager PIN required but not provided | 403 with step-up requirement | 403 |
| Manager PIN incorrect | 403, increment failed PIN attempts, log to rule_hit_logs | 403 |
| Cancel locked item (Kitchen started) | 403 with "Manager authorization required" | 403 |
| Zombie state (order changed during checkout) | Invalidate payment intent, redirect to reconcile screen | 409 |

#### Payments
| Failure | Handling | HTTP Code |
|---|---|---|
| HMAC verification fails | 400 with "Signature verification failed", log to rule_hit_logs | 400 |
| Nonce already used (replay) | 409, return existing confirmation (idempotent) | 409 |
| Payment intent expired (>5 min) | 410 with "Intent expired, create new intent" | 410 |
| Clock drift detected (>30s) | Tablet shows warning banner, blocks payment operations | N/A (client) |
| Duplicate payment confirmation | Return existing confirmation (idempotent) | 200 |
| Order status changed before payment | Invalidate intent, return 409 | 409 |
| "Paid but not settled" stuck state | Auto-detect via reconciliation command, create incident ticket | N/A (async) |

#### Authentication & Security
| Failure | Handling | HTTP Code |
|---|---|---|
| Invalid credentials | 401, increment failed login counter | 401 |
| 5+ failed logins → CAPTCHA required | Return CAPTCHA challenge | 429 + challenge |
| 3 rapid cart re-pricings in 60s → CAPTCHA | Return CAPTCHA challenge | 429 + challenge |
| CAPTCHA answer incorrect | 422, regenerate challenge | 422 |
| Rate limit exceeded (device or IP) | 429 with retry-after header, log to rule_hit_logs | 429 |
| Blacklisted device/IP/username | 403 "Access denied" (no further detail), log to rule_hit_logs | 403 |
| Unauthorized route access | 401 | 401 |
| Insufficient role | 403 | 403 |

#### Analytics
| Failure | Handling | HTTP Code |
|---|---|---|
| Materialized view refresh fails | Log error, serve stale data with "Last updated: [time]" banner | 200 (degraded) |
| Monthly partition missing | Auto-create via scheduled command; log alert | N/A (async) |

---

## 9. Logging Strategy

### Log Levels & Categories

| Category | Level | What's Logged | What's NEVER Logged |
|---|---|---|---|
| **request** | INFO | Method, path, status code, duration, trace_id, device_fingerprint_hash | Request bodies with passwords/PINs |
| **auth** | INFO/WARN | Login success/failure, role escalation, session creation | Passwords, PINs, tokens |
| **order** | INFO | State transitions, version bumps, actor ID | Customer notes (encrypted) |
| **payment** | INFO/WARN | Intent creation, confirmation, HMAC failures, reconciliation | HMAC keys, nonces, payment details |
| **risk** | WARN | Rate limit hits, CAPTCHA triggers, blacklist blocks, step-up actions | Device fingerprint raw traits |
| **error** | ERROR | Unhandled exceptions with stack trace, trace_id | Sensitive user data |
| **analytics** | DEBUG | Event tracking writes, aggregation runs | PII |

### Implementation

```php
// StructuredLogFormatter.php — every log entry includes:
[
    'timestamp' => '2026-04-05T12:00:00Z',
    'level' => 'INFO',
    'category' => 'order',
    'trace_id' => 'uuid',
    'message' => 'Order #1234 transitioned from pending_confirmation to in_preparation',
    'context' => [
        'order_id' => 1234,
        'actor_id' => 5,
        'actor_role' => 'cashier',
        'version' => 3,
    ]
]
```

### SensitiveDataScrubber

Applied as a log processor. Redacts any field matching: `password`, `pin`, `manager_pin`, `secret`, `hmac_key`, `token`, `nonce`, `note`, `notes`. Replaces with `[REDACTED]`.

### Log Rotation

- `config/logging.php` channel: `daily` with 30-day retention.
- Logs stored at `/var/log/harborbite/` inside the container, mounted as a Docker volume.

### Threshold Alerts

Scheduled command `CheckAlertThresholdsCommand` runs every 5 minutes:
- Error rate > 5% in last hour → CRITICAL alert
- Risk rule hits > 50 in last hour → WARNING alert
- GMV drop > 50% day-over-day → WARNING alert
- Failed logins > 100 in last hour → CRITICAL alert

Alerts are written to `admin_alerts` table and displayed as banners on the admin dashboard.

---

## 10. Testing Strategy

### Target: 90%+ Meaningful Coverage

### Test Pyramid

```
         ┌───────────┐
         │ Playwright │  ~15 E2E scenarios
         │    E2E     │  (happy + failure paths)
         ├───────────┤
         │ Feature /  │  ~80 integration tests
         │Integration │  (real DB, real HTTP)
         ├───────────┤
         │   Unit     │  ~120 unit tests
         │  (Domain)  │  (pure PHP, no DB)
         └───────────┘
```

### Unit Test Strategy (Domain layer — pure PHP)

**Approach: TDD — tests written before implementation.**

All domain classes are tested without any Laravel dependency. These tests run in milliseconds.

| Domain Class | Tests Required |
|---|---|
| `OrderStateMachine` | Every valid transition succeeds; every invalid transition throws `InvalidTransitionException`; version mismatch throws `StaleVersionException`; role enforcement (cashier cannot cancel in_preparation); kitchen lock prevents guest/cashier deletion |
| `PromotionEvaluator` | Single promo applied; best of multiple promos selected; mutual exclusion enforced; time window active/expired/not-yet; BOGO calculation; % off second item; threshold not met; empty cart; cart with single item |
| `HmacSigner` | Valid signature verifies; tampered params fail; expired timestamp rejected; replayed nonce rejected; different keys produce different signatures |
| `ProfanityFilter` | Exact match blocked; case-insensitive match; substring match; clean query passes; suggestions returned from trending |
| `TaxCalculator` | Hot food rate applied; cold food rate; mixed items in cart; effective date range respected; no matching rule falls back to default |
| `RateLimitEvaluator` | Under threshold passes; at threshold blocks; different windows independent; reset after window expires |
| `CaptchaTriggerEvaluator` | 5 failed logins triggers; 4 does not; 3 rapid re-pricings triggers; timing edge (61st second resets) |
| `DeviceFingerprintGenerator` | Same input produces same hash; different UA produces different hash; salt changes output; missing fields handled |
| `StepUpVerifier` | Correct PIN passes; incorrect PIN fails; missing PIN when required fails |
| `CartValidator` | Note > 140 chars rejected; quantity <= 0 rejected; valid input accepted |
| `AllergenFilter` | "contains_nuts" excludes nut items; multiple allergens combine; no allergens returns all |
| `OrderStatus` enum | `transitions()` returns correct next-states for each status; terminal states return empty |

### Integration Test Strategy (Feature tests — real DB in Docker)

**Approach: Tests hit real PostgreSQL via Eloquent. Transaction-wrapped for isolation.**

| Test Suite | Key Scenarios |
|---|---|
| `MenuSearchTest` | Full-text search returns ranked results; price range boundaries; JSONB attribute filter; allergen exclusion; sort by relevance/newest/price; banned word blocked with 422; trending terms returned |
| `CartManagementTest` | Add item creates cart; add duplicate increments quantity; remove item; edit quantity; note length validated at DB level; price snapshot recorded |
| `TaxCalculationTest` | Per-item tax calculation; mixed tax categories; tax rule effective date |
| `OrderLifecycleTest` | Full happy path: pending → in_prep → served → settled; version increments correctly; status log entries created; actor IDs recorded |
| `VersionConflictTest` | Two concurrent transitions: first succeeds, second gets 409; 409 response includes current version |
| `KitchenLockTest` | Item locked_at set on in_preparation; guest cannot delete locked item; manager can cancel with PIN |
| `ZombieStateTest` | Order canceled while checkout in progress → payment intent invalidated |
| `PromotionApplicationTest` | Best offer wins across all 4 promo types; mutual exclusion groups; time window filtering |
| `PaymentIntentTest` | Intent created with HMAC; confirmation succeeds; order transitions to settled |
| `HmacVerificationTest` | Tampered amount → 400; expired nonce → 410; replayed nonce → 409 (idempotent) |
| `IdempotencyTest` | Same reference submitted twice → same confirmation returned |
| `ReconciliationTest` | Stuck orders detected; incident tickets created; manager settles with reason code |
| `RateLimitingTest` | 31st checkout in 10 min → 429; 11th registration in 1 hour → 429; different devices independent |
| `DeviceFingerprintTest` | New fingerprint created; existing fingerprint updated (last_seen_at); cross-tablet lookup by hash |
| `BlacklistTest` | Blacklisted device → 403; blacklisted IP range (CIDR) → 403; whitelist overrides rate limit |
| `CaptchaTriggerTest` | 5 failed logins → CAPTCHA; correct answer clears; 3 rapid re-pricings → CAPTCHA |
| `StepUpVerificationTest` | Cancel in_preparation without PIN → 403; with correct PIN → success + escalation log entry; wrong PIN → 403 + log |
| `LoginTest` | Valid login succeeds; invalid login → 401; role set correctly in session |
| `RoleAccessTest` | Kitchen cannot access admin routes → 403; Cashier cannot cancel in_preparation → 403; Guest cannot access staff routes → 401 |
| `EventTrackingTest` | Page view, add_to_cart, checkout, settled events logged with trace_id |
| `AggregationTest` | Materialized view refresh computes correct DAU, GMV |
| `UserManagementTest` | Admin can CRUD users; non-admin → 403 |
| `PromotionManagementTest` | Admin can CRUD promos; time window validation |
| `SecurityRulesTest` | Admin can CRUD blacklist/whitelist entries; trending terms max 20 enforced |

### E2E Test Strategy (Playwright — full browser against Docker stack)

**Approach: Playwright tests run against the fully Dockerized application. They verify both UI behavior AND backend responses.**

| Test File | Scenarios |
|---|---|
| `guest-ordering.spec.ts` | Guest opens kiosk → browses menu → searches → adds items → views cart with subtotal/tax/discount → places order → sees order tracker with "Pending Confirmation" status |
| `search-and-filter.spec.ts` | Search by keyword → results appear; filter by price range → results narrow; filter by allergen exclusion → allergen items hidden; sort by price → order correct; search banned word → inline error + trending suggestions shown |
| `promotion-display.spec.ts` | Add items over $30 → 10% discount appears; add BOGO items → BOGO applied; verify mutual exclusion (BOGO removes percentage); time window display in local time |
| `staff-order-flow.spec.ts` | Cashier logs in → sees pending orders → confirms order → Kitchen sees "In Preparation" → Kitchen marks served → Cashier settles → order shows "Settled" |
| `manager-overrides.spec.ts` | Manager cancels In Preparation order → PIN modal appears → correct PIN → order canceled; wrong PIN → error shown; discount override > $20 → PIN required |
| `payment-flow.spec.ts` | Full checkout → payment intent created → staff confirms payment → order settled; expired intent → error message; verify idempotency (double-click confirm) |
| `admin-dashboard.spec.ts` | Admin logs in → sees analytics dashboard → charts render → filter by date range; manage menu items → add/edit/delete; manage promotions; manage users |
| `error-paths.spec.ts` | Attempt to access admin route as guest → redirect to login; version conflict → conflict banner shown; rate limit → 429 message; blacklisted device → 403 page |
| `security-enforcement.spec.ts` | 5 failed logins → CAPTCHA shown → correct answer → login succeeds; verify CAPTCHA image renders; blacklisted device cannot browse |

### run_tests.sh

```bash
#!/bin/bash
set -e

echo "=== HarborBite Test Suite ==="
echo ""

# 1. Unit Tests (no DB required)
echo "--- Running Unit Tests ---"
docker compose exec app php artisan test --testsuite=Unit --stop-on-failure
echo ""

# 2. Integration Tests (requires DB)
echo "--- Running Integration/Feature Tests ---"
docker compose exec app php artisan test --testsuite=Feature --stop-on-failure
echo ""

# 3. E2E Tests (Playwright against running app)
echo "--- Running E2E Tests (Playwright) ---"
docker compose exec playwright npx playwright test --reporter=list
echo ""

echo "=== All Tests Passed ==="
```

Exit code is non-zero if any step fails (due to `set -e`).

### Coverage Goals by Module

| Module | Unit Coverage | Integration Coverage | E2E Coverage |
|---|---|---|---|
| Order State Machine | 95%+ | 90%+ | Happy path + conflict |
| Promotion Engine | 95%+ | 90%+ | Visual discount display |
| Payment/HMAC | 95%+ | 90%+ | Full flow + error paths |
| Risk/Fraud | 90%+ | 85%+ | CAPTCHA + rate limit |
| Menu/Search | 85%+ | 90%+ | Search + filter UI |
| Cart | 85%+ | 90%+ | Add/remove/edit UI |
| Auth/RBAC | 90%+ | 90%+ | Login + role enforcement |
| Analytics | 80%+ | 85%+ | Dashboard renders |

### Edge Cases Explicitly Covered

- Concurrent version conflict on same order from two tablets
- Promotion time window edge: checkout at 7:59 PM, promo ends at 8:00 PM
- BOGO with odd quantity (3 items: 1 free, 1 paid, 1 paid)
- Tax rule with overlapping effective dates
- Payment intent created, order canceled by manager before confirmation
- Device fingerprint with missing screen traits (graceful degradation)
- 140-character note with Unicode (multi-byte characters)
- Cart with 0 items attempting checkout
- Manager PIN with special characters
- HMAC with exact 5-minute boundary (within tolerance)
- Rate limit window boundary (request at exactly 10-minute mark)

---

## 11. Docker Execution Assumptions

### docker-compose.yml Services

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    volumes:
      - ./src:/var/www/html
      - harborbite-logs:/var/log/harborbite
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=harborbite
      - DB_USERNAME=harborbite
      - DB_PASSWORD=${DB_PASSWORD}
      - QUEUE_CONNECTION=database
      - SESSION_DRIVER=database
      - CACHE_STORE=database
      - PAYMENT_HMAC_KEY=${PAYMENT_HMAC_KEY}
      - DEVICE_FINGERPRINT_SALT=${DEVICE_FINGERPRINT_SALT}
    networks:
      - harborbite

  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    ports:
      - "8080:80"
    depends_on:
      - app
    networks:
      - harborbite

  postgres:
    image: postgres:16-alpine
    environment:
      - POSTGRES_DB=harborbite
      - POSTGRES_USER=harborbite
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    volumes:
      - postgres-data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U harborbite"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - harborbite

  playwright:
    image: mcr.microsoft.com/playwright:v1.50.0-jammy
    working_dir: /tests
    volumes:
      - ./src/tests/E2E:/tests
    depends_on:
      - nginx
    environment:
      - BASE_URL=http://nginx:80
    networks:
      - harborbite
    profiles:
      - testing

volumes:
  postgres-data:
  harborbite-logs:

networks:
  harborbite:
    driver: bridge
```

### Dockerfile (app)

```dockerfile
FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache postgresql-dev supervisor nodejs npm

# PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pcntl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps
COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Install Node deps + build assets
COPY src/package.json src/package-lock.json ./
RUN npm ci && npm run build

# Copy application
COPY src/ .

# Generate key if needed, run migrations, seed
COPY docker/app/supervisord.conf /etc/supervisord.conf
COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

### entrypoint.sh (One-Command Start)

```bash
#!/bin/sh
set -e

# Wait for PostgreSQL
until pg_isready -h postgres -U harborbite; do
  echo "Waiting for PostgreSQL..."
  sleep 1
done

# Run migrations
php artisan migrate --force

# Seed (only if tables are empty)
php artisan db:seed --force

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisord (FPM + queue worker + scheduler)
exec "$@"
```

### supervisord.conf

```ini
[supervisord]
nodaemon=true

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true

[program:queue-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true

[program:scheduler]
command=sh -c "while true; do php /var/www/html/artisan schedule:run --verbose --no-interaction >> /var/log/harborbite/scheduler.log 2>&1; sleep 60; done"
autostart=true
autorestart=true
```

### Docker Verification Checkpoints

| When | What to Verify |
|---|---|
| After Module A (Foundation) | `docker compose up --build` starts cleanly; migrations run; login page loads; `run_tests.sh` passes |
| After Module B (Menu) | Menu page loads; search returns results; banned word blocked |
| After Module C (Cart) | Items can be added/removed; cart persists across page loads |
| After Module D (Orders) | Full order lifecycle works; version conflict detected |
| After Module E (Promotions) | Discount appears on checkout; best offer wins |
| After Module F (Payments) | Payment intent + confirm works; HMAC failure rejected |
| After Module G (Risk) | Rate limiting active; CAPTCHA triggers; blacklist blocks |
| After Module H (Analytics) | Dashboard renders with data; materialized view populated |
| Final | Full `run_tests.sh` including Playwright E2E |

---

## 12. UI/UX Design System

### Design Foundation: Tailwind CSS v4 with Custom Theme Tokens

Since the system is fully offline with no external CDN access, we use Tailwind CSS v4's `@theme` directive to define a complete design system. While the development directive references Fluent UI (a React component library), our stack is Laravel + Livewire + Blade. We implement Fluent UI's **design principles** (visual hierarchy, 8px grid, interaction states, shimmer loading) using Tailwind CSS tokens and Alpine.js, achieving the same visual quality natively.

### Theme Tokens (resources/css/app.css)

```css
@import "tailwindcss";

@theme {
  /* === Color Palette (Fluent-inspired neutral + brand) === */
  --color-brand-primary: oklch(0.55 0.15 250);     /* Deep blue */
  --color-brand-primary-hover: oklch(0.48 0.15 250);
  --color-brand-primary-active: oklch(0.42 0.15 250);
  --color-brand-secondary: oklch(0.90 0.03 250);
  --color-brand-accent: oklch(0.65 0.20 145);       /* Green for success/confirm */

  --color-surface-primary: oklch(1.0 0 0);           /* White — main canvas */
  --color-surface-secondary: oklch(0.97 0 0);        /* Light grey — sidebar/panels */
  --color-surface-tertiary: oklch(0.94 0 0);         /* Darker grey — cards */
  --color-surface-elevated: oklch(1.0 0 0);          /* Elevated cards with shadow */

  --color-text-primary: oklch(0.15 0 0);
  --color-text-secondary: oklch(0.40 0 0);
  --color-text-disabled: oklch(0.65 0 0);
  --color-text-on-brand: oklch(1.0 0 0);

  --color-status-success: oklch(0.65 0.20 145);
  --color-status-warning: oklch(0.75 0.15 85);
  --color-status-error: oklch(0.55 0.22 25);
  --color-status-info: oklch(0.60 0.15 250);

  --color-border-default: oklch(0.88 0 0);
  --color-border-strong: oklch(0.75 0 0);
  --color-border-focus: oklch(0.55 0.15 250);

  /* === Typography Scale === */
  --font-sans: 'Inter', 'Segoe UI', system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', monospace;
  --text-display: 2.25rem;    /* 36px — Dashboard headers */
  --text-title: 1.5rem;       /* 24px — Page titles */
  --text-subtitle: 1.25rem;   /* 20px — Section headers */
  --text-body: 1rem;          /* 16px — Body text */
  --text-caption: 0.875rem;   /* 14px — Labels, metadata */
  --text-small: 0.75rem;      /* 12px — Timestamps, hints */

  /* === Spacing (8px grid) === */
  --spacing-1: 0.25rem;   /* 4px — tight padding */
  --spacing-2: 0.5rem;    /* 8px — base unit */
  --spacing-3: 0.75rem;   /* 12px */
  --spacing-4: 1rem;      /* 16px — standard padding */
  --spacing-5: 1.25rem;   /* 20px */
  --spacing-6: 1.5rem;    /* 24px — section gaps */
  --spacing-8: 2rem;      /* 32px — major section gaps */
  --spacing-10: 2.5rem;   /* 40px */
  --spacing-12: 3rem;     /* 48px — page padding */

  /* === Shadows === */
  --shadow-card: 0 1px 3px oklch(0 0 0 / 0.1);
  --shadow-elevated: 0 4px 12px oklch(0 0 0 / 0.12);
  --shadow-modal: 0 8px 32px oklch(0 0 0 / 0.2);

  /* === Border Radius === */
  --radius-sm: 0.25rem;   /* 4px */
  --radius-md: 0.5rem;    /* 8px */
  --radius-lg: 0.75rem;   /* 12px */
  --radius-xl: 1rem;      /* 16px */
  --radius-full: 9999px;

  /* === Transitions === */
  --ease-default: cubic-bezier(0.2, 0, 0, 1);
  --duration-fast: 150ms;
  --duration-normal: 250ms;
  --duration-slow: 400ms;

  /* === Breakpoints (tablet-first) === */
  --breakpoint-tablet: 768px;
  --breakpoint-desktop: 1024px;
  --breakpoint-wide: 1280px;
}
```

### Visual Hierarchy Rules

| Area | Background | Purpose |
|---|---|---|
| Main canvas (menu grid, order list) | `surface-primary` (white) | Content focus |
| Sidebar (categories, filters) | `surface-secondary` (light grey) | Navigation separation |
| Cards (menu items, order cards) | `surface-elevated` + `shadow-card` | Scannable units |
| Header bar | `brand-primary` | App identity, navigation |
| Status badges | Respective `status-*` colors | Quick visual scanning |
| Modals (step-up, CAPTCHA) | `surface-primary` + `shadow-modal` + backdrop | Focus attention |

### 8px Grid System

All spacing uses multiples of 8px (`spacing-2` = 8px):
- Component internal padding: `spacing-4` (16px)
- Gap between cards: `spacing-4` (16px)
- Section separation: `spacing-8` (32px)
- Page margin: `spacing-6` (24px) on tablet, `spacing-12` (48px) on desktop

### Interaction States (Every Interactive Element)

```css
/* Button example — all states defined */
.btn-primary {
  @apply bg-brand-primary text-text-on-brand rounded-md px-6 py-3
         transition-colors duration-fast ease-default
         hover:bg-brand-primary-hover
         active:bg-brand-primary-active
         focus:outline-2 focus:outline-offset-2 focus:outline-border-focus
         disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none;
}
```

Every interactive element (button, input, card, link, toggle) must define:
- **Default** state
- **Hover** state (color shift or shadow)
- **Active/Pressed** state (deeper color or scale)
- **Focus** state (visible outline for accessibility)
- **Disabled** state (reduced opacity, no pointer events)

### Async/Loading States

All data-fetching transitions use either:
- **Shimmer/Skeleton** — for initial page loads (menu grid, dashboard charts)
- **Spinner** — for inline actions (add to cart, confirm order)

```blade
{{-- Livewire loading skeleton --}}
<div wire:loading.class="animate-pulse">
    <div wire:loading class="h-6 bg-surface-tertiary rounded w-3/4"></div>
    <div wire:loading class="h-4 bg-surface-tertiary rounded w-1/2 mt-2"></div>
</div>
<div wire:loading.remove>
    {{ $actualContent }}
</div>

{{-- Inline action spinner --}}
<button wire:click="addToCart({{ $itemId }})" class="btn-primary">
    <span wire:loading.remove wire:target="addToCart({{ $itemId }})">Add to Cart</span>
    <span wire:loading wire:target="addToCart({{ $itemId }})">
        <svg class="animate-spin h-5 w-5" ...></svg>
    </span>
</button>
```

### Layout Stability (No CLS)

- All images: explicit `width` and `height` attributes or `aspect-ratio` in CSS
- Dynamic text: container has `min-height` set
- Cart badge counter: fixed-width container (`min-w-6`)
- Menu grid: fixed-height card containers
- Dashboard charts: fixed-height `<canvas>` elements

### Touch-Friendly (Tablet/Kiosk)

- Minimum tap target: 44x44px (`min-h-11 min-w-11`)
- Font sizes: minimum 16px for body text (no zoom triggering on iOS)
- Button padding: minimum `py-3 px-6` (12px vertical, 24px horizontal)
- Input fields: `h-12` (48px height) for comfortable touch
- Spacing between interactive elements: minimum `gap-3` (12px) to prevent misclicks

### Layout Structure per Screen

**Kiosk Layout (Guest):**
```
┌──────────────────────────────────────────────┐
│  Header: Brand logo + Cart icon (badge)      │
├────────────┬─────────────────────────────────┤
│  Sidebar   │  Main Content                   │
│  (filters) │  (menu grid / cart / checkout)  │
│  240px     │  flex-1                         │
│            │                                  │
└────────────┴─────────────────────────────────┘
```

**Staff Layout:**
```
┌──────────────────────────────────────────────┐
│  Header: Role indicator + Active orders count│
├──────────────────────────────────────────────┤
│  Order Queue (full width grid of order cards)│
│  Status tabs: Pending | Preparing | Served   │
└──────────────────────────────────────────────┘
```

**Admin Layout:**
```
┌────────────┬─────────────────────────────────┐
│  Sidebar   │  Main Content                   │
│  Navigation│  (dashboard / CRUD / audit log) │
│  240px     │  flex-1                         │
│  - Dashboard                                 │
│  - Menu                                      │
│  - Promos                                    │
│  - Users                                     │
│  - Security                                  │
│  - Alerts                                    │
└────────────┴─────────────────────────────────┘
```

---

## 13. Module Breakdown

### Module A: Foundation & Infrastructure

**Responsibilities:**
- Laravel project scaffold with Docker configuration
- PostgreSQL connection, session/cache/queue via database driver
- User model with role enum, bcrypt password + optional manager PIN
- Authentication (login/logout) with session-based auth
- Role-based middleware (`role:cashier,manager`)
- Device fingerprint middleware (capture + store)
- Rate limit middleware (per-device, per-IP)
- Trace ID middleware (UUID per request, attached to all logs)
- Request logging middleware
- Structured log formatter + sensitive data scrubber
- Kiosk, staff, and admin Blade layouts with Tailwind theme tokens
- Time-sync endpoint
- Global error handler (JSON error response format)
- `config/harborbite.php` with all configurable thresholds
- Database seeder (roles, banned words, tax rules, demo data)
- `docker-compose.yml`, Dockerfiles, `entrypoint.sh`, `run_tests.sh`

**Inputs:** None (first module)

**Outputs:**
- Running Docker stack with `docker compose up --build`
- Login page functional
- Role-based route guarding verified
- Device fingerprint captured and stored
- Structured logs written with trace IDs
- `run_tests.sh` executes unit + feature tests

**Required Flows:**
1. `docker compose up --build` → app starts → migrations run → seeds run → login page accessible at `localhost:8080`
2. Staff login with username/password → session created → redirected to role-appropriate dashboard
3. Guest visits kiosk → device fingerprint captured → session created → no login required
4. Unauthorized route access → 401/403 JSON response
5. Time-sync endpoint returns server timestamp

**Failure Behavior:**
- Invalid credentials → 401 with "Invalid username or password"
- Missing required fields → 422 with validation errors
- Access denied → 403 with role requirement
- Rate limit exceeded → 429 with retry-after
- Blacklisted device → 403 "Access denied"

**Permissions:**
- Guest: no auth needed; device fingerprint tracked
- Staff: auth required; `role` middleware enforced
- Admin: auth required; `role:administrator` enforced

**Tests that should exist when done:**

*Unit:*
- `UserRole` enum — all roles defined, name/value mapping
- `DeviceFingerprintGenerator` — consistent hashing, salt sensitivity
- `RateLimitEvaluator` — threshold logic, window expiry
- `StepUpVerifier` — PIN verification

*Feature:*
- `LoginTest` — valid login, invalid login, role in session
- `RoleAccessTest` — guest vs cashier vs kitchen vs manager vs admin route access
- `DeviceFingerprintTest` — fingerprint created on first visit, found on second
- `RateLimitingTest` — request accepted under limit, blocked over limit

*Docker:*
- `docker compose up --build` starts without error
- `run_tests.sh` passes

---

### Module B: Menu Discovery & Search

**Responsibilities:**
- Menu categories and items CRUD (admin)
- PostgreSQL full-text search with `tsvector` + GIN index
- JSONB attribute filtering (category attributes: gluten-free, spicy level, contains nuts)
- Allergen negative filters ("contains nuts" → EXCLUDE items with nuts)
- Price-range filtering
- Sorting: relevance (ts_rank), newest (created_at), price (asc/desc)
- Profanity/banned word filter with inline messaging and trending term suggestions
- Trending search terms (admin pins up to 20 per location)
- Recent searches stored in session (not DB)
- `MenuBrowser` Livewire component with live search (`wire:model.live.debounce.300ms`)
- `MenuItemDetail` Livewire component
- Admin: `MenuManager` Livewire component for CRUD
- Banned word management

**Inputs:**
- Search query (keyword, filters, sort)
- Menu item data (admin CRUD)
- Banned words (admin CRUD)
- Trending terms (admin, max 20)

**Outputs:**
- Filtered, sorted menu items with pagination
- Inline error for banned terms with trending suggestions
- Menu management admin UI

**Required Flows:**
1. Guest types in search → debounced query → full-text search → results with relevance ranking
2. Guest applies price range filter → results narrow
3. Guest toggles "Hide allergens: nuts" → items with `contains_nuts: true` excluded
4. Guest sorts by price ascending → results reorder
5. Guest searches profanity → inline message "This search term is not allowed. Try: [trending terms]"
6. Admin adds/edits/removes menu items → changes reflected immediately
7. Admin pins trending terms → shown to guests on search page

**Failure Behavior:**
- Banned word → 422 with message + suggestions (NOT a blank page)
- Empty search → return trending terms + popular items
- Invalid price range → 422 validation error
- No results for filters → empty set with "No items match your filters" message
- Admin exceeds 20 trending terms → 422 "Maximum 20 trending terms allowed"

**Permissions:**
- Guest: read-only menu access
- Admin: CRUD on menu items, categories, banned words, trending terms

**Tests that should exist when done:**

*Unit:*
- `ProfanityFilter` — exact match, case insensitive, substring, clean query, suggestions
- `AllergenFilter` — single allergen exclusion, multiple, none
- `SearchQuery` value object — validates price range, sort options

*Feature:*
- `MenuSearchTest` — full-text ranking, price range, attribute filter, allergen exclusion, sort modes, banned word rejection, trending terms
- `MenuCrudTest` — admin creates/updates/deletes items and categories; non-admin → 403
- `AllergenFilteringTest` — "contains nuts" items excluded, "gluten free" items included

*E2E:*
- `search-and-filter.spec.ts` — visual verification of search results, filter toggles, sort dropdowns, banned word inline error, trending terms display

*Docker:*
- Menu page loads with seeded data
- Search returns results

---

### Module C: Cart Management

**Responsibilities:**
- Cart creation (per session + device fingerprint)
- Add items to cart (with price snapshot)
- Remove items from cart
- Edit quantities
- Select flavor preferences
- Leave notes (capped at 140 characters — enforced at domain, validation, and DB levels)
- Tax calculation using Tax-Rule Table (hot/cold food variances)
- Itemized subtotal display
- Estimated sales tax display (per-item based on tax category)
- Price change detection (compare snapshot to current price)
- `CartManager` Livewire component
- `CartSummary` Livewire component
- Tax rule management (admin)

**Inputs:**
- Menu item ID, quantity, flavor preference, note
- Tax rules (admin configuration)

**Outputs:**
- Cart with itemized lines, each showing: name, quantity, unit price, line total, tax
- Subtotal, tax total, grand total (discount placeholder for Module E)
- Price change warnings

**Required Flows:**
1. Guest clicks "Add to Cart" → cart created if none → item added with price snapshot → cart badge updates (Alpine.js counter)
2. Guest changes quantity → subtotal recalculates in real time
3. Guest types note → 140-char limit enforced → truncation warning if exceeded
4. Guest removes item → cart updates → empty cart shows "Your cart is empty"
5. Menu item price changes after add → cart shows "Price updated" warning on that item
6. Tax calculated per item based on `tax_category` → different rates for hot vs cold food

**Failure Behavior:**
- Add inactive item → 422 "Item no longer available"
- Note > 140 chars → 422 validation error (also truncated client-side via Alpine)
- Quantity <= 0 → 422 validation error
- Add item to cart when menu_item has been deleted → 422
- Empty cart at checkout → 422 "Cart is empty"

**Permissions:**
- Guest: own cart only (session-bound)
- Admin: CRUD tax rules

**Tests that should exist when done:**

*Unit:*
- `TaxCalculator` — hot food rate, cold food rate, mixed items, effective date range, default fallback
- `CartValidator` — note length, quantity bounds, inactive item rejection

*Feature:*
- `CartManagementTest` — add/remove/edit, price snapshot, empty cart, inactive item
- `TaxCalculationTest` — per-item tax, mixed categories, rule date range

*E2E:*
- Part of `guest-ordering.spec.ts` — add items, see cart update, edit quantity, verify subtotal/tax

*Docker:*
- Cart persists across page reloads (session in database)

---

### Module D: Order Lifecycle & State Machine

**Responsibilities:**
- Order creation from cart (snapshot all items at confirmation prices)
- Order number generation (human-readable sequence)
- State machine: Pending Confirmation → In Preparation → Served → Settled | Canceled
- Optimistic version control on all state transitions
- Kitchen lock: items locked_at set when moving to In Preparation
- Priority Matrix: locked items cannot be deleted by Guest/Cashier
- Zombie state handling: order change during checkout → invalidate payment intent → redirect
- `OrderTracker` Livewire component (guest-facing, `wire:poll.5s`)
- `OrderList` Livewire component (staff-facing, filterable by status)
- `ConflictBanner` shared component for version conflict display
- `StepUpModal` for Manager PIN verification
- Privilege escalation logging (immutable)
- Order status log (immutable)
- Real-time UI feedback on conflicts

**Inputs:**
- Cart ID (to create order)
- Status transition request (new status, version, optional manager PIN, optional cancel reason)

**Outputs:**
- Order with status, version, itemized content, timestamps, actor IDs
- Version conflict response (409) with current state
- Step-up challenge (403) when Manager PIN required

**Required Flows:**
1. Guest submits cart → Order created (Pending Confirmation) → `OrderTracker` shows status
2. Cashier confirms → status = In Preparation, version incremented, items locked_at set
3. Kitchen marks served → status = Served, version incremented
4. Cashier settles (after payment) → status = Settled, version incremented
5. Guest cancels Pending → status = Canceled (no PIN needed)
6. Manager cancels In Preparation → step-up PIN modal → correct PIN → Canceled + privilege_escalation_log entry
7. Two staff try to change same order → first succeeds, second gets 409 conflict → refresh UI → retry
8. Order canceled while guest in checkout → zombie detection → payment intent invalidated → guest sees "Your order was updated" screen

**Failure Behavior:**
- Stale version → 409 with current version and status
- Invalid transition (e.g., Settled → In Preparation) → 422
- Wrong role (Kitchen tries to settle) → 403
- Cancel In Preparation without PIN → 403 "Manager authorization required"
- Wrong Manager PIN → 403 + rule_hit_log entry
- Zombie state → payment intent canceled, guest redirected

**Permissions:**
- Guest: view own order, cancel if Pending
- Cashier: confirm, mark served, settle
- Kitchen: mark in preparation
- Manager: all of above + cancel after In Preparation (with PIN) + override
- Admin: all

**Tests that should exist when done:**

*Unit:*
- `OrderStateMachine` — every valid transition, every invalid transition, version conflict, role enforcement, kitchen lock, terminal state
- `OrderStatus` enum — transitions() method returns correct next-states

*Feature:*
- `OrderLifecycleTest` — full happy path with actor attribution
- `VersionConflictTest` — concurrent modification detected
- `KitchenLockTest` — locked items cannot be removed by cashier
- `ZombieStateTest` — order canceled during checkout flow
- `StepUpVerificationTest` — PIN required/correct/incorrect/logged

*E2E:*
- `staff-order-flow.spec.ts` — Cashier confirms → Kitchen prepares → Cashier settles
- `manager-overrides.spec.ts` — Manager cancels with PIN, wrong PIN shows error
- `error-paths.spec.ts` — version conflict banner shown

*Docker:*
- Order lifecycle works end-to-end
- `wire:poll` updates order status on guest tablet

---

### Module E: Promotion Engine

**Responsibilities:**
- Promotion CRUD (admin)
- Four promo types: percentage off over threshold, flat discount over threshold, BOGO for specific SKUs, percentage off second item
- Resolution Tree evaluation:
  1. Calculate all item-level discounts (BOGO, % off second) first
  2. Calculate all cart-level discounts (% off total, flat off total) second
  3. Evaluate all valid combinations respecting mutual exclusion groups
  4. Select combination yielding lowest total for the guest ("best offer wins")
- Mutual exclusion enforcement via `exclusion_group`
- Time-windowed promotions (starts_at / ends_at in local time)
- Applied promotion record with traceable discount breakdown
- Integration with CartSummary for discount preview
- Integration with CheckoutFlow for final application

**Inputs:**
- Cart contents (items, quantities, prices)
- Active promotions (filtered by time window + active flag)

**Outputs:**
- Best promotion combination with: promo name, type, discount amount, description
- Itemized discount breakdown on cart/order

**Required Flows:**
1. Guest has cart > $30 + active "10% off over $30" promo → discount shown in cart summary
2. Guest has BOGO-eligible SKUs → BOGO discount calculated (free item = cheapest of pair)
3. Both BOGO and "10% off" are eligible but mutually exclusive → system picks the one saving more
4. Promo time window: 5:00 PM – 8:00 PM → applied at 6:00 PM, rejected at 9:00 PM
5. Admin creates/edits/deactivates promotions
6. Checkout applies the winning promotion → `applied_promotions` record created

**Failure Behavior:**
- No eligible promos → no discount, no error
- Cart below all thresholds → no discount
- All eligible promos expired → no discount
- Admin sets overlapping exclusion groups → system handles (only one winner per group)
- Promo targets SKU not in cart → promo skipped

**Permissions:**
- Guest: read-only (sees applied discount)
- Admin: CRUD promotions

**Tests that should exist when done:**

*Unit:*
- `PromotionEvaluator` — extensive scenarios:
  - Single promo applied
  - Best of multiple selected (highest dollar savings)
  - Mutual exclusion within same group
  - Item-level before cart-level (Resolution Tree order)
  - Time window: active, expired, not yet started
  - BOGO: correct SKU matching, odd quantity (3 items: 1 free), cheapest item free
  - % off second item
  - Threshold not met
  - Empty cart → no promo
  - All four promo types in single evaluation

*Feature:*
- `PromotionApplicationTest` — promotion applied at checkout, discount amount correct
- `MutualExclusionTest` — exclusive promos cannot stack
- `TimeWindowTest` — promo not applied outside window
- `PromotionManagementTest` — admin CRUD, validation

*E2E:*
- `promotion-display.spec.ts` — discount shown in cart, correct amount, promo name visible, time window displayed in local time

*Docker:*
- Promo calculation works with seeded data

---

### Module F: Payments & Reconciliation

**Responsibilities:**
- Payment intent creation with HMAC signature (nonce + 5-minute expiry)
- Time-sync heartbeat between tablets and server (prevent clock-drift rejections)
- Payment confirmation by staff (method selection: cash, card_manual)
- HMAC verification on confirmation
- Nonce uniqueness enforcement (replay prevention)
- Idempotent processing (duplicate reference returns existing confirmation)
- Notes encrypted at rest (Laravel `encrypted` cast)
- Order transition to Settled on successful payment
- Reconciliation: "paid but not settled" detection via scheduled command
- Incident ticket creation with manager-reviewed queue
- Manager settlement with Reason Code + Receipt Reference
- Step-up verification for settling ambiguous payments

**Inputs:**
- Order ID (to create payment intent)
- Payment confirmation: reference, HMAC, nonce, method, notes
- Reconciliation: action, reason code, receipt reference, manager PIN

**Outputs:**
- Payment intent with reference UUID, HMAC, nonce, expiry
- Payment confirmation with order status update
- Incident tickets for manager review

**Required Flows:**
1. Checkout → create payment intent → HMAC signed → sent to staff terminal
2. Staff confirms payment (selects method) → HMAC verified → nonce checked → payment recorded → order settled
3. Same confirmation submitted twice → idempotent: returns existing confirmation
4. Tablet clock drifted > 30s → time-sync heartbeat detects → warning banner → payment blocked until sync
5. Payment intent expires (5 min) → staff tries to confirm → 410 "Intent expired"
6. Order canceled while payment pending → intent invalidated
7. Reconciliation command detects "confirmed payment but order not settled" → creates incident ticket
8. Manager reviews ticket → provides reason code + receipt → settles or cancels → ticket closed

**Failure Behavior:**
- Tampered HMAC → 400 "Signature verification failed" + rule_hit_log entry
- Replayed nonce → 409 (idempotent return if same reference, else reject)
- Expired intent → 410 "Payment intent expired"
- Clock drift > 30s → client-side block (not a server error)
- Missing reason code on reconciliation → 422
- Wrong manager PIN on reconciliation → 403 + privilege_escalation_log

**Permissions:**
- Guest: triggers intent creation via checkout
- Cashier: confirms payment
- Manager: reconciliation queue, settle ambiguous
- Admin: view all payment records

**Tests that should exist when done:**

*Unit:*
- `HmacSigner` — sign/verify, tampered params fail, expired rejected, replayed nonce rejected, different keys different output

*Feature:*
- `PaymentIntentTest` — intent created, HMAC present, expires_at set
- `HmacVerificationTest` — valid → success, tampered → 400, expired → 410
- `IdempotencyTest` — same reference twice → same confirmation
- `ReconciliationTest` — stuck orders detected, tickets created, manager settles with reason code

*E2E:*
- `payment-flow.spec.ts` — full checkout → payment → settled; expired intent error; double-click idempotency

*Docker:*
- Payment flow works end-to-end
- Reconciliation command runs via scheduler

---

### Module G: Risk Control & Anti-Fraud

**Responsibilities:**
- Device fingerprint generation (UA + screen traits + salted SHA-256)
- Fingerprint synced across tablets via server (same hash → same record)
- Per-device rate limiting (configurable: max 10 regs/hr, max 30 checkouts/10 min)
- Per-IP rate limiting
- Offline CAPTCHA (Gregwar/Captcha — local image/math-based)
- CAPTCHA triggers: 5 failed logins, 3 rapid cart re-pricings in 60 seconds
- Blacklists: device IDs, IP ranges (CIDR), usernames
- Whitelists: device IDs, IP ranges, usernames (bypass rate limits)
- Immutable rule_hit_logs (append-only, PG trigger prevents modification)
- Immutable privilege_escalation_logs (from Module D, verified here)
- Admin: SecurityRulesManager (blacklist/whitelist CRUD)
- Admin: SecurityAuditLog (filterable view of all rule hits + escalations)

**Inputs:**
- HTTP request (device fingerprint, IP, session)
- Login attempts (count per device/IP)
- Cart re-pricing events (count per device, 60s window)
- Admin: blacklist/whitelist entries

**Outputs:**
- Request allowed/blocked decisions
- CAPTCHA challenges
- Rule hit log entries
- Security audit dashboard data

**Required Flows:**
1. New device visits → fingerprint generated → stored server-side → used for all subsequent tracking
2. Same device on different tablet → same fingerprint hash → same record (cross-tablet sync)
3. Device exceeds rate limit → 429 + rule_hit_log entry
4. 5 failed logins → CAPTCHA challenge on next attempt → correct answer → login proceeds → counter resets
5. 3 rapid cart re-pricings in 60s → CAPTCHA required → correct answer → continues
6. Admin blacklists a device ID → device gets 403 on all requests
7. Admin blacklists IP range (CIDR) → all IPs in range blocked
8. Admin whitelists a device → device bypasses rate limits
9. Admin views audit log → filterable by type, date, device

**Failure Behavior:**
- Missing fingerprint data (no JS) → generate degraded fingerprint from UA + IP only
- CAPTCHA answer wrong → 422, regenerate challenge
- Invalid CIDR notation in blacklist → 422 validation error
- Blacklisted + whitelisted (conflict) → whitelist takes precedence

**Permissions:**
- Guest: subject to all checks
- Staff: subject to auth checks, not device fingerprinting
- Admin: manage all security rules, view audit logs

**Tests that should exist when done:**

*Unit:*
- `DeviceFingerprintGenerator` — consistent hash, salt sensitivity, missing fields
- `RateLimitEvaluator` — threshold, window, reset
- `CaptchaTriggerEvaluator` — 5 logins, 3 re-pricings, timing edge, counter reset

*Feature:*
- `RateLimitingTest` — per-device, per-IP, configurable thresholds
- `DeviceFingerprintTest` — creation, update, cross-request consistency
- `BlacklistTest` — device blacklist, IP CIDR blacklist, username blacklist
- `CaptchaTriggerTest` — login trigger, re-pricing trigger, correct answer clears
- `SecurityRulesTest` — admin CRUD on blacklist/whitelist

*E2E:*
- `security-enforcement.spec.ts` — failed logins trigger CAPTCHA, CAPTCHA image renders, blacklisted device blocked

*Docker:*
- Rate limiting works with real cache/DB
- Gregwar/Captcha generates images in container

---

### Module H: Analytics & Observability

**Responsibilities:**
- Event tracking: page_view, add_to_cart, checkout_started, order_placed, order_settled
- Each event includes trace_id, session_id, device_fingerprint_id, payload
- `analytics_events` table with monthly PostgreSQL partitioning
- Materialized view `mv_daily_analytics` for DAU, GMV, conversion, funnel
- Retention computation (returning device fingerprints)
- Daily aggregation command (refreshes materialized view)
- Monthly partition creation command (run on 25th of each month)
- Admin dashboard with Chart.js (bundled locally, no CDN)
- Threshold-based alert system:
  - Error rate > 5% last hour → CRITICAL
  - Risk rule hits > 50 last hour → WARNING
  - GMV drop > 50% day-over-day → WARNING
  - Failed logins > 100 last hour → CRITICAL
- Alert display on admin dashboard as banners
- Encryption key rotation command (`RotateEncryptionKeyCommand`)
- Admin: Dashboard Livewire component with date range picker, charts

**Inputs:**
- Analytics events from all modules (via event dispatch)
- Date range for dashboard queries
- Alert thresholds from `config/harborbite.php`

**Outputs:**
- Dashboard: DAU, GMV, conversion rate, funnel chart, retention table
- Alert banners on admin dashboard
- Refreshed materialized view

**Required Flows:**
1. Guest browses menu → page_view event tracked
2. Guest adds to cart → add_to_cart event tracked
3. Guest checks out → checkout_started event tracked
4. Order settled → order_settled event tracked with total
5. Admin opens dashboard → materialized view queried → charts render
6. Admin filters by date range → data updates
7. Scheduled command refreshes materialized view daily
8. Scheduled command checks alert thresholds every 5 min → creates AdminAlert if breached
9. Admin sees alert banner → acknowledges → banner dismissed

**Failure Behavior:**
- Event tracking fails → log error, do not interrupt user flow (fire-and-forget)
- Materialized view refresh fails → log error, serve stale data with timestamp
- Missing partition → auto-create, log warning
- Alert threshold config invalid → log error, skip check

**Permissions:**
- Admin only: dashboard, alert acknowledgment
- Key rotation: admin CLI only (`php artisan harborbite:rotate-key`)

**Tests that should exist when done:**

*Unit:*
- Analytics aggregation logic (DAU, GMV, conversion from sample data)

*Feature:*
- `EventTrackingTest` — events written with correct type, trace_id, payload
- `AggregationTest` — materialized view returns correct counts
- Alert threshold detection from seeded data

*E2E:*
- `admin-dashboard.spec.ts` — dashboard loads, charts render, date picker works, alerts visible

*Docker:*
- Materialized view created by migration
- Scheduled commands run via supervisord
- Chart.js renders without CDN

---

## 14. Module Implementation Order & Dependency Graph

```
MODULE A: Foundation & Infrastructure
    │
    ▼
MODULE B: Menu Discovery & Search
    │
    ▼
MODULE C: Cart Management
    │
    ▼
MODULE D: Order Lifecycle & State Machine
    │
    ▼
MODULE E: Promotion Engine
    │
    ▼
MODULE F: Payments & Reconciliation
    │
    ▼
MODULE G: Risk Control & Anti-Fraud
    │
    ▼
MODULE H: Analytics & Observability
    │
    ▼
FINAL: Full E2E Test Suite + Docker Verification + run_tests.sh
```

### Why This Order

1. **A first**: Everything depends on Docker, auth, middleware, logging, DB.
2. **B before C**: Cart needs menu items to exist.
3. **C before D**: Orders are created from carts.
4. **D before E**: Promotions are applied to orders at checkout. The checkout flow needs the order state machine.
5. **E before F**: Payment intents are created after promotion is applied (amount includes discount).
6. **F before G**: Risk controls wrap all the above flows. Rate limiting and CAPTCHA are middleware-level concerns that are best verified once the flows they protect exist.
7. **G before H**: Analytics events are fired from all previous modules. Audit logs feed into analytics dashboards.
8. **Final**: Playwright E2E tests run the complete stack.

### Module Completion Criteria (applies to ALL modules)

A module is **complete** only when:

- [ ] All planned requirements implemented
- [ ] Main flows work correctly
- [ ] Important error cases handled (from failure paths section)
- [ ] Inputs validated (Form Requests for HTTP, domain validators for business rules)
- [ ] Failure paths not silently ignored
- [ ] README.md updated if module affects setup/startup/flow
- [ ] Auth and authorization checks applied
- [ ] No secrets or PII leakage
- [ ] Unit tests pass
- [ ] Feature/integration tests pass
- [ ] `run_tests.sh` passes
- [ ] `docker compose up --build` works
- [ ] Docker verification passed (if module affects startup, services, ports, persistence, config)
- [ ] Visual audit passed (if module has UI: hierarchy, spacing, interaction states, loading, no CLS)

---

## 15. README & Operational Documentation

### README.md Structure

```markdown
# HarborBite — Offline Restaurant Ordering & Risk Management System

## Overview
[2-3 sentences describing the system]

## Prerequisites
- Docker Desktop (or Docker Engine + Docker Compose v2)
- No other dependencies required

## Quick Start
docker compose up --build
# App available at http://localhost:8080

### Default Credentials
| Role | Username | Password |
|---|---|---|
| Administrator | admin | [seeded password] |
| Manager | manager | [seeded password] |
| Cashier | cashier | [seeded password] |
| Kitchen | kitchen | [seeded password] |

## Testing
./run_tests.sh
# Runs: Unit → Integration → E2E (Playwright)

## Architecture
[Brief description of hexagonal architecture, module layout]

### Module Map
- Domain/     — Pure business logic (no framework imports)
- Application/ — Use case orchestration
- Infrastructure/ — Laravel implementations (Eloquent, middleware)
- Livewire/   — UI components
- Http/       — Controllers, middleware, form requests

## API Endpoints
[Table of all endpoints with methods, paths, auth requirements, and brief descriptions]

## Configuration
All configurable thresholds in config/harborbite.php:
- Rate limiting thresholds
- CAPTCHA triggers
- HMAC expiry window
- Tax rates
- Alert thresholds
- Maximum trending terms

## Services
| Service | Internal Port | External Port |
|---|---|---|
| Nginx (web) | 80 | 8080 |
| PHP-FPM (app) | 9000 | — |
| PostgreSQL | 5432 | — |

## Scheduled Tasks
| Command | Interval | Purpose |
|---|---|---|
| ReconcilePayments | Every 5 min | Detect stuck payment states |
| AggregateAnalytics | Daily at 2 AM | Refresh materialized views |
| CreateMonthlyPartition | 25th of month | Prepare next month's partition |
| CheckAlertThresholds | Every 5 min | Detect metric anomalies |

## Logs
- Application logs: Docker volume `harborbite-logs`
- Format: Structured JSON with trace IDs
- Rotation: 30-day retention

## Key Rotation
php artisan harborbite:rotate-key
# Re-encrypts all sensitive fields with new APP_KEY
```

### When to Update README

- Module A: initial README with quick start, prerequisites, architecture
- Module B–H: update API endpoints table as each module adds endpoints
- Module F: add scheduled tasks section
- Module H: add logs section, key rotation section
- Final: full review for accuracy

---

## 16. Compliance Verification — Original Prompt Traceability

### Iteration 1: Feature-by-Feature Check

| # | Original Requirement | Module | Status |
|---|---|---|---|
| 1 | On-premise menu discovery | B | Covered: MenuBrowser, full-text search, JSONB attributes |
| 2 | Keyword queries | B | Covered: PostgreSQL tsvector + ts_rank |
| 3 | Price-range filtering | B | Covered: WHERE price BETWEEN :min AND :max |
| 4 | Category attributes (gluten-free, spicy level, contains nuts) | B | Covered: JSONB attributes with GIN index |
| 5 | Sorting by relevance, newest, or price | B | Covered: ts_rank, created_at, price sort |
| 6 | Recent searches stored locally | B | Covered: session-based storage |
| 7 | Admins pin up to 20 trending terms per location | B | Covered: trending_searches table, max 20 enforced |
| 8 | Blocked sensitive/abnormal terms with inline message | B | Covered: ProfanityFilter + BannedWord + inline 422 |
| 9 | Suggestion to refine query | B | Covered: trending terms shown as suggestions |
| 10 | Add/remove items, edit quantities | C | Covered: CartManager Livewire |
| 11 | Selecting flavor preferences | C | Covered: CartItem.flavor_preference |
| 12 | Short notes capped at 140 chars | C | Covered: domain + validation + DB CHECK |
| 13 | Itemized subtotal | C | Covered: CartSummary |
| 14 | Estimated sales tax | C | Covered: TaxCalculator with Tax-Rule Table |
| 15 | Traceable discount breakdown | E | Covered: AppliedPromotion with description |
| 16 | Promotions applied automatically at checkout | E | Covered: PromotionEvaluator in CheckoutFlow |
| 17 | "Best offer wins" logic | E | Covered: Resolution Tree — highest dollar savings |
| 18 | 10% off orders over $30.00 | E | Covered: percentage_off promo type |
| 19 | $5.00 off over $50.00 | E | Covered: flat_discount promo type |
| 20 | BOGO for specific SKUs | E | Covered: bogo promo type |
| 21 | 50% off second item | E | Covered: percentage_off_second promo type |
| 22 | Mutual exclusions (BOGO cannot stack with %) | E | Covered: exclusion_group field |
| 23 | Time windows in local time | E | Covered: starts_at/ends_at, local timezone eval |
| 24 | Staff roles: Cashier, Kitchen, Manager, Administrator | A | Covered: UserRole enum + RoleMiddleware |
| 25 | Order lifecycle: 5 statuses | D | Covered: OrderStatus enum + OrderStateMachine |
| 26 | Real-time status visibility | D | Covered: OrderTracker with wire:poll.5s |
| 27 | Immediate UI feedback on conflicts | D | Covered: ConflictBanner + version check |
| 28 | Prevent double edits | D | Covered: Optimistic version control |
| 29 | Laravel REST-style endpoints consumed by Livewire | A,B,C,D,E,F | Covered: API controllers + Livewire integration |
| 30 | PostgreSQL persisting all data locally | A | Covered: single PG instance, all tables |
| 31 | Strict state machine | D | Covered: OrderStateMachine with transition rules |
| 32 | Optimistic version control (last-known version) | D | Covered: version column, StaleVersionException |
| 33 | Stale request rejected, requiring refresh | D | Covered: 409 response + ConflictBanner |
| 34 | Per-device rate limiting (10 regs/hr) | G | Covered: RateLimitMiddleware, configurable |
| 35 | Per-IP rate limiting (30 checkouts/10 min) | G | Covered: RateLimitMiddleware, configurable |
| 36 | CAPTCHA after 5 failed logins | G | Covered: CaptchaTriggerEvaluator + Gregwar/Captcha |
| 37 | CAPTCHA after 3 rapid cart re-pricings in 60s | G | Covered: CaptchaTriggerEvaluator |
| 38 | Manager PIN for cancel after In Preparation | D | Covered: StepUpModal + StepUpVerifier |
| 39 | Manager PIN for override discount > $20.00 | E,D | Covered: step-up before applying override |
| 40 | Manager PIN for settle when payment ambiguous | F | Covered: ReconciliationQueue + step-up |
| 41 | Device fingerprinting (UA + screen + salted hash) | G | Covered: DeviceFingerprintGenerator + middleware |
| 42 | Blacklists/whitelists (device IDs, IP ranges, usernames) | G | Covered: SecurityBlacklist/Whitelist + CIDR |
| 43 | Every rule hit writes immutable log | G | Covered: rule_hit_logs + PG trigger |
| 44 | Payment intents recorded | F | Covered: PaymentIntent model |
| 45 | Locally entered payment confirmations | F | Covered: PaymentConfirmation Livewire |
| 46 | HMAC signing (nonce + 5-min expiry) | F | Covered: HmacSigner |
| 47 | Idempotent processing via unique reference | F | Covered: UUID reference, duplicate detection |
| 48 | "Paid but not settled" reconciliation queue | F | Covered: ReconcilePaymentsCommand + IncidentTicket |
| 49 | Incident tickets and risk alerts | F | Covered: IncidentTicket model |
| 50 | DAU, GMV, conversion, funnel, retention | H | Covered: AnalyticsAggregator + materialized view |
| 51 | Event tracking in PostgreSQL | H | Covered: analytics_events table |
| 52 | API performance, error rates, trace IDs | H | Covered: TraceIdMiddleware + RequestLoggingMiddleware |
| 53 | Rotating local logs | H | Covered: daily channel, 30-day retention |
| 54 | Threshold-based alerts on admin dashboard | H | Covered: CheckAlertThresholdsCommand + AdminAlert |
| 55 | Passwords/PINs hashed | A | Covered: bcrypt via Hash facade |
| 56 | Customer notes encrypted at rest | C,F | Covered: Laravel encrypted cast |
| 57 | Key rotation stored only on-premise | H | Covered: RotateEncryptionKeyCommand |

**Result: All 57 traceable requirements from the original prompt are covered.**

### Iteration 2: Supplemental Prompt Additions Check

| # | Supplemental Addition | Module | Status |
|---|---|---|---|
| S1 | Alpine.js for immediate UI feedback/counters | A (layout) | Covered: bundled with Livewire, used for cart badge, toggles |
| S2 | Tailwind CSS | A (layout) | Covered: @theme design tokens, 8px grid |
| S3 | Materialized views for analytics performance | H | Covered: mv_daily_analytics |
| S4 | Priority Matrix (Kitchen lock) | D | Covered: locked_at on order_items |
| S5 | Zombie state handling | D | Covered: ZombieRedirect + payment intent invalidation |
| S6 | Allergen negative filters | B | Covered: AllergenFilter exclusion logic |
| S7 | Resolution Tree for promotions | E | Covered: item-level first, cart-level second, best combo |
| S8 | Tax-Rule Table (hot/cold variances) | C | Covered: tax_rules table + TaxCalculator |
| S9 | Gregwar/Captcha (offline) | G | Covered: GregwarCaptchaService |
| S10 | Privilege Escalation Log | D,G | Covered: privilege_escalation_logs (immutable) |
| S11 | Time-Sync Heartbeat | F | Covered: TimeSyncController + client-side drift detection |
| S12 | Reason Code + Receipt ID for reconciliation | F | Covered: resolution_reason_code, receipt_reference (encrypted) |
| S13 | Cross-tablet fingerprint sync | G | Covered: server-side hash lookup |
| S14 | RBAC for overlapping duties | A | Covered: multi-role middleware support |

**Result: All 14 supplemental additions are covered.**

### Final Integrity Check

- **No features softened**: HMAC is fully specified with nonce + expiry + replay prevention. Encryption at rest with key rotation. Immutable audit logs with PG triggers.
- **No features removed**: All 4 promo types, all 5 order statuses, all 4 staff roles, all search capabilities, all security controls present.
- **No half-implementations**: Each module is defined as a complete vertical slice with specific tests and completion criteria.
- **Docker-first**: `docker compose up --build` is the canonical start command. `run_tests.sh` is the canonical test entry point. No hidden setup steps.
- **Testing coverage**: 90%+ meaningful coverage target with unit + integration + E2E. TDD for domain logic. Failure paths explicitly tested.
- **UI audit compliance**: Tailwind theme tokens (no magic numbers), 8px grid, interaction states defined, loading states, no CLS, touch-friendly 44px targets.

---

*This development plan is the authoritative reference for implementation. Each module's planned requirements serve as the benchmark for module completion. Implementation should proceed sequentially, with each module fully completed and verified before starting the next.*
