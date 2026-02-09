# Railway Production 500 Error Fix

## Problem Summary

The production site is experiencing 500 errors due to database connection issues. The root causes are:

1. **Database URL Parsing Errors**: Railway's `DATABASE_URL` variable was overriding individual database settings
2. **Default Connection Type**: Laravel was defaulting to 'sqlite' instead of 'mysql'
3. **Config Cache Issues**: Cached configuration with wrong values persisted across deployments

## Files Changed

1. ✅ `wait-for-db.sh` - Fixed database configuration priority and added validation
2. ✅ `config/database.php` - Changed default connection from 'sqlite' to 'mysql'

## Required Railway Configuration

### Step 1: Set Individual Database Environment Variables

Go to your Railway project settings → Variables and ensure these are set:

```bash
DB_CONNECTION=mysql
DB_HOST=shinkansen.proxy.rlwy.net
DB_PORT=39648
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=vrwpOCYWgeKYjSfkBIdSqSIPuOvgjzke
```

### Step 2: Remove Conflicting Variables (if they exist)

Remove or unset these variables in Railway if they exist:

- `DATABASE_URL`
- `MYSQL_URL`
- `DB_URL`

Railway's MySQL service may auto-generate these. Our startup script now explicitly unsets them to prevent conflicts.

### Step 3: Verify Other Required Variables

Ensure these are also set in Railway:

```bash
APP_NAME=Dial4Dough
APP_ENV=production
APP_KEY=base64:XFlGX3wc4VayD/JJwIEKCkmOXCbFEWN3VwzldIxkWug=
APP_DEBUG=false
APP_URL=https://dial4doughdev-production.up.railway.app

LOG_CHANNEL=stack
LOG_LEVEL=error

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Wix Configuration
WIX_APP_ID=3cc2de07-3a6c-4542-b7b3-e92721c6df8a
WIX_APP_SECRET=91b427ed-cf50-447e-a391-0d7337be5007
WIX_APP_URL=https://dial4doughdev-production.up.railway.app
```

## Deployment Steps

1. **Commit and push the changes**:

    ```bash
    git add wait-for-db.sh config/database.php
    git commit -m "Fix database connection issues in production"
    git push origin main
    ```

2. **Railway will automatically redeploy** when it detects the push

3. **Monitor the deployment logs** in Railway to confirm:
    - ✅ Database connection successful
    - ✅ Migrations run (or "Nothing to migrate")
    - ✅ Server running on port 8080
    - ✅ No "Connection refused" or URL parsing errors

4. **Test the site** at https://dial4doughdev-production.up.railway.app

## What Was Fixed

### wait-for-db.sh Improvements:

1. **Config cache clearing** - Ensures fresh env vars are always used
2. **Priority logic** - Individual DB\_\* vars now take precedence over DATABASE_URL
3. **Validation** - Fails fast if DB_HOST contains a URL or required vars are missing
4. **Explicit unsetting** - Removes DATABASE_URL/MYSQL_URL to prevent Laravel confusion

### config/database.php:

1. Changed default connection from 'sqlite' to 'mysql' for production environments

## Troubleshooting

### If you still see "Connection refused" errors:

- Check that all DB\_\* variables are set in Railway (not just in .env.production)
- The .env.production file is NOT used by Railway - it only uses environment variables from the project settings

### If you see "DB_HOST appears to be a full URL" error:

- Railway is setting DB_HOST to a full connection string
- Check your MySQL service configuration in Railway
- You may need to manually set the individual variables as shown above

### If migrations fail with "Table already exists":

- This is a warning but startup continues
- The tables already exist in production, which is normal
- The startup script handles this gracefully

## Verification Checklist

After deployment, verify:

- [ ] Site loads without 500 errors
- [ ] Login/registration works
- [ ] Database operations work correctly
- [ ] Logs show successful database connection
- [ ] No URL parsing errors in startup logs

## Support

If issues persist, check:

1. Railway deployment logs for startup errors
2. Laravel logs in Railway: `php artisan log:show` or check storage/logs
3. Ensure MySQL service is running in Railway
4. Verify network connectivity between app and database services
