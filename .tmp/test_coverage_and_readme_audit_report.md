# Test Coverage Audit

Static inspection only. No tests, code, containers, or scripts were executed.

## Backend Endpoint Inventory

Route source: `src/routes/api.php` with framework API registration from `src/bootstrap/app.php:8-11`.

Normalized endpoint set (`METHOD + PATH`):

1. `GET /api/time-sync`
2. `GET /api/menu/search`
3. `GET /api/menu/categories`
4. `GET /api/menu/:id`
5. `GET /api/cart`
6. `POST /api/cart/items`
7. `PATCH /api/cart/items/:cartItemId`
8. `DELETE /api/cart/items/:cartItemId`
9. `DELETE /api/cart`
10. `POST /api/orders`
11. `GET /api/orders/:trackingToken`
12. `GET /api/orders/:orderId/detail`
13. `POST /api/orders/:orderId/transition`
14. `POST /api/orders/:orderId/discount`
15. `POST /api/payments/intent`
16. `POST /api/payments/confirm`

## API Test Mapping Table

| Endpoint | Covered | Test Type | Test Files | Evidence |
|---|---|---|---|---|
| `GET /api/time-sync` | yes | true no-mock HTTP | `src/tests/Feature/Api/OrderApiTest.php`, `src/tests/Feature/Middleware/AnalyticsTrackingMiddlewareTest.php` | `GET /api/time-sync returns server time...` (`src/tests/Feature/Api/OrderApiTest.php:59`), `getJson('/api/time-sync')` (`src/tests/Feature/Middleware/AnalyticsTrackingMiddlewareTest.php:26`) |
| `GET /api/menu/search` | yes | true no-mock HTTP | `src/tests/Feature/Api/MenuApiTest.php` | `GET /api/menu/search returns menu items...` (`src/tests/Feature/Api/MenuApiTest.php:16`) |
| `GET /api/menu/categories` | yes | true no-mock HTTP | `src/tests/Feature/Api/MenuApiTest.php` | `GET /api/menu/categories returns...` (`src/tests/Feature/Api/MenuApiTest.php:43`) |
| `GET /api/menu/:id` | yes | true no-mock HTTP | `src/tests/Feature/Api/MenuApiTest.php` | `GET /api/menu/{id} returns...` (`src/tests/Feature/Api/MenuApiTest.php:55`) |
| `GET /api/cart` | yes | true no-mock HTTP | `src/tests/Feature/Api/CartApiTest.php`, `src/tests/Feature/Api/CartOwnershipTest.php` | `GET /api/cart returns empty cart initially` (`src/tests/Feature/Api/CartApiTest.php:34`) |
| `POST /api/cart/items` | yes | true no-mock HTTP | `src/tests/Feature/Api/CartApiTest.php` | `POST /api/cart/items adds item to cart` (`src/tests/Feature/Api/CartApiTest.php:15`) |
| `PATCH /api/cart/items/:cartItemId` | yes | true no-mock HTTP | `src/tests/Feature/Api/CartApiTest.php`, `src/tests/Feature/Api/CartCrossItemOwnershipTest.php` | `PATCH /api/cart/items/{id} updates item note...` (`src/tests/Feature/Api/CartApiTest.php:39`) |
| `DELETE /api/cart/items/:cartItemId` | yes | true no-mock HTTP | `src/tests/Feature/Api/CartApiTest.php`, `src/tests/Feature/Api/CartCrossItemOwnershipTest.php` | `DELETE /api/cart/items/{id} removes item` (`src/tests/Feature/Api/CartApiTest.php:57`) |
| `DELETE /api/cart` | yes | true no-mock HTTP | `src/tests/Feature/Api/CartApiTest.php` | `DELETE /api/cart clears the cart` (`src/tests/Feature/Api/CartApiTest.php:66`) |
| `POST /api/orders` | yes | true no-mock HTTP | `src/tests/Feature/Api/OrderApiTest.php`, `src/tests/Feature/Api/CartOwnershipTest.php` | `POST /api/orders creates order...` (`src/tests/Feature/Api/OrderApiTest.php:22`) |
| `GET /api/orders/:trackingToken` | yes | true no-mock HTTP | `src/tests/Feature/Api/OrderApiTest.php`, `src/tests/Feature/Order/OrderTrackingDataMinimizationTest.php` | `GET /api/orders/{token} returns order...` (`src/tests/Feature/Api/OrderApiTest.php:40`) |
| `GET /api/orders/:orderId/detail` | yes | true no-mock HTTP | `src/tests/Feature/Order/OrderTrackingDataMinimizationTest.php` | `staff detail endpoint returns...` (`src/tests/Feature/Order/OrderTrackingDataMinimizationTest.php:106`) |
| `POST /api/orders/:orderId/transition` | yes | true no-mock HTTP | `src/tests/Feature/Api/StaffApiAuthTest.php`, `src/tests/Feature/Api/ErrorContractTest.php` | `POST /api/orders/{id}/transition works...` (`src/tests/Feature/Api/StaffApiAuthTest.php:57`) |
| `POST /api/orders/:orderId/discount` | yes | true no-mock HTTP | `src/tests/Feature/Api/DiscountOverrideApiTest.php` | `manager can apply small discount...` (`src/tests/Feature/Api/DiscountOverrideApiTest.php:39`) |
| `POST /api/payments/intent` | yes | true no-mock HTTP | `src/tests/Feature/Api/StaffApiAuthTest.php`, `src/tests/Feature/Payment/PaymentAuthAndVersionTest.php` | `POST /api/payments/intent works...` (`src/tests/Feature/Api/StaffApiAuthTest.php:103`) |
| `POST /api/payments/confirm` | yes | true no-mock HTTP | `src/tests/Feature/Api/PaymentMethodValidationTest.php`, `src/tests/Feature/Payment/PaymentAuthAndVersionTest.php` | `payment confirm accepts cash method` (`src/tests/Feature/Api/PaymentMethodValidationTest.php:47`) |

