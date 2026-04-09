# HarborBite API Specification

## 1) Conventions

### Base

- Base path: `/api`
- Transport: HTTP/1.1 JSON
- Default success content type: `application/json`
- Auth model for staff APIs: Laravel session auth (`auth` middleware, stateful API)

### Security Headers / Context

Clients should send screen trait headers (used by fingerprint middleware):

- `X-Screen-Width`
- `X-Screen-Height`
- `X-Screen-Color-Depth`

`X-Trace-ID` is optional inbound; if absent server generates one and returns it in response headers.

### Common Response Shapes

Success is endpoint-specific, but generally:

```json
{
  "data": {}
}
```

Error envelope (domain/business and policy errors):

```json
{
  "message": "Human-readable summary",
  "error_code": "MACHINE_CODE"
}
```

Validation failures (Laravel standard):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field": ["..."]
  }
}
```

### Common HTTP Statuses

- `200` OK
- `201` Created
- `400` Bad request/signature invalid
- `401` Unauthenticated
- `403` Forbidden / step-up required / blacklist block / forced password change
- `404` Not found
- `409` Version conflict / nonce replay
- `410` Expired payment nonce
- `422` Business rule violation or validation failure
- `429` Rate limited

---

## 2) Authentication and Authorization

### Public Endpoints

- `GET /api/time-sync`
- `GET /api/menu/search`
- `GET /api/menu/categories`
- `GET /api/menu/{id}`
- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/{cartItemId}`
- `DELETE /api/cart/items/{cartItemId}`
- `DELETE /api/cart`
- `POST /api/orders`
- `GET /api/orders/{trackingToken}`

### Authenticated Staff Endpoints

- `GET /api/orders/{orderId}/detail` (any authenticated role)
- `POST /api/orders/{orderId}/transition` (any authenticated role; role/state validated in domain)

### Role-Gated Auth Endpoints

- `POST /api/orders/{orderId}/discount` (`manager`,`administrator`)
- `POST /api/payments/intent` (`cashier`,`manager`,`administrator`)
- `POST /api/payments/confirm` (`cashier`,`manager`,`administrator`)

Kitchen role is explicitly excluded from payment routes.

---

## 3) Concurrency, Idempotency, and Session Ownership

### Optimistic Concurrency

Order-mutating endpoints require `expected_version`.

- Stale version response: `409` with `error_code=STALE_VERSION`
- Typically includes `current_version` and (for transitions) `current_status`

### Payment Confirm Idempotency

`POST /api/payments/confirm` is idempotent by intent reference:

- first successful confirm -> `201`, `idempotent=false`
- repeated confirm on already-confirmed intent -> `200`, `idempotent=true`

### Session Ownership

- Cart is server-session bound.
- `POST /api/orders` verifies `cart_id` belongs to current server session.
- Attempt to submit foreign cart -> `403` (`Cart not found or access denied.`)

---

## 4) Endpoint Reference

## 4.1 Time Sync

### `GET /api/time-sync`

Returns server time to detect kiosk clock drift.

Response `200`:

```json
{
  "server_time": 1776000100,
  "server_time_iso": "2026-04-09T20:01:40+00:00",
  "timezone": "America/Chicago"
}
```

---

## 4.2 Menu APIs

### `GET /api/menu/search`

Search/filter/sort paginated active menu items.

Query parameters:

- `keyword` (string, optional)
- `price_min` (number, optional)
- `price_max` (number, optional)
- `category_id` (int, optional)
- `allergen_exclusions[]` (array, optional; e.g. `nuts`, `gluten`, `dairy`, `shellfish`, `vegan`, `spicy`)
- `max_spicy_level` (int 0..3, optional)
- `sort` (enum: `relevance|newest|price_asc|price_desc`, default `relevance`)
- `page` (int, default `1`)
- `per_page` (int, default config `20`)

Behavior notes:

- Applies profanity/banned-word block logic for keyword searches.
- On banned term, result is blocked and includes suggestion from trending terms.

Response `200`:

```json
{
  "data": {
    "items": [],
    "total": 0,
    "trending": [],
    "blocked": false,
    "block_message": null,
    "suggestion": null
  },
  "query": {
    "keyword": "burger",
    "sort": "relevance",
    "page": 1,
    "per_page": 20
  }
}
```

### `GET /api/menu/categories`

Response `200`:

```json
{
  "data": [
    { "id": 1, "name": "Burgers" }
  ]
}
```

