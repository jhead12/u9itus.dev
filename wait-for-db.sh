#!/bin/bash
# Wait for database to be ready and then run migrations
set -o pipefail

echo "=== Setting up Laravel environment ==="
echo "Startup script version: 2026-04-10-app-key-diagnostic-v2"
echo "PHP version: $(php -v | head -1)"

# Validate APP_KEY before bootstrapping Laravel. We only print safe metadata.
if [[ -z "${APP_KEY:-}" ]] && [[ -f ".env" ]]; then
  FILE_APP_KEY=$(grep -E '^APP_KEY=' .env | tail -n 1 | cut -d '=' -f2- | tr -d '\r')
  FILE_APP_KEY="${FILE_APP_KEY%\"}"
  FILE_APP_KEY="${FILE_APP_KEY#\"}"
  FILE_APP_KEY="${FILE_APP_KEY%\'}"
  FILE_APP_KEY="${FILE_APP_KEY#\'}"

  if [[ -n "$FILE_APP_KEY" ]]; then
    export APP_KEY="$FILE_APP_KEY"
    echo "APP_KEY loaded from runtime .env file"
  fi
fi

if [[ -z "${APP_KEY:-}" ]]; then
  echo "FATAL: APP_KEY is missing at runtime."
  echo "Set APP_KEY in Railway service variables for this environment and redeploy."
  echo "Expected format: base64:..."
  exit 1
fi

