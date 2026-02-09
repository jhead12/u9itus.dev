#!/bin/bash
# Wait for database to be ready and then run migrations

echo "=== Setting up Laravel environment ==="
# Ensure required directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ============================================================
# Parse DATABASE_URL or MYSQL_URL if DB_HOST looks like a URL
# Railway often provides DB_HOST as a full connection URL:
#   mysql://user:pass@host:port/dbname
# We need to decompose it into individual env vars.
# ============================================================
RAW_HOST="${DATABASE_URL:-${MYSQL_URL:-${DB_HOST}}}"

if [[ "$RAW_HOST" == mysql://* ]] || [[ "$RAW_HOST" == mysqli://* ]]; then
  echo "Detected full database URL — parsing into components..."
  # Strip the scheme
  WITHOUT_SCHEME="${RAW_HOST#*://}"
  # Extract user:pass
  USERINFO="${WITHOUT_SCHEME%%@*}"
  HOSTINFO="${WITHOUT_SCHEME#*@}"
  # Extract username and password
  export DB_USERNAME="${USERINFO%%:*}"
  export DB_PASSWORD="${USERINFO#*:}"
  # Extract host:port/dbname
  HOST_PORT="${HOSTINFO%%/*}"
  export DB_DATABASE="${HOSTINFO#*/}"
  export DB_HOST="${HOST_PORT%%:*}"
  export DB_PORT="${HOST_PORT#*:}"
  # Also set DB_URL for Laravel's native URL-based config
  export DB_URL="$RAW_HOST"
  export DB_CONNECTION=mysql
fi

echo "=== Database Connection Setup ==="
echo "DB_CONNECTION: ${DB_CONNECTION}"
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
    break
  fi
  
  sleep 3
done

# Only run migrations if the DB connection loop succeeded (not timed out)
if [ $ATTEMPT -lt $MAX_ATTEMPTS ]; then
  echo "Running database migrations..."
  php artisan migrate --force 2>&1 || echo "Migration failed but continuing startup..."
else
  echo "Skipping migrations (database unreachable). Will retry on first request."
fi

# Set default PORT if not provided by Railway
export PORT=${PORT:-8080}

echo "==================================="
echo "Starting Laravel server..."
echo "PORT: $PORT"
echo "Command: php artisan serve --host=0.0.0.0 --port=$PORT"
echo "==================================="

# Use exec to replace shell with PHP process
exec php artisan serve --host=0.0.0.0 --port=$PORT