### `GET /api/menu/{id}`

Response `200`:

```json
{
  "data": {
    "id": 1,
    "sku": "BRG-001",
    "name": "Classic Cheeseburger",
    "price": 12.99,
    "tax_category": "hot_prepared",
    "attributes": {}
  }
}
```

Not found: `404` with message `Menu item not found.`

---

## 4.3 Cart APIs

### `GET /api/cart`

Returns session cart + totals + tax breakdown + repricing metadata.

Response `200`:

```json
{
  "data": { "id": 10, "session_id": "..." },
  "items": [
    {
      "id": 33,
      "menu_item_id": 1,
      "name": "Classic Burger",
      "sku": "B-001",
      "quantity": 2,
      "unit_price": 12.99,
      "current_price": 12.99,
      "line_total": 25.98,
      "flavor_preference": null,
      "note": "No onions",
      "tax_category": "hot_prepared",
      "is_active": true,
      "price_changed": false
    }
  ],
  "totals": {
    "subtotal": 25.98,
    "tax": 2.14,
    "total": 28.12
  },
  "tax_breakdown": [],
  "price_changes": {}
}
```

### `POST /api/cart/items`

Adds one quantity for a menu item, creating cart if needed.

Body:

```json
{ "menu_item_id": 1 }
```

Responses:

- `201` `{ "message": "Item added to cart." }`
- `404` when menu item inactive/missing
- `422` on validation/cart constraints
- Route has `rate-limit:registration`

### `PATCH /api/cart/items/{cartItemId}`

Partial update of cart item owned by current session cart.

Body fields (optional, any combination):

- `quantity` (int)
- `note` (string)
- `flavor_preference` (string)

Response `200`:

```json
{ "message": "Cart item updated." }
```

If session cart not found -> `404` `Cart not found.`

Ownership note: attempts to mutate foreign cart items are safe no-ops (200) due scoped update by current cart id.

### `DELETE /api/cart/items/{cartItemId}`

Response `200`:

```json
{ "message": "Item removed." }
```

If session cart absent -> `404`.

### `DELETE /api/cart`

Clears all current session cart items.

Response `200`:

```json
{ "message": "Cart cleared." }
```

---

## 4.4 Order APIs

### `POST /api/orders`

Creates order from session-owned cart. Applies promotions atomically.

Body:

```json
{ "cart_id": 123 }
```

Responses:

- `201` order created
- `403` when cart is not owned by current session
- `422` business errors (empty cart, etc.)
- Route has `rate-limit:checkout`

Response `201`:

```json
{
  "data": {
    "id": 500,
    "order_number": "HB-AB12CD34",
    "tracking_token": "64-char-hex",
    "status": "pending_confirmation",
    "version": 1,
    "subtotal": "25.98",
    "tax": "2.14",
    "discount": "0.00",
    "total": "28.12"
  },
  "tracking_url": "http://localhost:8080/order/64-char-hex"
}
```

### `GET /api/orders/{trackingToken}`

Guest-safe tracking endpoint (minimized fields only).

Response `200`:

```json
{
  "data": {
    "order_number": "HB-AB12CD34",
    "status": "served",
    "subtotal": 25.98,
    "tax": 2.14,
    "discount": 0,
    "total": 28.12,
    "created_at": "2026-04-09T19:59:00Z"
  },
  "items": [
    {
      "item_name": "Classic Burger",
      "quantity": 2,
      "unit_price": 12.99,
      "line_total": 25.98
    }
  ],
  "status_log": [
    {
      "status": "served",
      "timestamp": "2026-04-09T20:01:00Z"
    }
  ]
}
```

Not found: `404`.

### `GET /api/orders/{orderId}/detail` (auth)

Staff detail endpoint returning full internal order/item/log records.

Responses:

- `200` full data
- `401` unauthenticated
- `404` order not found

### `POST /api/orders/{orderId}/transition` (auth)

Transitions order state under role/state-machine rules.

Body:

```json
{
  "target_status": "in_preparation",
  "expected_version": 1,
  "manager_pin": "1234",
  "cancel_reason": "optional"
}
```

`target_status` allowed values:

- `pending_confirmation`
- `in_preparation`
- `served`
- `settled`
- `canceled`

Responses:

- `200` with updated order in `data`
- `401` unauthenticated
- `403` role violation / step-up required
- `409` stale version
- `422` invalid transition or business precondition (`PAYMENT_REQUIRED`)
- `404` order not found

