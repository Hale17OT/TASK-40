#!/bin/bash
set -e

echo "=== HarborBite Startup ==="

# Install PHP dependencies if missing (volume mount may overwrite image's vendor/)
if [ ! -f vendor/autoload.php ]; then
  echo "Installing PHP dependencies..."
  composer install --optimize-autoloader --no-interaction
fi

# Auto-generate APP_KEY if not set or placeholder
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "GENERATE" ]; then
  echo "Generating APP_KEY..."
  export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

# Auto-generate PAYMENT_HMAC_KEY if not set or placeholder
if [ -z "$PAYMENT_HMAC_KEY" ] || [ "$PAYMENT_HMAC_KEY" = "GENERATE" ]; then
  echo "Generating PAYMENT_HMAC_KEY..."
  export PAYMENT_HMAC_KEY="$(head -c 32 /dev/urandom | xxd -p -c 64)"
fi

# Auto-generate DEVICE_FINGERPRINT_SALT if not set or placeholder
if [ -z "$DEVICE_FINGERPRINT_SALT" ] || [ "$DEVICE_FINGERPRINT_SALT" = "GENERATE" ]; then
  echo "Generating DEVICE_FINGERPRINT_SALT..."
  export DEVICE_FINGERPRINT_SALT="$(head -c 16 /dev/urandom | xxd -p -c 32)"
fi

# Auto-generate DB_PASSWORD if not set or placeholder
if [ -z "$DB_PASSWORD" ] || [ "$DB_PASSWORD" = "GENERATE" ]; then
  echo "Generating DB_PASSWORD..."
  export DB_PASSWORD="$(head -c 24 /dev/urandom | base64 | tr -d '=/+')"
fi

# Wait for PostgreSQL
echo "Waiting for PostgreSQL..."
until pg_isready -h postgres -U harborbite -q 2>/dev/null; do
  sleep 1
done
echo "PostgreSQL is ready."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Seed database (only if users table is empty)
echo "Seeding database..."
php artisan db:seed --force

# Fix permissions for volume-mounted storage
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Build assets if manifest is missing (volume-mounted dev)
if [ ! -f public/build/manifest.json ]; then
  echo "Building frontend assets..."
  npm install --silent && npm run build
fi

# Clear any stale caches (important when volume-mounted in dev)
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "=== HarborBite Ready ==="

# Start supervisord
exec "$@"
