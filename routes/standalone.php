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
use App\Http\Controllers\Standalone\AdminOfficeProfileController;
use App\Http\Controllers\Standalone\PublicProfileController;
use App\Http\Controllers\Standalone\ProfileClaimController;
use App\Http\Controllers\Standalone\VoterOnboardingController;
use App\Http\Controllers\Standalone\PoliticianOnboardingController;
use App\Http\Controllers\Standalone\AdminOnboardingController;
use App\Http\Controllers\Standalone\IdmeController;
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

    // Registration closed — mailing list capture (always accessible regardless of registration_open flag)
    Route::get('/register/closed', [AuthController::class, 'showRegisterClosed'])->name('register.closed');
    Route::post('/register/closed', [AuthController::class, 'storeMailingListSubscriber'])->name('register.mailing-list.store');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Logout (authenticated users only)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Phone verification (authenticated only)
Route::middleware('auth')->group(function () {
    Route::get('/verify-phone', [AuthController::class, 'showVerifyPhone'])->name('phone.verify');
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone'])->name('phone.verify.submit');
    Route::post('/resend-phone-code', [AuthController::class, 'resendPhoneCode'])->name('phone.resend');
});

/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'no.cache'])->group(function () {
    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Phase 16: Profile Verification (Public Route)
|--------------------------------------------------------------------------
*/

// Public verification link from email (no auth required)
Route::get('/politician/verify/{token}', [PoliticianController::class, 'verifyProfile'])->name('politician.verify-profile');

/*
|--------------------------------------------------------------------------
| Onboarding Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'no.cache'])->group(function () {
    
    // Voter Onboarding
    Route::prefix('voter/onboarding')->name('voter.onboarding.')->group(function () {
        Route::get('/welcome', [VoterOnboardingController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [VoterOnboardingController::class, 'completeWelcome'])->name('complete-welcome');
        
        Route::get('/profile', [VoterOnboardingController::class, 'profileSetup'])->name('profile');
        Route::post('/profile', [VoterOnboardingController::class, 'completeProfileSetup'])->name('complete-profile');
        
        Route::get('/first-watch', [VoterOnboardingController::class, 'firstWatch'])->name('first-watch');
        Route::post('/first-watch', [VoterOnboardingController::class, 'completeFirstWatch'])->name('complete-first-watch');
        
        Route::get('/payout', [VoterOnboardingController::class, 'payoutSetup'])->name('payout');
        Route::post('/payout', [VoterOnboardingController::class, 'completePayoutSetup'])->name('complete-payout');
        
        Route::get('/referrals', [VoterOnboardingController::class, 'referralSetup'])->name('referrals');
        Route::post('/referrals', [VoterOnboardingController::class, 'completeReferralSetup'])->name('complete-referrals');
        
        Route::post('/skip', [VoterOnboardingController::class, 'skip'])->name('skip');
    });

    // Politician Onboarding
    Route::prefix('politician/onboarding')->name('politician.onboarding.')->group(function () {
        Route::get('/welcome', [PoliticianOnboardingController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [PoliticianOnboardingController::class, 'completeWelcome'])->name('complete-welcome');
        
        Route::get('/profile', [PoliticianOnboardingController::class, 'politicalProfile'])->name('profile');
        Route::post('/profile', [PoliticianOnboardingController::class, 'completePoliticalProfile'])->name('complete-profile');
        
        Route::get('/payment', [PoliticianOnboardingController::class, 'paymentMethod'])->name('payment');
        Route::post('/payment', [PoliticianOnboardingController::class, 'completePaymentMethod'])->name('complete-payment');
        
        Route::get('/campaign', [PoliticianOnboardingController::class, 'firstCampaign'])->name('campaign');
        Route::post('/campaign', [PoliticianOnboardingController::class, 'completeFirstCampaign'])->name('complete-campaign');
        
        Route::get('/credits', [PoliticianOnboardingController::class, 'addCredits'])->name('credits');
        Route::post('/credits', [PoliticianOnboardingController::class, 'completeAddCredits'])->name('complete-credits');
        
        Route::post('/skip', [PoliticianOnboardingController::class, 'skip'])->name('skip');
    });

    // Admin Onboarding
    Route::prefix('admin/onboarding')->name('admin.onboarding.')->group(function () {
        Route::get('/welcome', [AdminOnboardingController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [AdminOnboardingController::class, 'completeWelcome'])->name('complete-welcome');
        
        Route::get('/campaigns', [AdminOnboardingController::class, 'campaignApproval'])->name('campaigns');
        Route::post('/campaigns', [AdminOnboardingController::class, 'completeCampaignApproval'])->name('complete-campaigns');
        
        Route::get('/fraud', [AdminOnboardingController::class, 'fraudManagement'])->name('fraud');
        Route::post('/fraud', [AdminOnboardingController::class, 'completeFraudManagement'])->name('complete-fraud');
        
        Route::get('/payouts', [AdminOnboardingController::class, 'payoutProcessing'])->name('payouts');
        Route::post('/payouts', [AdminOnboardingController::class, 'completePayoutProcessing'])->name('complete-payouts');
        
        Route::post('/skip', [AdminOnboardingController::class, 'skip'])->name('skip');
    });
});

/*
|--------------------------------------------------------------------------
| Protected Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'check.role', 'no.cache'])->group(function () {

    // Id.me identity verification
    Route::prefix('verification/idme')->name('verification.idme.')->group(function () {
        Route::get('/redirect', [IdmeController::class, 'redirectToProvider'])->name('redirect');
        Route::get('/callback', [IdmeController::class, 'handleCallback'])->name('callback');
        Route::get('/status', [IdmeController::class, 'status'])->name('status');
    });

    // Admin security routes (kept outside onboarding + enforcement middleware
    // so admins can complete TOTP setup/challenge when required).
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {
        Route::get('/2fa/challenge', [AuthController::class, 'showAdminTwoFactorChallenge'])->name('2fa.challenge');
        Route::post('/2fa/challenge', [AuthController::class, 'verifyAdminTwoFactorChallenge'])->name('2fa.challenge.verify');

        Route::get('/security/2fa', [AdminController::class, 'twoFactorSetup'])->name('2fa.setup');
        Route::post('/security/2fa/enable', [AdminController::class, 'enableTwoFactor'])->name('2fa.setup.enable');
        Route::post('/security/2fa/disable', [AdminController::class, 'disableTwoFactor'])->name('2fa.setup.disable');
        Route::post('/security/2fa/recovery-codes/rotate', [AdminController::class, 'rotateRecoveryCodes'])->name('2fa.setup.recovery.rotate');
    });
    
    // Main Dashboard (role-based redirect)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    /*
    |--------------------------------------------------------------------------
    | Politician Dashboard & Campaign Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('politician')->name('politician.')->middleware(['role:politician', 'check.politician.onboarding'])->group(function () {
        Route::get('/dashboard', [PoliticianController::class, 'dashboard'])->name('dashboard');
        
        // Campaign Management
        Route::get('/campaigns', [PoliticianController::class, 'campaigns'])->name('campaigns.index');
        Route::get('/campaigns/create', [PoliticianController::class, 'createCampaign'])->name('campaigns.create');
        Route::post('/campaigns', [PoliticianController::class, 'storeCampaign'])->name('campaigns.store');
        Route::post('/campaigns/save-draft', [PoliticianController::class, 'saveDraft'])->name('campaigns.save-draft');
        Route::get('/campaigns/{campaign}', [PoliticianController::class, 'showCampaign'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/edit', [PoliticianController::class, 'editCampaign'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [PoliticianController::class, 'updateCampaign'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/questions/{report}/reply', [PoliticianController::class, 'replyToQuestion'])
            ->name('campaigns.questions.reply');
        Route::get('/campaigns/{campaign}/questions', [PoliticianController::class, 'campaignQuestions'])
            ->name('campaigns.questions.index');
        Route::delete('/campaigns/{campaign}', [PoliticianController::class, 'destroyCampaign'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/pause', [PoliticianController::class, 'pauseCampaign'])->name('campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [PoliticianController::class, 'resumeCampaign'])->name('campaigns.resume');
        Route::post('/campaigns/{campaign}/submit-review', [PoliticianController::class, 'submitForReview'])->name('campaigns.submit-review');
        
        // Video Upload
        Route::post('/campaigns/{campaign}/upload-video', [PoliticianController::class, 'uploadVideo'])->name('campaigns.upload-video');
        
        // S3 Direct Upload (for large files with background transcoding)
        Route::post('/campaigns/{campaign}/s3-upload-url', [PoliticianController::class, 'getS3UploadUrl'])->name('campaigns.s3-upload-url');
        Route::post('/campaigns/{campaign}/process-s3-video', [PoliticianController::class, 'processS3UploadedVideo'])->name('campaigns.process-s3-video');
        
        // Analytics & Reports
        Route::get('/analytics', [PoliticianController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/{campaign}', [PoliticianController::class, 'campaignAnalytics'])->name('analytics.campaign');
        
        // Billing & Payments
        Route::get('/billing', [PoliticianController::class, 'billing'])->name('billing');
        Route::post('/billing/add-funds', [PoliticianController::class, 'addFunds'])->name('billing.add-funds');
        Route::get('/billing/confirm', [PoliticianController::class, 'confirmPayment'])->name('billing.confirm');
        Route::post('/billing/update-receipt-email', [PoliticianController::class, 'updateReceiptEmail'])->name('billing.update-receipt-email');
        Route::get('/billing/invoices', [PoliticianController::class, 'invoices'])->name('billing.invoices');
        Route::get('/billing/invoices/{transaction}/details', [PoliticianController::class, 'invoiceDetails'])
            ->name('billing.invoices.details');
        Route::post('/billing/invoices/{transaction}/send-receipt', [PoliticianController::class, 'sendReceipt'])
            ->name('billing.invoices.send-receipt');
            Route::post('/billing/setup-intent', [PoliticianController::class, 'createSetupIntent'])->name('billing.setup-intent');
            Route::post('/billing/payment-methods', [PoliticianController::class, 'storePaymentMethod'])->name('billing.payment-methods.store');
            Route::delete('/billing/payment-methods/{paymentMethod}', [PoliticianController::class, 'deletePaymentMethod'])->name('billing.payment-methods.delete');
        
        // Profile & Settings
        Route::get('/profile', [PoliticianController::class, 'profile'])->name('profile');
        Route::put('/profile', [PoliticianController::class, 'updateProfile'])->name('profile.update');

        // Referrals
        Route::get('/referrals', [PoliticianController::class, 'referrals'])->name('referrals');

        // KYC Document Upload
        Route::post('/kyc/upload', [PoliticianController::class, 'uploadKycDocument'])->name('kyc.upload');
        Route::get('/kyc/document', [PoliticianController::class, 'viewKycDocument'])->name('kyc.view');

        // Phase 13 — Public Profile Page Management
        Route::get('/public-page', [PoliticianController::class, 'publicPage'])->name('public-page');
        Route::put('/public-page', [PoliticianController::class, 'updatePublicPage'])->name('public-page.update');

        // Phase 13 — Platform Initiatives (CRUD)
        Route::post('/initiatives', [PoliticianController::class, 'storeInitiative'])->name('initiatives.store');
        Route::put('/initiatives/{initiative}', [PoliticianController::class, 'updateInitiative'])->name('initiatives.update');
        Route::delete('/initiatives/{initiative}', [PoliticianController::class, 'destroyInitiative'])->name('initiatives.destroy');

        // Phase 16 — Transparency Settings & Profile Verification
        Route::get('/transparency-settings', [PoliticianController::class, 'transparencySettings'])->name('transparency-settings');
        Route::post('/transparency-settings/verify', [PoliticianController::class, 'initiateVerification'])->name('transparency-settings.verify');
        Route::put('/transparency-settings', [PoliticianController::class, 'updateTransparencySettings'])->name('transparency-settings.update');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Voter Dashboard & Earnings
    |--------------------------------------------------------------------------
    */
    Route::prefix('voter')->name('voter.')->middleware(['role:voter', 'check.voter.onboarding'])->group(function () {
        Route::get('/dashboard', [VoterController::class, 'dashboard'])->name('dashboard');

        // ── Ad Viewing Room ──────────────────────────────────────────────────
        // Browse available campaigns and self-select one to watch.
        Route::get('/ad-room', [VoterController::class, 'adRoom'])->name('ad-room');
        // Claim a campaign from the Ad Viewing Room → mints a token → redirects to watch page.
        Route::post('/campaigns/{campaign}/claim', [VoterController::class, 'claimCampaign'])->name('campaigns.claim');

        // Watch Ad (secure token-based)
        Route::get('/watch/{token}', [VoterController::class, 'watch'])->name('watch');
        Route::get('/watch/{token}/questions', [VoterController::class, 'watchQuestions'])->name('watch.questions');
        Route::post('/watch/{token}/start', [VoterController::class, 'startWatching'])->name('watch.start');
        // Heartbeat & completion use session UUID (not token)
        Route::post('/session/{sessionUuid}/progress', [VoterController::class, 'progressHeartbeat'])->name('session.progress');
        Route::post('/session/{sessionUuid}/complete', [VoterController::class, 'markComplete'])->name('session.complete');
        Route::post('/session/{sessionUuid}/survey', [VoterController::class, 'submitSurvey'])->name('session.survey');
        // In-watch interactions: error reporting + direct message to politician
        Route::post('/watch/{token}/report-issue', [VoterController::class, 'reportIssue'])->name('watch.report-issue');
        Route::post('/watch/{token}/ask-question', [VoterController::class, 'askQuestion'])->name('watch.ask-question');
        Route::post('/watch/{token}/message-politician', [VoterController::class, 'messagePolitician'])->name('watch.message-politician');
        
        // Earnings & Payouts
        Route::get('/earnings', [VoterController::class, 'earnings'])->name('earnings');
        Route::get('/earnings/history', [VoterController::class, 'earningsHistory'])->name('earnings.history');
        Route::post('/earnings/request-payout', [VoterController::class, 'requestPayout'])->name('earnings.payout');
        Route::get('/authentic-user-verifier/start', [VoterController::class, 'startAuthenticUserVerifier'])->name('authentic-user-verifier.start');
        
        // Referrals
        Route::get('/referrals', [VoterController::class, 'referrals'])->name('referrals');
        Route::get('/referrals/link', [VoterController::class, 'getReferralLink'])->name('referrals.link');
        
        // Preferences
        Route::get('/preferences', [VoterController::class, 'preferences'])->name('preferences');
        Route::put('/preferences', [VoterController::class, 'updatePreferences'])->name('preferences.update');
        
        // Profile
        Route::get('/profile', [VoterController::class, 'profile'])->name('profile');
        Route::put('/profile', [VoterController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [VoterController::class, 'updatePassword'])->name('profile.password.update');

        // KYC Document Upload
        Route::post('/kyc/upload', [VoterController::class, 'uploadKycDocument'])->name('kyc.upload');
        Route::get('/kyc/document', [VoterController::class, 'viewKycDocument'])->name('kyc.view');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard & Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware(['role:admin', 'check.admin.onboarding', 'admin.2fa'])->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        
        // Campaign Approval
        Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
        Route::get('/campaigns/running', [AdminController::class, 'runningCampaigns'])->name('campaigns.running');
        Route::post('/campaigns/bulk-action', [AdminController::class, 'bulkCampaignAction'])->name('campaigns.bulk-action');
        Route::post('/campaigns/{campaign}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
        Route::post('/campaigns/{campaign}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');

        // Campaign Editing (admin can edit any campaign)
        Route::get('/campaigns/{campaign}/edit', [AdminController::class, 'editCampaign'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [AdminController::class, 'updateCampaign'])->name('campaigns.update');

        // Campaign Stop / Reactivate
        Route::post('/campaigns/{campaign}/stop', [AdminController::class, 'stopCampaign'])->name('campaigns.stop');
        Route::post('/campaigns/{campaign}/reactivate', [AdminController::class, 'reactivateCampaign'])->name('campaigns.reactivate');

        // Campaign Audit Log
        Route::get('/campaigns/{campaign}/audit', [AdminController::class, 'campaignAuditLog'])->name('campaigns.audit');
        
        // User Management
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::post('/users/bulk-action', [AdminController::class, 'bulkUserAction'])->name('users.bulk-action');
        Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('users.show');
        Route::match(['PUT', 'POST'], '/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::match(['PUT', 'POST'], '/users/{user}/unsuspend', [AdminController::class, 'unsuspendUser'])->name('users.unsuspend');

        // Candidate Matching Review
        Route::get('/candidate-matches', [AdminController::class, 'candidateMatchReviews'])->name('candidate-matches.index');
        Route::post('/candidate-matches/{review}/approve', [AdminController::class, 'approveCandidateMatch'])->name('candidate-matches.approve');
        Route::post('/candidate-matches/{review}/reject', [AdminController::class, 'rejectCandidateMatch'])->name('candidate-matches.reject');
        Route::post('/candidate-matches/retry/{politician}', [AdminController::class, 'retryCandidateMatch'])->name('candidate-matches.retry');
        Route::post('/candidate-matches/import', [AdminController::class, 'importElectionCandidates'])->name('candidate-matches.import');
        
        // Fraud Detection
        Route::get('/fraud', [AdminController::class, 'fraud'])->name('fraud.index');
        Route::get('/fraud/flagged-views', [AdminController::class, 'flaggedViews'])->name('fraud.views');
        Route::post('/fraud/views/{view}/review', [AdminController::class, 'reviewView'])->name('fraud.review');
        Route::post('/fraud/voters/{voter}/clear-flag', [AdminController::class, 'clearVoterFraud'])->name('fraud.clear-voter');

        // KYC Management
        Route::get('/kyc', [AdminController::class, 'kycQueue'])->name('kyc.index');
        Route::post('/kyc/{user}/approve', [AdminController::class, 'approveKyc'])->name('kyc.approve');
        Route::post('/kyc/{user}/reject', [AdminController::class, 'rejectKyc'])->name('kyc.reject');
        Route::get('/kyc/{user}/document', [AdminController::class, 'viewKycDocument'])->name('kyc.view');
        
        // Payouts Management
        Route::get('/payouts', [AdminController::class, 'payouts'])->name('payouts.index');
        Route::get('/payouts/pending', [AdminController::class, 'pendingPayouts'])->name('payouts.pending');
        Route::get('/payouts/skipped', [AdminController::class, 'skippedPayouts'])->name('payouts.skipped');
        Route::post('/payouts/batch-process', [AdminController::class, 'processBatchPayouts'])->name('payouts.batch');
        Route::post('/payouts/skipped/{skippedItem}/force-pay', [AdminController::class, 'forcePayBelowMinimum'])
            ->name('payouts.skipped.force-pay');

        // Data Imports Monitoring (Sprint 2)
        Route::get('/imports', [AdminController::class, 'imports'])->name('imports');
        Route::post('/imports/unverified-profile', [AdminController::class, 'seedUnverifiedPoliticianProfile'])
            ->name('imports.unverified-profile.seed');
        Route::post('/imports/ocr-candidates', [AdminController::class, 'importCandidatesFromOcr'])
            ->name('imports.ocr-candidates');

        // Billing Refunds (unused politician credits only)
        Route::get('/billing/refunds', [AdminController::class, 'billingRefunds'])->name('billing.refunds');
        Route::post('/billing/transactions/{transaction}/refund-unused', [AdminController::class, 'refundUnusedCredits'])
            ->name('billing.refund-unused');
        
        // Analytics & Reports
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/district-searches', [AdminController::class, 'districtSearches'])->name('district-searches.index');
        Route::get('/district-searches/export', [AdminController::class, 'exportDistrictSearches'])->name('district-searches.export');
        Route::get('/analytics/export/campaign-accounting', [AdminController::class, 'exportCampaignAccounting'])->name('analytics.export.campaign-accounting');
        Route::get('/analytics/export/voter-accounting', [AdminController::class, 'exportVoterAccounting'])->name('analytics.export.voter-accounting');
        Route::get('/analytics/ledger/campaign', [AdminController::class, 'campaignAccountingLedger'])->name('analytics.ledger.campaign');
        Route::get('/analytics/ledger/voter', [AdminController::class, 'voterAccountingLedger'])->name('analytics.ledger.voter');
        Route::get('/reports/revenue', [AdminController::class, 'revenueReport'])->name('reports.revenue');
        Route::get('/reports/engagement', [AdminController::class, 'engagementReport'])->name('reports.engagement');
        Route::post('/reports/engagement/questions/{report}/moderate', [AdminController::class, 'moderateQuestion'])
            ->name('reports.engagement.questions.moderate');
        
        // System Settings
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::put('/settings/security', [AdminController::class, 'updateSecuritySettings'])->name('settings.security');
        Route::put('/settings/password', [AdminController::class, 'updatePassword'])->name('settings.password');
        Route::post('/settings/test-email', [AdminController::class, 'testEmail'])->name('settings.test-email');

        // Platform Settings (Dynamic Pricing/Commissions)
        Route::get('/platform-settings', [AdminController::class, 'platformSettings'])->name('platform-settings');
        Route::post('/platform-settings', [AdminController::class, 'updatePlatformSetting'])->name('platform-settings.update');
        Route::delete('/platform-settings', [AdminController::class, 'deletePlatformSetting'])->name('platform-settings.delete');
        Route::post('/platform-settings/clear-cache', [AdminController::class, 'clearSettingsCache'])->name('platform-settings.clear-cache');

        // Office Profiles — civic education data for the voter popup
        Route::get('/office-profiles', [AdminOfficeProfileController::class, 'index'])->name('office-profiles.index');
        Route::get('/office-profiles/{politician}/edit', [AdminOfficeProfileController::class, 'edit'])->name('office-profiles.edit');
        Route::post('/office-profiles/{politician}', [AdminOfficeProfileController::class, 'update'])->name('office-profiles.update');
        Route::post('/office-profiles/{politician}/toggle-verified', [AdminOfficeProfileController::class, 'toggleVerified'])->name('office-profiles.toggle-verified');
        Route::delete('/office-profiles/{politician}', [AdminOfficeProfileController::class, 'destroy'])->name('office-profiles.destroy');

        // Email Template Management (Phase 7b)
        Route::get('/email-templates', [AdminController::class, 'emailTemplates'])->name('email-templates.index');
        Route::get('/email-templates/{template}/edit', [AdminController::class, 'editEmailTemplate'])->name('email-templates.edit');
        Route::put('/email-templates/{template}', [AdminController::class, 'updateEmailTemplate'])->name('email-templates.update');
        Route::patch('/email-templates/{template}/toggle', [AdminController::class, 'toggleEmailTemplate'])->name('email-templates.toggle');
        Route::get('/email-templates/{template}/preview', [AdminController::class, 'previewEmailTemplate'])->name('email-templates.preview');

        // Admin Profile (Phase 11)
        Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
        Route::put('/profile', [AdminController::class, 'updateProfile'])->name('profile.update');
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

// Phase 13 — Politician Public Profile Pages
Route::get('/politicians', [PublicProfileController::class, 'index'])->name('politicians.directory');
Route::get('/district-lookup', [PublicProfileController::class, 'districtLookup'])->name('district.lookup');

// Interactive 3D U.S. Regional Map
Route::get('/map', fn() => view('standalone.public.us-map'))->name('us.map');
Route::get('/p/{slug}', [PublicProfileController::class, 'show'])->name('politician.public.show');

// Profile claim flow (no auth required — claimant verifies via emailed token)
Route::get('/p/{slug}/claim',        [ProfileClaimController::class, 'show'])->name('politician.profile.claim.show');
Route::post('/p/{slug}/claim',       [ProfileClaimController::class, 'submit'])->name('politician.profile.claim.submit');
Route::get('/p/{slug}/claim/verify', [ProfileClaimController::class, 'verify'])->name('politician.profile.claim.verify');
