#!/bin/bash
set -e

echo "=== HarborBite Startup ==="

# Install PHP dependencies if missing (volume mount may overwrite image's vendor/)
if [ ! -f vendor/autoload.php ]; then
  echo "Installing PHP dependencies..."
  composer install --optimize-autoloader --no-interaction
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