## Coverage Summary

- Total endpoints: `16`
- Endpoints with HTTP tests: `16`
- Endpoints with true no-mock HTTP tests: `16`
- HTTP coverage: `100%` (`16/16`)
- True API coverage: `100%` (`16/16`)

## Unit Test Summary

### Test files

- Unit suite exists under `src/tests/Unit/Domain/*` and includes auth, order, payment, promotion, risk, search, and cart domain tests (e.g., `src/tests/Unit/Domain/Order/OrderStateMachineTest.php`, `src/tests/Unit/Domain/Payment/HmacSignerTest.php`, `src/tests/Unit/Domain/Cart/TaxCalculatorTest.php`).

### Modules covered

- Controllers (via HTTP tests): covered indirectly by API feature tests, e.g. `src/tests/Feature/Api/MenuApiTest.php:16`, `src/tests/Feature/Api/CartApiTest.php:15`, `src/tests/Feature/Api/OrderApiTest.php:22`.
- Services/use-cases: covered by feature tests and mixed integration tests, e.g. `CreateOrderUseCase` in `src/tests/Feature/Api/StaffApiAuthTest.php:32`, `ConfirmPaymentUseCase` in `src/tests/Feature/Payment/PaymentAuthAndVersionTest.php:102`.
- Repositories: covered by `src/tests/Feature/Infrastructure/EloquentMenuRepositoryTest.php:22`.
- Auth/guards/middleware: covered by `src/tests/Feature/Auth/RoleAccessTest.php`, `src/tests/Feature/Auth/ForcePasswordChangeTest.php`, and middleware-focused tests under `src/tests/Feature/Middleware/*`.

### Important modules not tested

- No dedicated controller unit tests (controller behavior is only HTTP-validated).
- No explicit unit tests for infrastructure service adapters under `src/app/Infrastructure/Services` (directory contains `GregwarCaptchaService.php`; no direct reference found in `src/tests` pattern scan).

## Tests Check

### API Test Classification

1. **True No-Mock HTTP**
   - Present across API files: `src/tests/Feature/Api/ApiJsonErrorContractTest.php`, `src/tests/Feature/Api/CartApiTest.php`, `src/tests/Feature/Api/CartCrossItemOwnershipTest.php`, `src/tests/Feature/Api/CartOwnershipTest.php`, `src/tests/Feature/Api/DiscountOverrideApiTest.php`, `src/tests/Feature/Api/ErrorContractTest.php`, `src/tests/Feature/Api/MenuApiTest.php`, `src/tests/Feature/Api/OrderApiTest.php`, `src/tests/Feature/Api/PaymentMethodValidationTest.php`, `src/tests/Feature/Api/StaffApiAuthTest.php`.

2. **HTTP with Mocking**
   - None detected.

3. **Non-HTTP (unit/integration without HTTP)**
   - Present in mixed feature tests and unit tests, e.g. direct use-case execution in `src/tests/Feature/Payment/PaymentAuthAndVersionTest.php:92` and `src/tests/Feature/Order/OrderTrackingDataMinimizationTest.php:27`, plus full unit suite in `src/tests/Unit/Domain/*`.

### Mock Detection Rules

Pattern scan in `src/tests` found no usage of `jest.mock`, `vi.mock`, `sinon.stub`, `Mockery`, `shouldReceive`, `partialMock`, `Http::fake`, `Event::fake`, `Queue::fake`, `Bus::fake`, `Mail::fake`, `Notification::fake`, `Storage::fake`, container `swap/instance` overrides.

- What is mocked: none detected.
- Where: no matching files from static scan.

### API Observability Check