APP_KEY_LENGTH=${#APP_KEY}
if [[ "$APP_KEY" == base64:* ]]; then
  echo "APP_KEY present (format=base64, length=${APP_KEY_LENGTH})"
else
  echo "APP_KEY present (non-base64 format, length=${APP_KEY_LENGTH})"
fi

# Override session driver to use file storage (database-based sessions require DB to be ready)
export SESSION_DRIVER=file

# Clear any cached config to ensure fresh environment variables are used
echo "Clearing configuration cache..."
if [[ -f bootstrap/cache/config.php ]]; then
  rm -f bootstrap/cache/config.php
  echo "Removed bootstrap/cache/config.php"
fi
php artisan config:clear 2>&1 || echo "Config cache clear skipped (may not exist)"
php artisan cache:clear 2>&1 || echo "Cache clear skipped (may not exist)"
php artisan view:clear 2>&1 || echo "View cache clear skipped (may not exist)"
php artisan route:clear 2>&1 || echo "Route cache clear skipped (may not exist)"

# Verify what Laravel itself resolves after cache clears.
LARAVEL_APP_KEY=$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap(); $key = config("app.key"); echo is_string($key) ? $key : "";' 2>/dev/null || true)
if [[ -z "$LARAVEL_APP_KEY" ]]; then
  echo "FATAL: Laravel resolved app.key as empty after bootstrap."
  if [[ -n "${APP_KEY:-}" ]]; then
    echo "APP_KEY exists in process env but Laravel config is empty."
  else
    echo "APP_KEY is missing in process env."
  fi
  echo "Check Railway variable scope (service + environment) and redeploy."
  exit 1
fi

LARAVEL_APP_KEY_LENGTH=${#LARAVEL_APP_KEY}
if [[ "$LARAVEL_APP_KEY" == base64:* ]]; then
  echo "Laravel app.key resolved (format=base64, length=${LARAVEL_APP_KEY_LENGTH})"
else
  echo "Laravel app.key resolved (non-base64 format, length=${LARAVEL_APP_KEY_LENGTH})"
fi

# Ensure required directories exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ============================================================
# Database Configuration Priority:
# 1. Parse DATABASE_URL/MYSQL_URL if provided
# 2. Otherwise use individual DB_* environment variables
# ============================================================

# Get the database URL from Railway (DATABASE_URL, MYSQL_URL, or DB_HOST if it's a URL)
RAW_URL="${DATABASE_URL:-${MYSQL_URL}}"

# If DB_HOST contains a URL scheme, use it as the source
if [[ -n "$DB_HOST" ]] && [[ "$DB_HOST" == mysql://* || "$DB_HOST" == mysqli://* ]]; then
  RAW_URL="$DB_HOST"
fi

# Parse the database URL if we have one
if [[ -n "$RAW_URL" ]] && [[ "$RAW_URL" == mysql://* || "$RAW_URL" == mysqli://* ]]; then
  echo "Detected database URL — parsing into components..."
  
  # Strip the scheme (mysql:// or mysqli://)
  WITHOUT_SCHEME="${RAW_URL#*://}"
  
  # Extract user:pass@host:port/database
  USERINFO="${WITHOUT_SCHEME%%@*}"
  HOSTINFO="${WITHOUT_SCHEME#*@}"
  
  # Extract username and password
  export DB_USERNAME="${USERINFO%%:*}"
  export DB_PASSWORD="${USERINFO#*:}"
  
  # Extract host:port/database
  HOST_PORT="${HOSTINFO%%/*}"
  export DB_DATABASE="${HOSTINFO#*/}"
  export DB_HOST="${HOST_PORT%%:*}"
  export DB_PORT="${HOST_PORT#*:}"
  
  # If port is same as host (no colon), default to 3306
  if [[ "$DB_PORT" == "$DB_HOST" ]]; then
    export DB_PORT=3306
  fi
  
  export DB_CONNECTION=mysql
  echo "Parsed database connection from URL"
elif [[ -n "$DB_HOST" ]] && [[ "$DB_HOST" != mysql://* ]] && [[ "$DB_HOST" != mysqli://* ]]; then
  # Individual vars are already set correctly
  echo "Using individual database environment variables..."
  export DB_CONNECTION="${DB_CONNECTION:-mysql}"
else
  echo "WARNING: No valid database configuration found!"
  echo "Set either DATABASE_URL or individual DB_* environment variables"
fi

echo "=== Database Connection Setup ==="
echo "DB_CONNECTION: ${DB_CONNECTION:-not set}"
echo "DB_HOST: ${DB_HOST:-not set}"
echo "DB_PORT: ${DB_PORT:-not set}"
echo "DB_DATABASE: ${DB_DATABASE:-not set}"
echo "DB_USERNAME: ${DB_USERNAME:-not set}"
echo "================================="

echo "=== Stripe Readiness Check ==="
if [[ -n "${STRIPE_SECRET:-}" ]]; then
  if [[ "${STRIPE_SECRET}" == sk_live_* ]]; then
    echo "STRIPE_SECRET: set (live mode)"
  elif [[ "${STRIPE_SECRET}" == sk_test_* ]]; then
    echo "STRIPE_SECRET: set (test mode)"
  else
    echo "STRIPE_SECRET: set (unrecognized key format)"
  fi
else
  echo "STRIPE_SECRET: missing"
fi

if [[ -n "${STRIPE_KEY:-}" ]]; then
  echo "STRIPE_KEY: set"
else
  echo "STRIPE_KEY: missing"
fi

if [[ -n "${STRIPE_WEBHOOK_SECRET:-}" ]]; then
  echo "STRIPE_WEBHOOK_SECRET: set"
else
  echo "STRIPE_WEBHOOK_SECRET: missing (webhook signature verification disabled)"
fi
echo "================================="

# Process role for Railway service split.
# web       -> HTTP server only
# queue     -> queue worker only
# scheduler -> scheduler only
# reverb    -> websocket server only
PROCESS_ROLE="${PROCESS_ROLE:-web}"
echo "PROCESS_ROLE: ${PROCESS_ROLE}"

# Set default PORT if not provided by Railway
export PORT=${PORT:-8080}

# Only worker roles require DB readiness before process start.
DB_REQUIRED=false
if [[ "$PROCESS_ROLE" == "queue" ]] || [[ "$PROCESS_ROLE" == "scheduler" ]]; then
  DB_REQUIRED=true
fi

# Validate database configuration for DB-required roles only.
if [[ "$DB_REQUIRED" == "true" ]]; then
  if [[ -z "$DB_HOST" ]] || [[ -z "$DB_PORT" ]] || [[ -z "$DB_DATABASE" ]]; then
    echo "ERROR: Required database environment variables are missing!"
    echo "Please set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD"
    exit 1
  fi

  if [[ "$DB_HOST" == *"://"* ]]; then
    echo "ERROR: DB_HOST appears to be a full URL: $DB_HOST"
    echo "DB_HOST should only contain the hostname, not a full connection string"
    echo "Check your Railway environment variables"
    exit 1
  fi
else
  if [[ -z "$DB_HOST" ]] || [[ -z "$DB_PORT" ]] || [[ -z "$DB_DATABASE" ]]; then
    echo "WARNING: DB variables are incomplete for PROCESS_ROLE=${PROCESS_ROLE}."
    echo "Continuing startup so web/reverb healthchecks can pass."
  fi
fi

# Unset conflicting URL variables to prevent Laravel from using them
unset DATABASE_URL
unset MYSQL_URL
unset DB_URL

# Quick sanity check: can Laravel boot at all?
echo "=== Pre-flight check ==="
php artisan --version 2>&1 || {
  echo "FATAL: Laravel cannot boot. Check configuration."
  echo "Attempting to show error details:"
  php artisan config:show app 2>&1 || true
  # Start server anyway so Railway can see error pages
}

DB_CONNECTED=false

if [[ "$DB_REQUIRED" == "true" ]]; then
  # Wait for database (30 attempts / ~2.5 minutes to survive Railway MySQL restart cycles)
  MAX_ATTEMPTS=30
  ATTEMPT=0

  while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    ATTEMPT=$((ATTEMPT + 1))
    echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Checking database connectivity to ${DB_HOST}:${DB_PORT}..."

    if php -r "
      try {
        \$pdo = new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
          getenv('DB_USERNAME'),
          getenv('DB_PASSWORD'),
          [PDO::ATTR_TIMEOUT => 10]
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
    if [ $ATTEMPT -lt 5 ]; then
      sleep 3
    elif [ $ATTEMPT -lt 15 ]; then
      sleep 5
    else
      sleep 8
    fi
  done

  if [ "$DB_CONNECTED" != true ]; then
    echo "✗ Database not reachable after $MAX_ATTEMPTS attempts"
    exit 1
  fi
else
  echo "Skipping DB wait for PROCESS_ROLE=${PROCESS_ROLE} to keep startup fast."
fi

# Ensure core Spatie roles exist on every deploy (idempotent — safe to repeat).
# Runs only when the DB is expected to be reachable for this process role.
if [[ "$DB_REQUIRED" == "true" ]]; then
  echo "=== Ensuring core roles ==="
  php artisan roles:ensure 2>&1 || echo "roles:ensure skipped (non-fatal)"
  echo "================================="
fi

echo "==================================="
case "$PROCESS_ROLE" in
  web)
    # Run migrations on every web deployment so new tables are always present.
    # Migrations are idempotent — this is safe to run on every startup.
    echo "=== Running database migrations ==="
    php artisan migrate --force 2>&1 || echo "WARNING: migrate failed (non-fatal — continuing startup)"
    echo "================================="

    # Safe default: disable realtime broadcasting unless explicitly enabled.
    # This avoids long request stalls when Reverb is not reachable.
    if [ "${ENABLE_REVERB:-false}" != "true" ]; then
      export BROADCAST_DRIVER=log
      export BROADCAST_CONNECTION=log
      echo "ENABLE_REVERB=false — forcing BROADCAST_DRIVER=log for web role"
    fi

    echo "Starting Laravel web server..."
    echo "PORT: $PORT"
    echo "Command: php artisan serve --host=0.0.0.0 --port=$PORT --no-reload"
    exec php artisan serve --host=0.0.0.0 --port=$PORT --no-reload 2>&1
    ;;

  queue)
    echo "Starting Laravel queue worker (dedicated service)..."
    exec php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=600 --no-interaction 2>&1
    ;;

  scheduler)
    echo "Starting Laravel scheduler worker (dedicated service)..."
    exec php artisan schedule:work --no-interaction 2>&1
    ;;

  reverb)
    REVERB_BIND_PORT="${REVERB_SERVER_PORT:-6001}"
    echo "Starting Reverb WebSocket server (dedicated service) on port ${REVERB_BIND_PORT}..."
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_BIND_PORT}" --no-interaction 2>&1
    ;;

  *)
    echo "ERROR: Unknown PROCESS_ROLE=${PROCESS_ROLE}. Expected one of: web, queue, scheduler, reverb"
    exit 1
    ;;
esac
