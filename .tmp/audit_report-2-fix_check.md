# Re-Verification Report (Current State)

Static-only re-check of the 6 issues you listed.

## Summary
- **Fixed:** 6 / 6
- **Partially fixed:** 0 / 6
- **Unfixed:** 0 / 6

## Detailed Results

### 1) High — Public order tracking API overexposes internal data
- **Status:** **Fixed**
- **Verification:** Public `/api/orders/{trackingToken}` now returns guest-safe fields only (no raw `changed_by`, no raw status-log metadata, no full order row dump).
- **Evidence:** `src/app/Http/Controllers/Api/OrderController.php:50`, `src/app/Http/Controllers/Api/OrderController.php:65`, `src/app/Http/Controllers/Api/OrderController.php:70`
- **Additional hardening:** Full-detail order/status response moved to staff-authenticated endpoint.
- **Evidence:** `src/app/Http/Controllers/Api/OrderController.php:85`, `src/routes/api.php:40`, `src/routes/api.php:42`

### 2) High — Rapid re-pricing CAPTCHA tied to cart reads
- **Status:** **Fixed**
- **Verification:** Repricing events are now conditional on real pricing-impact signals, not every cart read:
  - Item price drift (`priceChanges`)
  - Tax-driven total change snapshot comparison
  - Promotion-driven checkout total change snapshot comparison
- **Evidence:** `src/app/Application/Cart/CartService.php:239`, `src/app/Application/Cart/CartService.php:253`, `src/app/Livewire/Checkout/CheckoutFlow.php:304`, `src/app/Livewire/Checkout/CheckoutFlow.php:311`

### 3) Medium — Payment method not constrained
- **Status:** **Fixed**
- **Verification:** `method` is explicitly validated with allow-list.
- **Evidence:** `src/app/Http/Controllers/Api/PaymentController.php:32`

### 4) Medium — API authz errors can degrade to redirects/HTML
- **Status:** **Fixed**
- **Verification:** Role middleware now checks API path and forces JSON 401/403 for API requests.
- **Evidence:** `src/app/Http/Middleware/RoleMiddleware.php:15`, `src/app/Http/Middleware/RoleMiddleware.php:18`, `src/app/Http/Middleware/RoleMiddleware.php:31`

### 5) Medium — Menu browser mismatched closing tags
- **Status:** **Fixed**
- **Verification:** Sidebar block structure is balanced around spicy-level/sort/clear sections; malformed close sequence from prior report is gone.
- **Evidence:** `src/resources/views/livewire/menu/menu-browser.blade.php:79`, `src/resources/views/livewire/menu/menu-browser.blade.php:92`, `src/resources/views/livewire/menu/menu-browser.blade.php:94`, `src/resources/views/livewire/menu/menu-browser.blade.php:115`

### 6) Low — Weak default credentials without forced rotation
- **Status:** **Fixed**
- **Verification:**
  - README clearly marks seed credentials as non-production and states mandatory rotation.
  - Seeder flags all seeded users with `force_password_change = true`.
  - Migration adds the `force_password_change` column.
  - Middleware enforces password-change flow for flagged users.
- **Evidence:** `README.md:29`, `README.md:31`, `src/database/seeders/RoleSeeder.php:29`, `src/database/migrations/0014_00_00_000000_add_force_password_change_to_users.php:12`, `src/app/Http/Middleware/ForcePasswordChangeMiddleware.php:17`

## Boundary Note
- This verification is static-only; no runtime execution, tests, Docker, or browser runs were performed.
