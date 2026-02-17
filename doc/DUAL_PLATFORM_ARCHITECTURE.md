# Dual-Platform Architecture Guide

**U9itus – Political Loyalty Ads**  
**Version:** 2.0.0  
**Date:** February 16, 2026

## Overview

U9itus supports **two deployment modes** from a single codebase:

1. **Wix App Extension** — Integrated into Wix marketplace for their 200M+ users
2. **Standalone Application** — Direct deployment for enterprise clients and white-label solutions

## Architecture Philosophy

### Shared Core

Both platforms share:

- ✅ **Backend API** ([routes/api.php](../routes/api.php))
- ✅ **Business Logic** ([app/Services/](../app/Services/))
- ✅ **Database Models** ([app/Models/](../app/Models/))
- ✅ **Job Queue & Workers** ([app/Jobs/](../app/Jobs/))
- ✅ **Payment Processing** (Stripe, PayPal)
- ✅ **Fraud Detection System**
- ✅ **Token-Based Ad Delivery**

### Platform-Specific Components

#### Wix Platform

- **Routes:** [routes/wix.php](../routes/wix.php)
- **Controllers:** `app/Http/Controllers/Wix/`
- **Views:** `resources/views/wix/`
- **Authentication:** Wix OAuth
- **Member Management:** Wix Members API
- **Notifications:** Wix notification APIs
- **Frontend SDK:** `@wix/sdk`, `@wix/dashboard`

#### Standalone Platform

- **Routes:** `routes/standalone.php` (to be created)
- **Controllers:** `app/Http/Controllers/Standalone/`
- **Views:** `resources/views/standalone/`
- **Authentication:** Laravel Breeze/Fortify
- **Member Management:** Native Laravel auth
- **Notifications:** Twilio (SMS), Firebase (Push), Laravel Mail
- **Frontend:** Vue.js/React + Tailwind CSS

## Platform Detection

### Environment Configuration

```env
# Platform Mode: 'wix' | 'standalone' | 'dual'
PLATFORM_MODE=dual

# Wix Configuration (only needed for Wix mode)
WIX_APP_ID=your-wix-app-id
WIX_APP_SECRET=your-wix-app-secret

# Standalone Configuration
APP_URL=https://u9itus.com
FRONTEND_URL=https://app.u9itus.com
```

### Config File: `config/platform.php`

```php
<?php

return [
    'mode' => env('PLATFORM_MODE', 'dual'), // wix, standalone, dual

    'wix' => [
        'enabled' => env('PLATFORM_MODE') !== 'standalone',
        'app_id' => env('WIX_APP_ID'),
        'app_secret' => env('WIX_APP_SECRET'),
    ],

    'standalone' => [
        'enabled' => env('PLATFORM_MODE') !== 'wix',
        'frontend_url' => env('FRONTEND_URL', env('APP_URL')),
        'api_url' => env('APP_URL') . '/api',
    ],
];
```

## Service Abstraction Layer

### Notification Service Interface

Create platform-agnostic notification service:

```php
// app/Contracts/NotificationServiceInterface.php
interface NotificationServiceInterface {
    public function sendEmail(User $user, string $subject, string $message);
    public function sendSMS(User $user, string $message);
    public function sendPush(User $user, string $title, string $message);
}

// app/Services/WixNotificationService.php - Wix implementation
// app/Services/StandardNotificationService.php - Standalone implementation
```

### Authentication Service Interface

```php
// app/Contracts/AuthServiceInterface.php
interface AuthServiceInterface {
    public function authenticate(Request $request): User;
    public function register(array $data): User;
    public function logout(Request $request): void;
}

// app/Services/WixAuthService.php - Wix OAuth
// app/Services/StandardAuthService.php - Laravel Sanctum/Fortify
```

## Routing Strategy

### routes/web.php

```php
<?php

use Illuminate\Support\Facades\Route;

// Load platform-specific routes based on configuration
if (config('platform.wix.enabled')) {
    require __DIR__.'/wix.php';
}

if (config('platform.standalone.enabled')) {
    require __DIR__.'/standalone.php';
}

// Shared routes (landing page, legal docs, etc.)
Route::get('/', fn() => view('welcome'));
Route::get('/privacy', fn() => view('legal.privacy'));
Route::get('/terms', fn() => view('legal.terms'));
```

### routes/standalone.php (NEW)

```php
<?php

use App\Http\Controllers\Standalone\AuthController;
use App\Http\Controllers\Standalone\DashboardController;
use App\Http\Controllers\Standalone\PoliticianController;
use App\Http\Controllers\Standalone\VoterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Standalone Application Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Dashboard Routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Main Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Politician Dashboard
    Route::prefix('politician')->name('politician.')->group(function () {
        Route::get('/dashboard', [PoliticianController::class, 'dashboard']);
        Route::get('/campaigns', [PoliticianController::class, 'campaigns']);
        Route::get('/create-campaign', [PoliticianController::class, 'createCampaign']);
        Route::post('/campaigns', [PoliticianController::class, 'storeCampaign']);
        Route::get('/analytics', [PoliticianController::class, 'analytics']);
        Route::get('/billing', [PoliticianController::class, 'billing']);
    });

    // Voter Dashboard
    Route::prefix('voter')->name('voter.')->group(function () {
        Route::get('/dashboard', [VoterController::class, 'dashboard']);
        Route::get('/watch/{token}', [VoterController::class, 'watch']);
        Route::post('/watch/{token}/complete', [VoterController::class, 'markComplete']);
        Route::get('/earnings', [VoterController::class, 'earnings']);
        Route::get('/referrals', [VoterController::class, 'referrals']);
    });
});
```

