<?php

/**
 * Standalone Application Routes
 * 
 * These routes are for the U9itus standalone application,
 * powered by Laravel 12.
 * 
 * Framework: Laravel 12 (Standalone Architecture)
 */

use App\Http\Controllers\Standalone\AuthController;
use App\Http\Controllers\Standalone\DashboardController;
use App\Http\Controllers\Standalone\PoliticianController;
use App\Http\Controllers\Standalone\VoterController;
use App\Http\Controllers\Standalone\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Routes (Authentication)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // Shared login (redirects by role after authentication)
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Admin-specific login portal
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login.submit');

    // Registration — role chooser landing
    Route::get('/register', [AuthController::class, 'showRegisterChoose'])->name('register');

    // Politician registration
    Route::get('/register/politician', [AuthController::class, 'showRegisterPolitician'])->name('register.politician');
    Route::post('/register/politician', [AuthController::class, 'registerPolitician'])->name('register.politician.submit');

    // Voter registration
    Route::get('/register/voter', [AuthController::class, 'showRegisterVoter'])->name('register.voter');
    Route::post('/register/voter', [AuthController::class, 'registerVoter'])->name('register.voter.submit');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Logout (authenticated users only)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Protected Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    
    // Main Dashboard (role-based redirect)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    /*
    |--------------------------------------------------------------------------
    | Politician Dashboard & Campaign Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('politician')->name('politician.')->middleware('role:politician')->group(function () {
        Route::get('/dashboard', [PoliticianController::class, 'dashboard'])->name('dashboard');
        
        // Campaign Management
        Route::get('/campaigns', [PoliticianController::class, 'campaigns'])->name('campaigns.index');
        Route::get('/campaigns/create', [PoliticianController::class, 'createCampaign'])->name('campaigns.create');
        Route::post('/campaigns', [PoliticianController::class, 'storeCampaign'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [PoliticianController::class, 'showCampaign'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/edit', [PoliticianController::class, 'editCampaign'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [PoliticianController::class, 'updateCampaign'])->name('campaigns.update');
        Route::delete('/campaigns/{campaign}', [PoliticianController::class, 'destroyCampaign'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/pause', [PoliticianController::class, 'pauseCampaign'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [PoliticianController::class, 'resumeCampaign'])->name('campaigns.resume');
        Route::post('/campaigns/{campaign}/submit-review', [PoliticianController::class, 'submitForReview'])->name('campaigns.submit-review');
        
        // Video Upload
        Route::post('/campaigns/{campaign}/upload-video', [PoliticianController::class, 'uploadVideo'])->name('campaigns.upload-video');
        
        // Analytics & Reports
        Route::get('/analytics', [PoliticianController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/{campaign}', [PoliticianController::class, 'campaignAnalytics'])->name('analytics.campaign');
        
        // Billing & Payments
        Route::get('/billing', [PoliticianController::class, 'billing'])->name('billing');
        Route::post('/billing/add-funds', [PoliticianController::class, 'addFunds'])->name('billing.add-funds');
        Route::get('/billing/invoices', [PoliticianController::class, 'invoices'])->name('billing.invoices');
        
        // Profile & Settings
        Route::get('/profile', [PoliticianController::class, 'profile'])->name('profile');
        Route::put('/profile', [PoliticianController::class, 'updateProfile'])->name('profile.update');

        // Referrals
        Route::get('/referrals', [PoliticianController::class, 'referrals'])->name('referrals');

        // KYC Document Upload
        Route::post('/kyc/upload', [PoliticianController::class, 'uploadKycDocument'])->name('kyc.upload');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Voter Dashboard & Earnings
    |--------------------------------------------------------------------------
    */
    Route::prefix('voter')->name('voter.')->middleware('role:voter')->group(function () {
        Route::get('/dashboard', [VoterController::class, 'dashboard'])->name('dashboard');

        // ── Ad Viewing Room ──────────────────────────────────────────────────
        // Browse available campaigns and self-select one to watch.
        Route::get('/ad-room', [VoterController::class, 'adRoom'])->name('ad-room');
        // Claim a campaign from the Ad Viewing Room → mints a token → redirects to watch page.
        Route::post('/campaigns/{campaign}/claim', [VoterController::class, 'claimCampaign'])->name('campaigns.claim');

        // Watch Ad (secure token-based)
        Route::get('/watch/{token}', [VoterController::class, 'watch'])->name('watch');
        Route::post('/watch/{token}/start', [VoterController::class, 'startWatching'])->name('watch.start');
        // Heartbeat & completion use session UUID (not token)
        Route::post('/session/{sessionUuid}/progress', [VoterController::class, 'progressHeartbeat'])->name('session.progress');
        Route::post('/session/{sessionUuid}/complete', [VoterController::class, 'markComplete'])->name('session.complete');
        
        // Earnings & Payouts
        Route::get('/earnings', [VoterController::class, 'earnings'])->name('earnings');
        Route::get('/earnings/history', [VoterController::class, 'earningsHistory'])->name('earnings.history');
        Route::post('/earnings/request-payout', [VoterController::class, 'requestPayout'])->name('earnings.payout');
        
        // Referrals
        Route::get('/referrals', [VoterController::class, 'referrals'])->name('referrals');
        Route::get('/referrals/link', [VoterController::class, 'getReferralLink'])->name('referrals.link');
        
        // Preferences
        Route::get('/preferences', [VoterController::class, 'preferences'])->name('preferences');
        Route::put('/preferences', [VoterController::class, 'updatePreferences'])->name('preferences.update');
        
        // Profile
        Route::get('/profile', [VoterController::class, 'profile'])->name('profile');
        Route::put('/profile', [VoterController::class, 'updateProfile'])->name('profile.update');

        // KYC Document Upload
        Route::post('/kyc/upload', [VoterController::class, 'uploadKycDocument'])->name('kyc.upload');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard & Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        
        // Campaign Approval
        Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
        Route::post('/campaigns/{campaign}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
        Route::post('/campaigns/{campaign}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');

        // Campaign Editing (admin can edit any campaign)
        Route::get('/campaigns/{campaign}/edit', [AdminController::class, 'editCampaign'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [AdminController::class, 'updateCampaign'])->name('campaigns.update');
        
        // User Management
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('users.show');
        Route::put('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::put('/users/{user}/unsuspend', [AdminController::class, 'unsuspendUser'])->name('users.unsuspend');
        
        // Fraud Detection
        Route::get('/fraud', [AdminController::class, 'fraud'])->name('fraud.index');
        Route::get('/fraud/flagged-views', [AdminController::class, 'flaggedViews'])->name('fraud.views');
        Route::post('/fraud/views/{view}/review', [AdminController::class, 'reviewView'])->name('fraud.review');
        Route::post('/fraud/voters/{voter}/clear-flag', [AdminController::class, 'clearVoterFraud'])->name('fraud.clear-voter');

        // KYC Management
        Route::get('/kyc', [AdminController::class, 'kycQueue'])->name('kyc.index');
        Route::post('/kyc/{user}/approve', [AdminController::class, 'approveKyc'])->name('kyc.approve');
        Route::post('/kyc/{user}/reject', [AdminController::class, 'rejectKyc'])->name('kyc.reject');
        
        // Payouts Management
        Route::get('/payouts', [AdminController::class, 'payouts'])->name('payouts.index');
        Route::get('/payouts/pending', [AdminController::class, 'pendingPayouts'])->name('payouts.pending');
        Route::post('/payouts/batch-process', [AdminController::class, 'processBatchPayouts'])->name('payouts.batch');
        
        // Analytics & Reports
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/reports/revenue', [AdminController::class, 'revenueReport'])->name('reports.revenue');
        Route::get('/reports/engagement', [AdminController::class, 'engagementReport'])->name('reports.engagement');
        
        // System Settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/test-email', [AdminController::class, 'testEmail'])->name('settings.test-email');

        // Email Template Management (Phase 7b)
        Route::get('/email-templates', [AdminController::class, 'emailTemplates'])->name('email-templates.index');
        Route::get('/email-templates/{template}/edit', [AdminController::class, 'editEmailTemplate'])->name('email-templates.edit');
        Route::put('/email-templates/{template}', [AdminController::class, 'updateEmailTemplate'])->name('email-templates.update');
        Route::patch('/email-templates/{template}/toggle', [AdminController::class, 'toggleEmailTemplate'])->name('email-templates.toggle');
        Route::get('/email-templates/{template}/preview', [AdminController::class, 'previewEmailTemplate'])->name('email-templates.preview');
    });
});

/*
|--------------------------------------------------------------------------
| Public Pages (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::get('/about', fn() => view('standalone.about'))->name('about');
Route::get('/how-it-works', fn() => view('standalone.how-it-works'))->name('how-it-works');
Route::get('/pricing', fn() => view('standalone.pricing'))->name('pricing');
Route::get('/contact', fn() => view('standalone.contact'))->name('contact');
Route::post('/contact', [DashboardController::class, 'submitContact'])->name('contact.submit');