### `POST /api/orders/{orderId}/discount` (auth + manager/admin)

Applies manual discount with CAS version checks.

Body:

```json
{
  "amount": 25.0,
  "expected_version": 3,
  "manager_pin": "1234",
  "reason": "Customer complaint"
}
```

Rules:

- `amount > 20.00` requires manager PIN step-up.
- successful updates increment order version.

Responses:

- `200` updated order
- `403` `STEP_UP_REQUIRED` or `STEP_UP_FAILED` when needed
- `409` `STALE_VERSION`
- `422` validation error

---

## 4.5 Payment APIs

### `POST /api/payments/intent` (auth + cashier/manager/admin)

Creates or reuses pending payment intent for payable order states.

Body:

```json
{ "order_id": 500 }
```

Responses:

- `201` created
- `200/201` reused existing pending intent (implementation currently returns 201 path through controller)
- `401` unauthenticated
- `403` forbidden role
- `404` order not found
- `422` invalid state (e.g., canceled/settled)

Response body:

```json
{
  "data": {
    "id": 900,
    "order_id": 500,
    "reference": "uuid",
    "amount": "28.12",
    "hmac_signature": "64hex",
    "nonce": "32-byte-hex",
    "signed_at": 1776000000,
    "expires_at": "2026-04-09T20:05:00Z",
    "status": "pending"
  }
}
```

### `POST /api/payments/confirm` (auth + cashier/manager/admin)

Confirms payment intent, enforces HMAC/nonce checks, and settles order through transition use case.

Body:

```json
{
  "reference": "uuid",
  "hmac_signature": "64hex",
  "nonce": "hex",
  "method": "cash",
  "expected_version": 3,
  "notes": "optional",
  "manager_pin": "optional"
}
```

`method` enum:

- `cash`
- `card_manual`

Responses:

- `201` new confirmation (`idempotent=false`)
- `200` idempotent replay on already-confirmed intent (`idempotent=true`)
- `400` `HMAC_FAILED`
- `401` unauthenticated
- `403` forbidden role or ambiguous step-up requirement/failure
- `404` intent/order not found
- `409` `NONCE_REPLAYED` or `STALE_VERSION`
- `410` `PAYMENT_EXPIRED`
- `422` payment canceled/failed, business rule failures

Response example:

```json
{
  "data": {
    "confirmation_id": 77,
    "order_status": "settled",
    "idempotent": false
  }
}
```

Ambiguous settlement criteria:

- intent amount differs from order total by > 0.01
- OR order status is not `served`

When ambiguous, actor must be manager/admin and provide valid manager PIN; escalation is audit logged.

---

## 5) Middleware/Policy Error Codes

Common `error_code` values returned by APIs:

- `UNAUTHENTICATED` - missing auth
- `FORBIDDEN` - role/policy denial
- `RATE_LIMITED` - request throttled
- `BLACKLISTED` - blocked by security blacklist
- `FORCE_PASSWORD_CHANGE` - user must rotate credentials first
- `STEP_UP_REQUIRED` - manager PIN required
- `STEP_UP_FAILED` - manager PIN incorrect
- `STALE_VERSION` - optimistic concurrency conflict
- `INVALID_TRANSITION` - illegal order state move
- `PAYMENT_REQUIRED` - settle requested without confirmed payment
- `PAYMENT_EXPIRED` - HMAC timestamp expired
- `HMAC_FAILED` - signature mismatch
- `NONCE_REPLAYED` - nonce already consumed

Business errors also use this envelope for not-found/invalid-state conditions.

---

## 6) Non-API Web Routes (Reference)

These are Blade/Livewire pages, not JSON APIs:

- `/`, `/menu`, `/cart`, `/checkout`, `/order/{trackingToken}`
- `/login`, `/logout`, `/password/change`
- `/staff/orders`
- `/admin/dashboard`, `/admin/menu`, `/admin/promotions`, `/admin/users`, `/admin/security`, `/admin/security/audit`, `/admin/alerts`
- `/manager/reconciliation`

---

## 7) Practical Client Notes

- Always send and track `expected_version` for order/payment mutation calls.
- Treat `tracking_token` as the only public order identifier.
- Preserve server session (cookie) for cart/order creation continuity.
- Handle `429` with `Retry-After` backoff.
- For step-up flows, detect `STEP_UP_REQUIRED`, prompt PIN, retry with `manager_pin`.
- Distinguish validation-422 (`errors`) from business-422 (`error_code`).
