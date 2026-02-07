#!/bin/bash
# Wait for database to be ready and then run migrations

echo "Waiting for database connection..."

# Wait up to 30 seconds for database
until php artisan db:monitor --max-attempts=1 2>/dev/null || [ $SECONDS -gt 30 ]; do
  echo "Database not ready yet... retrying in 2 seconds"
  sleep 2
done

if [ $SECONDS -gt 30 ]; then
  echo "WARNING: Database not reachable after 30 seconds, starting anyway..."
  exit 0
fi

echo "Database is ready! Running migrations..."
php artisan migrate --force

echo "Migration complete! Starting application..."