## Deployment Strategy

### Option 1: Single Instance (Dual Mode)

Run one application instance with `PLATFORM_MODE=dual`:

- **URL Structure:**
    - Wix routes: `https://u9itus.app/wix/*`
    - Standalone routes: `https://u9itus.app/*`
    - API: `https://u9itus.app/api/*`

### Option 2: Separate Instances

Run two separate deployments:

- **Wix Instance:** `PLATFORM_MODE=wix` → `https://wix.u9itus.app`
- **Standalone Instance:** `PLATFORM_MODE=standalone` → `https://app.u9itus.app`
- **Shared Database:** Both connect to same DB for unified analytics

### Option 3: Microservices (Future)

- API service (shared)
- Wix frontend service
- Standalone frontend service
- Each can scale independently

## Database Considerations

### Shared Schema

Users from both platforms share the same `users` table with a `platform` field:

```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('platform', ['wix', 'standalone'])->default('standalone');
    $table->string('wix_instance_id')->nullable(); // For Wix users
});
```

### Platform-Specific Data

- **Wix users:** Store `wix_instance_id`, `wix_member_id`
- **Standalone users:** Standard Laravel auth fields

## Frontend Strategy

### Wix Platform

- Embedded in Wix Dashboard iframes
- Uses Wix Design System components
- Limited customization (follows Wix branding)

### Standalone Platform

- Full-Featured SPA (Vue.js/React)
- Complete branding control
- Advanced features not available in Wix
- PWA support for mobile apps

**Recommended Stack:**

- **Frontend:** Vue 3 + Vite + Pinia
- **UI:** Tailwind CSS + HeadlessUI
- **Build:** Vite (already configured)
- **Real-time:** Laravel Reverb/Pusher

## Migration Path

### Phase 1: Setup Standalone Routes ✅ (Current)

- Create `routes/standalone.php`
- Create standalone controllers
- Add platform configuration

### Phase 2: Authentication System

- Install Laravel Breeze or Fortify
- Create standalone auth views
- Implement service abstraction

### Phase 3: Dashboard UI

- Build standalone dashboard layouts
- Implement politician/voter dashboards
- Create video player component

### Phase 4: Notification Services

- Create notification interface
- Implement Twilio integration (SMS)
- Implement Firebase (Push notifications)
- Setup Laravel Mail

### Phase 5: Testing & Polish

- E2E tests for both platforms
- Performance optimization
- Security audit
- Documentation

## Development Workflow

### Running Locally

**Wix Mode:**

```bash
PLATFORM_MODE=wix php artisan serve
```

**Standalone Mode:**

```bash
PLATFORM_MODE=standalone php artisan serve
npm run dev # For frontend assets
```

**Dual Mode (both active):**

```bash
PLATFORM_MODE=dual php artisan serve
npm run dev
```

### Testing Both Platforms

```bash
# Test Wix routes
php artisan route:list --path=wix

# Test standalone routes
php artisan route:list --path=dashboard

# Run tests for specific platform
php artisan test --filter=StandaloneTest
php artisan test --filter=WixTest
```

## Security Considerations

### Authentication

- **Wix:** Instance verification via JWT (existing)
- **Standalone:** Laravel Sanctum for API, Session for web

### CSRF Protection

- **Wix routes:** Excluded (iframe limitations)
- **Standalone routes:** Full CSRF protection enabled

### API Rate Limiting

- Shared rate limiter for both platforms
- Platform-specific limits configurable

## Benefits of Dual-Platform

### Business

✅ **Market Reach:** Wix marketplace + direct sales  
✅ **Revenue Diversification:** Marketplace fees vs direct pricing  
✅ **White-Label:** Offer branded versions to enterprise  
✅ **Data Insights:** Cross-platform analytics

### Technical

✅ **Code Reuse:** 80%+ shared codebase  
✅ **Faster Development:** Build features once  
✅ **Unified Database:** Single source of truth  
✅ **Easier Maintenance:** Fix bugs in one place

### User Experience

✅ **Wix Users:** Seamless integration with their site  
✅ **Enterprise Users:** Full control and customization  
✅ **Data Portability:** Users can migrate between platforms

## Next Steps

1. ✅ Create this architecture document
2. ⏳ Install Laravel Breeze for standalone auth
3. ⏳ Create standalone routes and controllers
4. ⏳ Build standalone dashboard views
5. ⏳ Implement notification service abstraction
6. ⏳ Create platform switcher middleware
7. ⏳ Update deployment documentation
8. ⏳ Setup CI/CD for both platforms

## Resources

- [Laravel Breeze Documentation](https://laravel.com/docs/11.x/starter-kits#laravel-breeze)
- [Wix App Extension Guide](https://dev.wix.com/docs/build-apps)
- [Service Container in Laravel](https://laravel.com/docs/11.x/container)
- [Twilio SMS Integration](https://www.twilio.com/docs/sms)

---

**Questions?** Contact the development team or check [DEVELOPMENT.md](DEVELOPMENT.md)
