#!/bin/bash
set -e

echo "=== HarborBite Test Suite ==="
echo ""

# Wait for the app container to finish startup (composer install, migrations, seeding)
echo "Waiting for app container to be ready..."
for i in $(seq 1 120); do
    if docker compose exec -T app test -f vendor/autoload.php 2>/dev/null; then
        # Also wait for "HarborBite Ready" signal (migrations + seeding done)
        if docker compose logs app 2>/dev/null | grep -q "HarborBite Ready"; then
            echo "App container is ready."
            break
        fi
    fi
    if [ "$i" -eq 120 ]; then
        echo "ERROR: App container did not become ready within 120 seconds."
        docker compose logs app
        exit 1
    fi
    sleep 1
done

# Test APP_KEY (valid 32-byte key for encryption in test suite)
TEST_APP_KEY="base64:igwuJltoOFVNDaqhKwFBJpx0jnI8HR6XHxY6taBB9LY="

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
