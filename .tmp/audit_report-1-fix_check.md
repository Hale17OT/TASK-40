# Issue Recheck (Static-Only)

Reviewed only via static inspection (no runtime execution).

## 1) Reconciliation flow does not repair order state to Settled
- Status: **Fixed (static evidence)**
- What changed:
  - Resolution path now attempts order transition to `settled` via `TransitionOrderUseCase` before ticket closure.
  - Ticket resolution is blocked if no confirmed payment exists.
  - On transition failure, ticket is not resolved.
- Evidence:
  - `src/app/Livewire/Manager/ReconciliationQueue.php:86`
  - `src/app/Livewire/Manager/ReconciliationQueue.php:100`
  - `src/app/Livewire/Manager/ReconciliationQueue.php:109`
  - `src/app/Livewire/Manager/ReconciliationQueue.php:114`
  - `src/tests/Feature/Payment/ReconciliationSettlementTest.php:30`
  - `src/tests/Feature/Payment/ReconciliationSettlementTest.php:78`

## 2) Per-location trending terms not enforced in guest search retrieval
- Status: **Fixed (static evidence)**
- What changed:
  - `SearchQuery` now carries `locationId`.
  - `SearchMenuUseCase` now passes `locationId` to trending retrieval for normal, blocked, and validation-error paths.
  - `MenuBrowser` now sources location context from session/config and passes it into query.
- Evidence:
  - `src/app/Domain/Search/SearchQuery.php:19`
  - `src/app/Application/Search/SearchMenuUseCase.php:26`
  - `src/app/Application/Search/SearchMenuUseCase.php:34`
  - `src/app/Application/Search/SearchMenuUseCase.php:62`
  - `src/app/Application/Search/SearchMenuUseCase.php:91`
  - `src/app/Livewire/Menu/MenuBrowser.php:130`
  - `src/app/Infrastructure/Persistence/Repositories/EloquentMenuRepository.php:137`
  - `src/tests/Feature/Menu/LocationTrendingTest.php:34`

## 3) Replay prevention not explicitly implemented despite `ReplayedNonceException`
- Status: **Fixed (static evidence)**
- What changed:
  - Payment intent now stores nonce usage time (`nonce_used_at`).
  - Confirmation flow now checks `nonce_used_at` and throws `ReplayedNonceException` when already consumed.
- Evidence:
  - `src/database/migrations/0013_00_00_000000_add_nonce_used_at_and_signed_at_to_payment_intents.php:12`
  - `src/app/Application/Payment/ConfirmPaymentUseCase.php:69`
  - `src/app/Application/Payment/ConfirmPaymentUseCase.php:71`
  - `src/app/Application/Payment/ConfirmPaymentUseCase.php:101`
  - `src/tests/Feature/Payment/NonceReplayTest.php:36`

## 4) HMAC verification depended on `created_at` instead of persisted signed timestamp
- Status: **Fixed (static evidence)**
- What changed:
  - Payment intent now persists signer timestamp as `signed_at`.
  - Confirmation now verifies HMAC using `signed_at` (with fallback to `created_at` for older records).
- Evidence:
  - `src/database/migrations/0013_00_00_000000_add_nonce_used_at_and_signed_at_to_payment_intents.php:13`
  - `src/app/Application/Payment/CreatePaymentIntentUseCase.php:56`
  - `src/app/Application/Payment/ConfirmPaymentUseCase.php:83`
  - `src/app/Application/Payment/ConfirmPaymentUseCase.php:84`
  - `src/tests/Feature/Payment/NonceReplayTest.php:95`
  - `src/tests/Feature/Payment/NonceReplayTest.php:105`

## Overall Recheck Verdict
- **All 4 previously reported issues appear fixed by current code and tests (static review).**
- Manual verification required for runtime confirmation in a real environment.
