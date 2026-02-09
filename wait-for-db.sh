#!/bin/bash
# Wait for database to be ready and then run migrations
set -o pipefail

echo "=== Setting up Laravel environment ==="
echo "PHP version: $(php -v | head -1)"

# Clear any cached config to ensure fresh environment variables are used
echo "Clearing configuration cache..."
php artisan config:clear 2>&1 || echo "Config cache clear skipped (may not exist)"
php artisan cache:clear 2>&1 || echo "Cache clear skipped (may not exist)"

# Ensure required directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ============================================================
# Database Configuration Priority:
# 1. Use individual DB_* vars if DB_HOST is already a proper hostname
# 2. Otherwise parse DATABASE_URL/MYSQL_URL if provided
# ============================================================

# Check if DB_HOST is already properly set (not a URL)
if [[ -n "$DB_HOST" ]] && [[ "$DB_HOST" != mysql://* ]] && [[ "$DB_HOST" != mysqli://* ]]; then
  # Individual vars are already set correctly, use them
  echo "Using individual database environment variables..."
  export DB_CONNECTION="${DB_CONNECTION:-mysql}"
else
  # Try to parse DATABASE_URL or MYSQL_URL
  RAW_HOST="${DATABASE_URL:-${MYSQL_URL}}"
  
  if [[ -n "$RAW_HOST" ]] && ([[ "$RAW_HOST" == mysql://* ]] || [[ "$RAW_HOST" == mysqli://* ]]); then
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
  else
    echo "WARNING: No valid database configuration found!"
    echo "Set either DATABASE_URL or individual DB_* environment variables"
  fi
fi

echo "=== Database Connection Setup ==="
echo "DB_CONNECTION: ${DB_CONNECTION:-not set}"
echo "DB_HOST: ${DB_HOST:-not set}"
echo "DB_PORT: ${DB_PORT:-not set}"
echo "DB_DATABASE: ${DB_DATABASE:-not set}"
echo "DB_USERNAME: ${DB_USERNAME:-not set}"
echo "================================="

# Validate database configuration
if [[ -z "$DB_HOST" ]] || [[ -z "$DB_PORT" ]] || [[ -z "$DB_DATABASE" ]]; then
  echo "ERROR: Required database environment variables are missing!"
  echo "Please set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD"
  exit 1
fi

# Ensure DB_HOST is not a URL
if [[ "$DB_HOST" == *"://"* ]]; then
  echo "ERROR: DB_HOST appears to be a full URL: $DB_HOST"
  echo "DB_HOST should only contain the hostname, not a full connection string"
  echo "Check your Railway environment variables"
  exit 1
fi

# Unset conflicting URL variables to prevent Laravel from using them
unset DATABASE_URL
unset MYSQL_URL
unset DB_URL

# Set default PORT if not provided by Railway
export PORT=${PORT:-8080}

# Quick sanity check: can Laravel boot at all?
echo "=== Pre-flight check ==="
php artisan --version 2>&1 || {
  echo "FATAL: Laravel cannot boot. Check configuration."
  echo "Attempting to show error details:"
  php artisan config:show app 2>&1 || true
  # Start server anyway so Railway can see error pages
}

# Wait for database (reduced to 10 attempts / ~30 seconds)
MAX_ATTEMPTS=10
ATTEMPT=0
DB_CONNECTED=false

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
  ATTEMPT=$((ATTEMPT + 1))
  echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Checking database connectivity to ${DB_HOST}:${DB_PORT}..."
  
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
    DB_CONNECTED=true
    break
  fi
  
  echo ""
  sleep 3
done

if [ "$DB_CONNECTED" = true ]; then
  echo "Running database migrations..."
  php artisan migrate --force 2>&1 || echo "WARNING: Migration failed but continuing startup..."
else
  echo "✗ Database not reachable after $MAX_ATTEMPTS attempts"
  echo "Skipping migrations — will retry on first request..."
fi

echo "==================================="
echo "Starting Laravel server..."
echo "PORT: $PORT"
echo "Command: php artisan serve --host=0.0.0.0 --port=$PORT"
echo "==================================="

# Use exec to replace shell with PHP process
exec php artisan serve --host=0.0.0.0 --port=$PORT 2>&1
