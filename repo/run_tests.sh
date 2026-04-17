#!/bin/bash
set -e

echo "=== HarborBite Test Suite ==="
echo ""

# Bring the stack up ourselves. The CI harness may have torn down containers
# from a previous step, so we cannot rely on them already running. This is
# idempotent — if containers are already up and healthy, compose is a no-op.
echo "Starting Docker stack (idempotent — no-op if already up)..."
docker compose up -d --build

# Wait for the app container to finish startup:
#   - composer install (123 dev packages on a cold volume mount)
#   - migrations
#   - seeding
#   - vite asset build
# This can legitimately take 5-10 minutes on a cold build in CI. Use a
# generous timeout (20 minutes) and poll every 5 seconds.
echo "Waiting for app container to be ready (up to 20 minutes on cold build)..."
MAX_WAIT=1200   # seconds
INTERVAL=5      # seconds
ELAPSED=0
READY=0
while [ "$ELAPSED" -lt "$MAX_WAIT" ]; do
    if docker compose exec -T app test -f vendor/autoload.php 2>/dev/null; then
        if docker compose logs app 2>/dev/null | grep -q "HarborBite Ready"; then
            echo "App container is ready (after ${ELAPSED}s)."
            READY=1
            break
        fi
    fi
    sleep "$INTERVAL"
    ELAPSED=$((ELAPSED + INTERVAL))
    # Progress heartbeat every 30s
    if [ $((ELAPSED % 30)) -eq 0 ]; then
        echo "  ... still waiting (${ELAPSED}s elapsed)"
    fi
done

if [ "$READY" -ne 1 ]; then
    echo "ERROR: App container did not become ready within ${MAX_WAIT} seconds."
    docker compose logs app
    exit 1
fi

# Test APP_KEY (valid 32-byte key for encryption in test suite)
TEST_APP_KEY="base64:igwuJltoOFVNDaqhKwFBJpx0jnI8HR6XHxY6taBB9LY="

# Pest 4's side-effects-detector probes .env per test to detect I/O side
# effects. We ship without an .env (config comes from env vars + phpunit.xml
# <server> overrides), so the probe emits a per-test warning. Touch an empty
# .env to silence the noise — phpunit.xml overrides still win.
docker compose exec -T app touch .env 2>/dev/null || true

# 1. Unit Tests (inside Docker)
echo "--- Running Unit Tests ---"
docker compose exec -T -e APP_KEY="$TEST_APP_KEY" app php artisan test --testsuite=Unit
echo ""

# 2. Feature/Integration Tests (inside Docker)
echo "--- Running Feature Tests ---"
docker compose exec -T -e APP_KEY="$TEST_APP_KEY" app php artisan test --testsuite=Feature
echo ""

# 3. E2E Tests (Playwright) — ALWAYS inside Docker, no host-side deps
echo "--- Preparing for E2E Tests ---"
# Re-seed database (PHP tests with RefreshDatabase may have wiped it)
docker compose exec -T app php artisan migrate:fresh --force --seed 2>/dev/null
# Clear rate-limit counters to prevent CAPTCHA accumulation
docker compose exec -T app php artisan tinker --execute="DB::table('cache')->truncate();" 2>/dev/null || true
# Whitelist the Docker bridge subnet so Playwright traffic isn't rate-limited
# or CAPTCHA-blocked during the long E2E run. This is test-environment-only:
# production seeding never inserts this entry. 172.16.0.0/12 covers every
# Docker bridge network range (172.17.x, 172.18.x, etc.).
docker compose exec -T app php artisan tinker --execute="DB::table('security_whitelists')->insert(['type' => 'ip', 'value' => '172.16.0.0/12', 'reason' => 'E2E test traffic', 'created_at' => now(), 'updated_at' => now()]);" 2>/dev/null || true
# Ensure storage permissions
docker compose exec -T app chmod -R 777 storage bootstrap/cache 2>/dev/null || true

echo "--- Running E2E Tests (Playwright in Docker) ---"
# Run the Docker playwright service (defined under the 'testing' profile in
# docker-compose.yml). The image is pre-built with deps + browsers — NO
# runtime npm install, NO runtime browser install, NO host-side deps.
docker compose --profile testing run --rm -T \
    -e BASE_URL=http://nginx:80 \
    playwright \
    npx playwright test --reporter=list

echo ""
echo "=== All Tests Passed ==="
