# Getting Started

This guide walks you through installing and running U9itus locally.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2 or higher |
| Composer | Latest stable |
| Node.js | 18+ |
| NPM / Yarn | Latest stable |
| SQLite3 | Any (for local dev) |

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/jhead12/u9itus.dev.git
cd u9itus.dev
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install JavaScript dependencies

```bash
npm install
```

### 4. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure the required values:

```env
APP_URL=https://yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Database (SQLite for local dev)
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql  ← use for production

# Stripe
STRIPE_KEY=your-stripe-publishable-key
STRIPE_SECRET=your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=your-webhook-secret
```

### 5. Database setup

```bash
touch database/database.sqlite
php artisan migrate
```

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the development server

```bash
php artisan serve
# Runs at http://localhost:8000
```

## Running Everything Together

Use the convenience script to start Laravel and Vite simultaneously:

```bash
npm run dev:all
```

If you also need the real-time WebSocket server (Phase 11):

```bash
php artisan reverb:start &
npm run dev:all
```

## Admin Account

Create the initial admin account with:

```bash
php artisan admin:create --email=admin@u9itus.com --name="Admin User"
```

Reset the admin password:

```bash
php artisan admin:reset-password --email=admin@u9itus.com
```

The admin portal is accessible at `/admin/login`.

## Verify the Installation

1. Visit `http://localhost:8000`
2. Register as a Voter at `/register/voter`
3. Register as a Politician at `/register/politician`
4. Log in to the Admin portal at `/admin/login`

## Common Setup Issues

| Problem | Solution |
|---------|----------|
| Frontend changes not showing | Ensure `npm run dev` is running; clear browser cache |
| `public/build/manifest.json` missing | Run `npm run build` or `npm run dev` |
| Database migration errors | Run `php artisan migrate:status` to check pending migrations |
| Fresh start needed | `php artisan migrate:fresh` (dev only) |

---

← [Home](Home.md) | [Architecture →](Architecture.md)