- Strong observability examples:
  - endpoint + request + response contract: `src/tests/Feature/Api/MenuApiTest.php:16-29`
  - endpoint + role/validation/error code checks: `src/tests/Feature/Api/DiscountOverrideApiTest.php:54-64`
  - endpoint + response data minimization assertions: `src/tests/Feature/Order/OrderTrackingDataMinimizationTest.php:26-44`
- Weak observability examples:
  - status-centric checks with limited payload semantics still exist, e.g. `src/tests/Feature/Api/ErrorContractTest.php:50-57`, `src/tests/Feature/Api/CartOwnershipTest.php:57-59`.

### Test Quality & Sufficiency

- Success paths: covered (order creation, cart lifecycle, payment intent/confirm, discount flows).
- Failure paths: covered (401, 403, 404, 409, 422, 429), e.g. `src/tests/Feature/Api/ApiJsonErrorContractTest.php`, `src/tests/Feature/Api/ErrorContractTest.php`, `src/tests/Feature/Middleware/CheckoutRateLimitTest.php:18-45`.
- Edge cases: present (stale versions, cross-item cart ownership, minimization/security DTO checks).
- Validation: present (`src/tests/Feature/Api/PaymentMethodValidationTest.php:34-45`, `src/tests/Feature/Api/ErrorContractTest.php:26-36`).
- Auth/permissions: present (`src/tests/Feature/Api/StaffApiAuthTest.php`, `src/tests/Feature/Auth/RoleAccessTest.php`).
- Integration boundaries: present (DB side effects and middleware logging checks).
- Superficial/autogenerated signal: low overall; however some tests still rely mostly on status codes.

### `run_tests.sh` check

- Docker-based: **OK** (`docker compose exec/run` in `run_tests.sh:10`, `run_tests.sh:30`, `run_tests.sh:35`, `run_tests.sh:51`).
- Local dependency requirement: **Not required** by script path (test commands execute inside containers).

### End-to-End Expectations

- Project is fullstack and includes E2E suite under `src/tests/E2E/*.spec.ts` with Playwright config (`src/tests/E2E/playwright.config.ts:3-23`).
- Full FE↔BE expectation is partially satisfied by presence of broad browser flows plus strong API + unit coverage.

## Test Coverage Score (0-100)

`90/100`

## Score Rationale

- Endpoint coverage is complete (`16/16`) with direct HTTP route hits.
- No static evidence of test-time mocks/stubs in API path execution.
- Depth is strong across auth, validation, concurrency/versioning, and error contracts.
- Minor deductions: mixed HTTP/non-HTTP patterns in some feature files and remaining status-centric assertions in a subset of tests.

## Key Gaps

1. Some API tests still under-assert response semantics beyond status code (example: `src/tests/Feature/Api/CartOwnershipTest.php:57-59`).
2. Non-HTTP use-case tests are mixed into endpoint-focused files, reducing strict separation of API-vs-domain evidence (example: `src/tests/Feature/Payment/PaymentAuthAndVersionTest.php:92-141`).
3. No dedicated direct tests for `src/app/Infrastructure/Services/GregwarCaptchaService.php` detected.

## Confidence & Assumptions

- Confidence: **High** for endpoint inventory and HTTP coverage mapping.
- Assumptions:
  - API scope is only routes in `src/routes/api.php` under `/api` prefix from framework routing config.
  - No runtime behavior was assumed beyond static code evidence.
  - “No mocks detected” is limited to patterns scanned in `src/tests`.

**Test Coverage Verdict: PASS**

# README Audit

## Project Type Detection

- Declared in README top section: `Project Type: Fullstack web application` (`README.md:3`).
- Effective type used for audit: **fullstack**.

## README Location

- Required location exists: `README.md` at repository root.

## High Priority Issues

- None.

## Medium Priority Issues

- None.

## Low Priority Issues

- None material to compliance.

## Hard Gate Failures

Hard-gate evaluation:

- Formatting / readability: **PASS** (structured markdown sections and tables).
- Startup instructions for fullstack include `docker-compose up`: **PASS** (`README.md:16`).
- Access method (URL + port): **PASS** (`README.md:23`).
- Verification method (API curl + UI flow): **PASS** (`README.md:31-35`).
- Environment rule (no runtime install/manual setup): **PASS** per documented instructions (`README.md:71-79` states no runtime installs; manual command uses prebuilt Playwright service and does not call `npm install`/`pip install`/`apt-get`).
- Demo credentials for auth and all roles: **PASS** (`README.md:41-47`).

No hard-gate failures detected.

## Engineering Quality

- Tech stack clarity: strong (`README.md:5`).
- Architecture explanation: strong (`README.md:81-107`).
- Testing instructions: clear and containerized (`README.md:48-79`).
- Security/roles coverage: explicit (`README.md:133-141`, `README.md:41-47`).
- Workflow/presentation quality: high, with quick start, verification, endpoints, services, and operations documented.

## README Verdict (PASS / PARTIAL PASS / FAIL)

**PASS**

**README Final Verdict: PASS**
