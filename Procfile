web: ./wait-for-db.sh
scheduler: php artisan schedule:work --no-interaction
queue: php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=600 --no-interaction
