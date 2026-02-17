# U9itus Development Guide

## Project Architecture

This is a **Laravel backend + Wix SDK integration** project, NOT a Wix-hosted app. The backend runs on Railway/your server, and Wix integration happens through:

- OAuth authentication
- Wix JavaScript SDKs (`@wix/sdk`, `@wix/dashboard`, `@wix/members`)
- Webhook handlers
- Embedded dashboard pages and widgets

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

## Wix CLI Commands

The `wix` CLI is **NOT for local development**. It's only for publishing your app to the Wix Marketplace.

### ❌ Don't Use (for local dev)

- `npm run wix:dev` — Not applicable for Laravel-hosted apps
- `npx wix dev` — Only for Wix Blocks/Platform hosted projects

### ✅ Use (for publishing)

- `npm run wix:login` — Authenticate with Wix
- `npm run wix:publish` — Publish app version to Wix Marketplace
- `npm run wix:create-version` — Create new app version

## Configuration Files

### `wix.config.json`

This file is **metadata for the Wix App Market**, not for local development. It defines:

- App ID and project ID
- Dashboard page URLs (pointing to your Laravel routes)
- Widget URLs
- Webhook endpoints
- OAuth redirect URLs

The Wix CLI reads this when you publish, but your local dev ignores it.

### `.env`

Your actual Wix credentials for OAuth and webhooks:

```env
WIX_APP_ID=3cc2de07-3a6c-4542-b7b3-e92721c6df8a
WIX_APP_SECRET=91b427ed-cf50-447e-a391-0d7337be5007
WIX_WEBHOOK_SECRET=MIIBIjAN...
WIX_APP_URL=https://u9itus-production.up.railway.app
WIX_REDIRECT_URL=/wix/oauth/callback
```

## Testing Wix Integration Locally

### Option 1: Use ngrok/localhost.run

```bash
# Expose local server to internet
ngrok http 8000

# Update .env with ngrok URL
WIX_APP_URL=https://abc123.ngrok.io

# Test OAuth flow and webhooks
```

### Option 2: Test on Railway

```bash
# Push to Railway
git push railway main

# Test with production URL
# https://u9itus-production.up.railway.app
```

## Typical Development Flow

1. **Make backend changes** → Edit Laravel controllers, models, routes
2. **Make frontend changes** → Edit Blade views, JS files in `resources/`, `src/wix/`
3. **Test locally** → `npm run dev:all` + visit http://localhost:8000
4. **Test Wix features** → Use ngrok or push to Railway
5. **Publish to Wix Market** → `npm run wix:publish` (only when ready for production)

## Why `wix dev` Doesn't Work

The error you saw:

```
✖ A configuration file was found, but it is either malformed or missing required fields
```

This happens because `wix dev` expects a **Wix Blocks or Wix Platform app** that runs entirely on Wix infrastructure. Our app is:

- Backend: Laravel (Railway)
- Frontend: Blade + Wix SDKs
- Integration: OAuth + Webhooks

The Wix CLI can't run a Laravel server, so `wix dev` is not applicable.

## Frontend Source Files

### `src/wix/dashboard-page.js`

Wix Dashboard SDK initialization for dashboard pages. This code runs **in the browser** when your Laravel views are loaded inside Wix dashboard iframes.

### `src/wix/widget.js`

Widget JavaScript for the embeddable feed. This runs on Wix site pages where users embed your widget.

### Build Process

Vite compiles these files and outputs to `public/build/`. Your Blade templates reference them:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js', 'src/wix/dashboard-page.js'])
```

## Quick Reference

| Task                  | Command               |
| --------------------- | --------------------- |
| Start Laravel         | `php artisan serve`   |
| Watch frontend        | `npm run dev`         |
| Start both            | `npm run dev:all`     |
| Build for prod        | `npm run build`       |
| Run migrations        | `php artisan migrate` |
| Run tests             | `php artisan test`    |
| Login to Wix          | `npm run wix:login`   |
| Publish to Wix Market | `npm run wix:publish` |

## Common Issues

### "Configuration file malformed"

→ Don't run `wix dev`. Use `npm run dev:all` instead.

### "OAuth error: invalid redirect"

→ Update `WIX_APP_URL` in `.env` to match your ngrok/Railway URL  
→ Update redirect URL in Wix Developer Console

### "Webhook signature verification failed"

→ Ensure `WIX_WEBHOOK_SECRET` matches the public key from Wix Developer Console

### Frontend changes not showing

→ Make sure `npm run dev` is running (Vite watch mode)  
→ Clear browser cache  
→ Check `public/build/manifest.json` exists

## Need Help?

- Wix SDK Docs: https://dev.wix.com/docs/sdk
- Laravel Docs: https://laravel.com/docs
- GitHub Issues: https://github.com/jhead12/u9itus.dev/issues
