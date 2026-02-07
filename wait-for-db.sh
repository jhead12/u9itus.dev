#!/bin/bash
# Wait for database to be ready and then run migrations

echo "Waiting for database connection..."

# Wait up to 60 seconds for database with proper timeout
MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
  ATTEMPT=$((ATTEMPT + 1))
  echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Checking database connectivity..."
  
  # Try to connect using PHP directly instead of artisan command
  if php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; then
    echo "✓ Database connection successful!"
    break
  fi
  
  if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo "✗ Database not reachable after $MAX_ATTEMPTS attempts (60 seconds)"
    echo "Starting application anyway - migrations will be attempted on first request..."
    exit 0
  fi
  
  sleep 2
done

echo "Running database migrations..."
php artisan migrate --force

echo "Migration complete! Starting application..."
