# Deployment

U9itus is deployed on [Railway.app](https://railway.app). The production environment uses MySQL as the database and runs the application via a Procfile or `railway.json` configuration.

**Production URL:** https://u9itus-production.up.railway.app

## Railway Configuration

The `railway.json` at the project root defines the build and deploy settings. The `Procfile` specifies the web process command.

### Key environment variables for production

```env
APP_ENV=production
APP_KEY=<generated-key>
APP_URL=https://u9itus-production.up.railway.app
FRONTEND_URL=https://u9itus-production.up.railway.app

# MySQL (provided by Railway)
DB_CONNECTION=mysql
DB_HOST=<railway-mysql-host>
DB_PORT=3306
DB_DATABASE=<db-name>
DB_USERNAME=<db-user>
DB_PASSWORD=<db-password>

# Stripe
STRIPE_KEY=<publishable-key>
STRIPE_SECRET=<secret-key>
STRIPE_WEBHOOK_SECRET=<webhook-secret>

# Cache / Session / Queue
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Reverb WebSockets (if using Phase 11)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=<id>
REVERB_APP_KEY=<key>
REVERB_APP_SECRET=<secret>
REVERB_HOST=<railway-reverb-domain>
REVERB_PORT=443
REVERB_SCHEME=https
```

## Deploy Steps

### First-time setup

```bash
# 1. Set all required environment variables in Railway dashboard

# 2. Run migrations via Railway CLI
railway run php artisan migrate

# 3. Create the admin account
railway run php artisan admin:create --email=admin@u9itus.com --name="Admin User"
```

### Routine deployment

Push to the configured Railway branch (typically `main`). Railway automatically:
1. Installs Composer dependencies
2. Installs NPM dependencies
3. Runs `npm run build` to compile frontend assets
4. Restarts the web service

## Reverb WebSocket Service (Phase 11)

To run the Reverb server on Railway, add a **second service** (Worker) with:

- **Start command:** `php artisan reverb:start --host=0.0.0.0 --port=8080`
- **Exposed port:** `8080` (TCP)
- Set `REVERB_HOST` to the Railway-assigned domain for the Reverb service
- Set `REVERB_SCHEME=https` and `REVERB_PORT=443` when behind Railway's TLS termination

## Useful Railway Commands

```bash
# Run artisan commands in production
railway run php artisan <command>

# Open a shell in the production environment
railway shell

# View live logs
railway logs
```

## Admin Account Setup in Production

```bash
# Create admin account
railway run php artisan admin:create --email=admin@u9itus.com --name="Admin User"

# Reset admin password
railway run php artisan admin:reset-password --email=admin@u9itus.com
```

## Troubleshooting Production Issues

| Problem | Solution |
|---------|----------|
| Migrations not applied | `railway run php artisan migrate` |
| 500 errors | Check `railway logs` for stack traces; verify `.env` variables |
| WebSockets not working | Verify Reverb service is running and `REVERB_HOST` is correct |
| Stripe webhooks failing | Confirm `STRIPE_WEBHOOK_SECRET` matches Railway's endpoint secret |
| Queue jobs not processing | Start a worker: `railway run php artisan queue:work` |

---

← [Development](Development.md) | [Implementation Progress →](Implementation-Progress.md)
