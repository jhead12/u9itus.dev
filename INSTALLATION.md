# Dial4Dough Installation Guide

Complete step-by-step installation guide for the Dial4Dough MVP platform.

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Fresh Installation](#fresh-installation)
3. [Configuration](#configuration)
4. [Database Setup](#database-setup)
5. [Authentication Setup](#authentication-setup)
6. [File Storage](#file-storage)
7. [Email Configuration](#email-configuration)
8. [Payment Integration](#payment-integration)
9. [Troubleshooting](#troubleshooting)

## System Requirements

### Minimum Requirements
- **PHP**: 8.1 or higher
- **Composer**: 2.0 or higher
- **Database**: SQLite 3.x (or PostgreSQL 12+, MySQL 8.0+)
- **Node.js**: 18.x or higher
- **NPM**: 9.x or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### PHP Extensions Required
```bash
php -m | grep -E 'PDO|pdo_sqlite|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|fileinfo'
```

Required extensions:
- PDO
- pdo_sqlite (or pdo_pgsql for PostgreSQL)
- mbstring
- openssl
- tokenizer
- xml
- ctype
- json
- bcmath
- fileinfo

## Fresh Installation

### Step 1: Clone Repository

```bash
git clone https://github.com/jhead12/dial4dough.dev.git
cd dial4dough.dev
```

### Step 2: Install PHP Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

For development environment:
```bash
composer install
```

### Step 3: Install Node Dependencies

```bash
npm install
```

### Step 4: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 5: Database Setup

#### Option A: SQLite (Recommended for MVP)

```bash
# Create database file
touch database/database.sqlite

# Verify file exists
ls -l database/database.sqlite
```

#### Option B: PostgreSQL (Production)

1. Create database:
```sql
CREATE DATABASE dial4dough;
CREATE USER dial4dough_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE dial4dough TO dial4dough_user;
```

2. Update `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dial4dough
DB_USERNAME=dial4dough_user
DB_PASSWORD=your_secure_password
```

#### Option C: MySQL (Alternative)

1. Create database:
```sql
CREATE DATABASE dial4dough CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dial4dough_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON dial4dough.* TO 'dial4dough_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dial4dough
DB_USERNAME=dial4dough_user
DB_PASSWORD=your_secure_password
```

### Step 6: Run Migrations and Seeders

```bash
# Run migrations
php artisan migrate

# Seed database with roles and admin user
php artisan db:seed

# Or do both at once
php artisan migrate:fresh --seed
```

### Step 7: Build Frontend Assets

```bash
# For production
npm run build

# For development (with hot reload)
npm run dev
```

### Step 8: Storage Links

```bash
# Create symbolic link for public storage
php artisan storage:link
```

### Step 9: Set Permissions

```bash
# Set proper permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Step 10: Start Development Server

```bash
php artisan serve
```

Visit: http://localhost:8000

## Configuration

### App Configuration (.env)

```env
APP_NAME=Dial4Dough
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

### Dial4Dough Settings

```env
# Head Enterprises fee (15%)
HEAD_ENTERPRISES_FEE_PERCENT=15.0

# Assignment expires after 24 hours
ASSIGNMENT_EXPIRY_HOURS=24

# Minimum 80% watch time required
MIN_WATCH_TIME_PERCENT=80

# Minimum $25 payout threshold
MIN_PAYOUT_AMOUNT=25.00

# Default payment per view
DEFAULT_PAYMENT_PER_VIEW=1.00

# Video duration limits (seconds)
MAX_VIDEO_DURATION=20
MIN_VIDEO_DURATION=10
```

## Authentication Setup

### Default Admin User

After seeding, default admin credentials are:
- Email: admin@dial4dough.com
- Password: password

**IMPORTANT**: Change these credentials immediately!

```bash
php artisan tinker
```

```php
$admin = User::where('email', 'admin@dial4dough.com')->first();
$admin->password = Hash::make('new_secure_password');
$admin->save();
```

### Creating Additional Users

#### Create Admin User
```bash
php artisan tinker
```

```php
$user = User::create([
    'name' => 'Jane Admin',
    'first_name' => 'Jane',
    'last_name' => 'Admin',
    'email' => 'jane@headenterprises.com',
    'password' => Hash::make('secure_password'),
    'user_type' => 'admin',
    'email_verified_at' => now(),
    'is_verified' => true,
    'kyc_status' => 'approved',
]);
$user->assignRole('admin');
```

## File Storage

### Local Storage (MVP)

Files are stored in `storage/app/public/campaigns/`

```bash
# Ensure directory exists
mkdir -p storage/app/public/campaigns
chmod -R 775 storage/app/public/campaigns
```

### AWS S3 (Production)

1. Install S3 package:
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

2. Update `.env`:
```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=dial4dough-media
AWS_USE_PATH_STYLE_ENDPOINT=false
```

3. Update `config/filesystems.php` if needed.

## Email Configuration

### Development (Log Driver)

```env
MAIL_MAILER=log
```

Emails will be logged to `storage/logs/laravel.log`

### Production (SMTP)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@dial4dough.com"
MAIL_FROM_NAME="Dial4Dough"
```

### Using SendGrid

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
```

## Payment Integration

### Stripe Setup

1. Get API keys from https://dashboard.stripe.com/test/apikeys

2. Update `.env`:
```env
STRIPE_KEY=pk_test_your_publishable_key
STRIPE_SECRET=sk_test_your_secret_key
```

3. For production, use live keys:
```env
STRIPE_KEY=pk_live_your_publishable_key
STRIPE_SECRET=sk_live_your_secret_key
```

### PayPal (Manual for MVP)

PayPal integration is manual for MVP. Future versions will include API integration.

## Task Scheduling

### Setup Cron Job

For automated tasks (expired assignments, payouts), add to crontab:

```bash
crontab -e
```

Add:
```cron
* * * * * cd /path/to/dial4dough.dev && php artisan schedule:run >> /dev/null 2>&1
```

### Verify Scheduled Tasks

```bash
php artisan schedule:list
```

Expected output:
- `assignments:handle-expired` - Hourly
- `payouts:process-viewer` - Daily

## Troubleshooting

### Issue: Database Connection Error

**Solution**: Verify database credentials and ensure database exists.

```bash
# Test SQLite
php artisan tinker
DB::connection()->getPdo();

# Test PostgreSQL/MySQL
php artisan migrate:status
```

### Issue: Permission Denied on Storage

**Solution**: Set proper permissions.

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Issue: 500 Error After Installation

**Solution**: Check logs and enable debug mode temporarily.

```bash
tail -f storage/logs/laravel.log
```

In `.env`:
```env
APP_DEBUG=true
```

### Issue: Assets Not Loading

**Solution**: Clear cache and rebuild assets.

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
npm run build
```

### Issue: Roles Not Working

**Solution**: Clear permission cache.

```bash
php artisan permission:cache-reset
```

### Issue: Migration Fails

**Solution**: Check if tables already exist.

```bash
# Drop all tables and re-migrate
php artisan migrate:fresh --seed

# Or rollback and re-run
php artisan migrate:rollback
php artisan migrate
```

## Production Deployment

### Pre-Deployment Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set proper `APP_URL`
- [ ] Use PostgreSQL/MySQL instead of SQLite
- [ ] Configure proper mail driver
- [ ] Set up AWS S3 for file storage
- [ ] Configure Redis for cache/queue
- [ ] Set up SSL certificate
- [ ] Configure backups
- [ ] Set up monitoring (Laravel Telescope, Sentry)
- [ ] Enable queue workers
- [ ] Set up cron for scheduled tasks
- [ ] Change default admin password
- [ ] Test payment integration with live keys

### Optimization Commands

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Run optimizations
php artisan optimize
```

### Queue Workers (Production)

Set up supervisor for queue workers:

```ini
[program:dial4dough-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/dial4dough.dev/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/dial4dough.dev/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dial4dough-worker:*
```

## Support

For additional help:
- GitHub Issues: https://github.com/jhead12/dial4dough.dev/issues
- Main README: See README.md for feature overview

## License

MIT License
