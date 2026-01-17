# Dial4Dough MVP - Loyalty Viewers Platform

**Version:** 1.0.0 (MVP)  
**Framework:** Laravel 11  
**Database:** SQLite (upgradeable to PostgreSQL)

## Overview

Dial4Dough is a Loyalty Viewers platform where advertisers pay viewers to watch video ads. The platform is controlled by Head Enterprises (admin) who manually or automatically assign ads to viewers.

## Key Features

### Core Business Model
- **One Assignment Per Viewer**: Viewers can only have ONE active ad assignment at a time
- **80% Watch Requirement**: Viewers must watch at least 80% of the video to get paid
- **Admin-Controlled Assignments**: Admins manually or automatically assign ads to viewers
- **One View Per Campaign**: Each viewer can only watch each campaign once
- **24-Hour Expiration**: Assignments expire after 24 hours if not completed

### User Roles
1. **Admin** - Manages assignments, approves campaigns, tracks completion
2. **Advertiser** - Uploads video ads, pays via Stripe, views campaign analytics
3. **Viewer** - Watches assigned ads, earns money, receives payouts

### Technical Stack
- **Backend**: Laravel 11 (PHP 8.1+)
- **Database**: SQLite (MVP) / PostgreSQL (Production)
- **Authentication**: Laravel built-in auth with role-based access
- **Permissions**: Spatie Laravel Permission
- **Payments**: Laravel Cashier (Stripe integration)
- **Frontend**: Bootstrap 5 + jQuery
- **Testing**: Pest

## Quick Start

### Requirements
- PHP 8.1 or higher
- Composer
- SQLite3
- Node.js & NPM (for frontend assets)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/jhead12/dial4dough.dev.git
cd dial4dough.dev
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup**
```bash
touch database/database.sqlite
php artisan migrate --seed
```

5. **Build frontend assets**
```bash
npm run build
```

6. **Start development server**
```bash
php artisan serve
```

7. **Access the application**
- URL: http://localhost:8000
- Admin: admin@dial4dough.com / password

## Default Credentials

After running the seeders, you can log in with:

**Admin Account:**
- Email: admin@dial4dough.com
- Password: password

*Change these credentials immediately in production!*

## Application Structure

### Database Schema

**Users** - Extended with user_type, KYC status, assignment tracking  
**Advertisers** - Company info, Stripe integration, budgets  
**Loyalty Viewers** - Demographics, payment preferences, earnings  
**Campaigns** - Video ads with targeting, budget, status  
**Ad Assignments** - Core feature linking campaigns to viewers  

### Services

- **AdminAssignmentService** - Assignment logic and validation
- **ViewTrackingService** - Watch time tracking and completion
- **PaymentService** - Stripe charges and viewer payouts

### Console Commands

```bash
# Handle expired assignments (runs hourly)
php artisan assignments:handle-expired

# Process viewer payouts (runs daily)
php artisan payouts:process-viewer
```

## Development Workflow

### Running Tests
```bash
php artisan test
```

### Code Style
```bash
./vendor/bin/pint
```

### Database Management
```bash
# Fresh migration
php artisan migrate:fresh --seed

# Reset specific table
php artisan migrate:rollback --step=1

# Check migration status
php artisan migrate:status
```

## API Endpoints

### Admin Routes
- `GET /admin/assignments` - Assignment dashboard
- `POST /admin/assign-ad` - Manually assign ad
- `POST /admin/auto-assign` - Auto-assign ads

### Advertiser Routes
- `GET /advertiser/dashboard` - Overview
- `GET /advertiser/campaigns` - List campaigns
- `POST /advertiser/campaigns` - Create campaign
- `GET /advertiser/campaigns/{id}` - Campaign analytics

### Viewer Routes
- `GET /viewer/dashboard` - Current assignment & earnings
- `GET /viewer/watch/{assignment}` - Watch video
- `POST /viewer/complete/{assignment}` - Mark as completed

## Configuration

Key configuration values in `config/dial4dough.php`:

```php
'head_enterprises_fee_percent' => 15.0,  // Platform fee
'assignment_expiry_hours' => 24,         // Assignment lifetime
'min_watch_time_percent' => 80,          // Minimum watch time
'min_payout_amount' => 25.00,            // Minimum payout threshold
'default_payment_per_view' => 1.00,      // Default payment
```

## Security

- Role-based access control via Spatie Permission
- Policy-based authorization for campaigns and assignments
- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- XSS protection via Blade templating

## Known Limitations (MVP)

- SQLite database (upgrade to PostgreSQL for production)
- Local file storage (upgrade to S3 for production)
- Stripe test mode only
- Manual PayPal payouts (no API integration)
- No real-time features
- No blockchain verification
- Basic fraud detection

## Future Enhancements

- Real-time notifications via WebSockets
- Advanced fraud detection
- Blockchain verification
- Advanced analytics dashboard
- Mobile application
- Multi-language support
- Video streaming optimization

## Support

For issues and questions:
- GitHub Issues: https://github.com/jhead12/dial4dough.dev/issues
- Documentation: See INSTALLATION.md for detailed setup guide

## License

MIT License - See LICENSE file for details

## Credits

Developed by Head Enterprises
