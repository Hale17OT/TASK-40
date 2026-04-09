#!/bin/bash
set -e

echo "=== HarborBite Test Suite ==="
echo ""

# Test APP_KEY (valid 32-byte key for encryption in test suite)
TEST_APP_KEY="base64:igwuJltoOFVNDaqhKwFBJpx0jnI8HR6XHxY6taBB9LY="

# 1. Unit Tests
echo "--- Running Unit Tests ---"
docker compose exec -T -e APP_KEY="$TEST_APP_KEY" app php artisan test --testsuite=Unit
echo ""

# 2. Feature/Integration Tests
echo "--- Running Feature Tests ---"
docker compose exec -T -e APP_KEY="$TEST_APP_KEY" app php artisan test --testsuite=Feature
echo ""

# 3. E2E Tests (Playwright)
echo "--- Preparing for E2E Tests ---"
# Re-seed database (PHP tests with RefreshDatabase may have wiped it)
docker compose exec -T app php artisan migrate:fresh --force --seed 2>/dev/null
# Clear rate-limit counters to prevent CAPTCHA accumulation
docker compose exec -T app php artisan tinker --execute="DB::table('cache')->truncate();" 2>/dev/null || true
# Ensure storage permissions
docker compose exec -T app chmod -R 777 storage bootstrap/cache 2>/dev/null || true

echo "--- Running E2E Tests (Playwright) ---"
if [ -d "src/tests/E2E/node_modules" ]; then
    cd src/tests/E2E && npx playwright test --reporter=list
    cd ../../..
elif docker compose --profile testing ps playwright 2>/dev/null | grep -q "running"; then
    docker compose exec -T playwright npx playwright test --reporter=list
else
    echo "Playwright not available. Install locally with:"
    echo "  cd src/tests/E2E && npm install && npx playwright install chromium"
    echo "Skipping E2E tests."
fi
echo ""

echo "=== All Tests Passed ==="
