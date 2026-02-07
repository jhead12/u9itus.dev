#!/bin/bash
# Wait for database to be ready and then run migrations

echo "=== Setting up Laravel environment ==="
# Ensure required directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "=== Database Connection Setup ==="
echo "DB_HOST: ${DB_HOST}"
echo "DB_PORT: ${DB_PORT}"
echo "DB_DATABASE: ${DB_DATABASE}"
echo "DB_USERNAME: ${DB_USERNAME}"
echo "================================="

# Wait up to 90 seconds for database with proper timeout
MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
  ATTEMPT=$((ATTEMPT + 1))
  echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Checking database connectivity to ${DB_HOST}:${DB_PORT}..."
  
  # Try to connect using PHP directly instead of artisan command
  if php -r "
    try {
      \$pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 5]
      );
      echo 'connected';
      exit(0);
    } catch (Exception \$e) {
      echo 'failed: ' . \$e->getMessage();
      exit(1);
    }
  " 2>&1; then
    echo ""
    echo "✓ Database connection successful!"
    break
  fi
  
  echo ""
  
  if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo "✗ Database not reachable after $MAX_ATTEMPTS attempts"
    echo "Starting application anyway - migrations will be attempted on first request..."
    exit 0
  fi
  
  sleep 3
done

echo "Running database migrations..."
php artisan migrate --force 2>&1 || echo "Migration failed but continuing startup..."

echo "Running config cache..."
php artisan config:cache 2>&1 || true

echo "Setup complete! Starting application on port ${PORT:-8080}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
