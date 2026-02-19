# U9itus Development Guide

## Project Architecture

U9itus is a **standalone Laravel 12 application** deployed on Railway. The stack includes:

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade templates with Tailwind CSS dark theme + Alpine.js
- **Database**: SQLite (development) / MySQL (Railway production)
- **Authentication**: Laravel Sanctum + session-based auth
- **Permissions**: Spatie Laravel Permission (`admin`, `politician`, `voter`)
- **Payments**: Stripe (politician billing)
- **Build**: Vite (JS/CSS compilation)

## Development Workflow

### 1. Start Backend (Laravel)

```bash
php artisan serve
# Runs on http://localhost:8000
```

### 2. Start Frontend Build (Vite)

```bash
npm run dev
# Watches and compiles assets (JS, CSS)
```

### 3. Run Both Simultaneously

```bash
npm run dev:all
# Uses concurrently to run Laravel + Vite together
```

## Configuration

### `.env`

Key environment variables:

```env
APP_URL=https://yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Database
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql (for production)

# Stripe
STRIPE_KEY=your-stripe-publishable-key
STRIPE_SECRET=your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=your-webhook-secret
```

### Build Process

Vite compiles JS and CSS files from `resources/` and outputs to `public/build/`. Blade templates reference them:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

## Typical Development Flow

1. **Make backend changes** → Edit Laravel controllers, models, routes
2. **Make frontend changes** → Edit Blade views, JS/CSS files in `resources/`
3. **Test locally** → `npm run dev:all` + visit http://localhost:8000
4. **Run tests** → `php artisan test`
5. **Deploy** → Push to Railway

## Quick Reference

| Task           | Command               |
| -------------- | --------------------- |
| Start Laravel  | `php artisan serve`   |
| Watch frontend | `npm run dev`         |
| Start both     | `npm run dev:all`     |
| Build for prod | `npm run build`       |
| Run migrations | `php artisan migrate` |
| Run tests      | `php artisan test`    |
| Code style     | `./vendor/bin/pint`   |

## Common Issues

### Frontend changes not showing

→ Make sure `npm run dev` is running (Vite watch mode)  
→ Clear browser cache  
→ Check `public/build/manifest.json` exists

### Database migration errors

→ Run `php artisan migrate:status` to check pending migrations  
→ Use `php artisan migrate:fresh` for a clean slate (development only)

### Test failures after changes

→ Run `php artisan test --filter=TestName` to isolate failures  
→ Check `phpunit.xml` for test database configuration

## Need Help?

- Laravel Docs: https://laravel.com/docs
- GitHub Issues: https://github.com/jhead12/u9itus.dev/issues
