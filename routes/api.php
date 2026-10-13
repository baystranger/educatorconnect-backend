<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\ModerationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Centre;
use App\Http\Controllers\Api\V1\ChildcareCentreController;
use App\Http\Controllers\Api\V1\Educator;
use App\Http\Controllers\Api\V1\EducatorPublicController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentRequestController;
use App\Http\Controllers\Api\V1\Professional;
use App\Http\Controllers\Api\V1\ProfessionalDirectoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {

    // ── Authentication ─────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
            Route::post('oauth/exchange', [AuthController::class, 'exchangeOAuthCode']);
        });

        // Guests get `data: null` (not 401) so first-party page loads stay quiet.
        Route::get('session', [AuthController::class, 'session']);

        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
            Route::delete('providers/{provider}', [AuthController::class, 'disconnectProvider']);
        });
    });

    // ── Public ─────────────────────────────────────────────────────
    Route::get('meta/options', [MetaController::class, 'options']);

    Route::get('childcare-centres', [ChildcareCentreController::class, 'index']);
    Route::get('childcare-centres/{slug}', [ChildcareCentreController::class, 'show']);
    Route::get('childcare-centres/{slug}/jobs', [ChildcareCentreController::class, 'jobs']);
    Route::post('childcare-centres/{slug}/requests', [ParentRequestController::class, 'store'])->middleware('throttle:public-form');

    Route::get('jobs', [JobController::class, 'index']);
    Route::get('jobs/{slug}', [JobController::class, 'show']);

    Route::get('educators/{slug}', [EducatorPublicController::class, 'show']);

    Route::get('professionals/categories', [ProfessionalDirectoryController::class, 'categories']);
    Route::get('professionals', [ProfessionalDirectoryController::class, 'index']);
    Route::get('professionals/{slug}', [ProfessionalDirectoryController::class, 'show']);

    // ── Signed in (any role) ───────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('account')->group(function () {
            Route::put('/', [AccountController::class, 'update']);
            Route::put('email', [AccountController::class, 'changeEmail'])->middleware('throttle:auth');
            Route::put('password', [AccountController::class, 'changePassword'])->middleware('throttle:auth');
            Route::get('notification-preferences', [AccountController::class, 'notificationPreferences']);
            Route::put('notification-preferences', [AccountController::class, 'updateNotificationPreferences']);
            Route::get('export', [AccountController::class, 'export'])->middleware('throttle:3,10');
            Route::post('deactivate', [AccountController::class, 'deactivate'])->middleware('throttle:auth');
        });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

        // ── Educator ───────────────────────────────────────────────
        Route::middleware('role:educator')->group(function () {
            Route::prefix('educator')->group(function () {
                Route::get('dashboard', Educator\DashboardController::class);
                Route::get('profile', [Educator\ProfileController::class, 'show']);
                Route::put('profile', [Educator\ProfileController::class, 'update']);
                Route::put('profile/practicum-mode', [Educator\ProfileController::class, 'practicumMode']);
                Route::post('profile/dismiss-setup', [Educator\ProfileController::class, 'dismissSetup']);
                Route::post('profile/media/{kind}', [Educator\ProfileController::class, 'upload'])
                    ->whereIn('kind', ['image', 'video', 'resume'])->middleware('throttle:uploads');
                Route::delete('profile/media/{kind}', [Educator\ProfileController::class, 'removeMedia'])->whereIn('kind', ['video', 'resume']);
                Route::get('profile/resume', [Educator\ProfileController::class, 'downloadResume']);

                Route::post('credentials', [Educator\CredentialController::class, 'store']);
                Route::put('credentials/{credential}', [Educator\CredentialController::class, 'update'])->whereNumber('credential');
                Route::delete('credentials/{credential}', [Educator\CredentialController::class, 'destroy'])->whereNumber('credential');

                Route::get('applications', [Educator\ApplicationController::class, 'index']);
                Route::get('applications/{application}', [Educator\ApplicationController::class, 'show']);
                Route::post('applications/{application}/withdraw', [Educator\ApplicationController::class, 'withdraw']);
                Route::post('applications/{application}/messages', [Educator\ApplicationController::class, 'message']);

                Route::get('saved-jobs', [Educator\SavedJobController::class, 'index']);
            });

            Route::post('jobs/{job}/apply', [Educator\ApplicationController::class, 'store'])->middleware('verified.api');
            Route::post('jobs/{job}/save', [Educator\SavedJobController::class, 'store']);
            Route::delete('jobs/{job}/save', [Educator\SavedJobController::class, 'destroy']);
        });

        // ── Childcare centre ───────────────────────────────────────
        Route::middleware('role:childcare_centre')->prefix('centre')->group(function () {
            Route::get('dashboard', Centre\DashboardController::class);

            Route::get('profile', [Centre\ProfileController::class, 'show']);
            Route::put('profile/{section}', [Centre\ProfileController::class, 'updateSection'])
                ->whereIn('section', ['basics', 'about', 'programs', 'media', 'team', 'parents']);
            Route::post('profile/media/{kind}', [Centre\ProfileController::class, 'upload'])
                ->whereIn('kind', ['image', 'video'])->middleware('throttle:uploads');
            Route::delete('profile/media/video', [Centre\ProfileController::class, 'removeVideo']);
            Route::post('profile/team/{member}/photo', [Centre\ProfileController::class, 'teamPhoto'])
                ->whereNumber('member')->defaults('kind', 'image')->middleware('throttle:uploads');
            Route::post('profile/submit', [Centre\ProfileController::class, 'submit'])->middleware('verified.api');

            Route::post('gallery', [Centre\GalleryController::class, 'store'])->middleware('throttle:uploads');
            Route::put('gallery/reorder', [Centre\GalleryController::class, 'reorder']);
            Route::put('gallery/{item}', [Centre\GalleryController::class, 'update'])->whereNumber('item');
            Route::post('gallery/{item}/cover', [Centre\GalleryController::class, 'cover'])->whereNumber('item');
            Route::delete('gallery/{item}', [Centre\GalleryController::class, 'destroy'])->whereNumber('item');
            Route::post('resources', [Centre\GalleryController::class, 'storeResource'])->defaults('kind', 'document')->middleware('throttle:uploads');
            Route::delete('resources/{resource}', [Centre\GalleryController::class, 'destroyResource'])->whereNumber('resource');

            Route::get('jobs', [Centre\JobController::class, 'index']);
            Route::post('jobs', [Centre\JobController::class, 'store']);
            Route::get('jobs/{job}', [Centre\JobController::class, 'show'])->whereNumber('job');
            Route::put('jobs/{job}', [Centre\JobController::class, 'update'])->whereNumber('job');
            Route::delete('jobs/{job}', [Centre\JobController::class, 'destroy'])->whereNumber('job');
            Route::post('jobs/{job}/publish', [Centre\JobController::class, 'transition'])->defaults('action', 'publish')->middleware('verified.api');
            Route::post('jobs/{job}/{action}', [Centre\JobController::class, 'transition'])->whereIn('action', ['close', 'archive', 'duplicate']);

            Route::get('jobs/{job}/applicants', [Centre\ApplicantController::class, 'index'])->whereNumber('job');
            Route::get('applications/{application}', [Centre\ApplicantController::class, 'show']);
            Route::put('applications/{application}/status', [Centre\ApplicantController::class, 'updateStatus']);
            Route::post('applications/{application}/messages', [Centre\ApplicantController::class, 'message']);
            Route::get('applications/{application}/resume', [Centre\ApplicantController::class, 'resume']);

            Route::get('families', [Centre\FamilyController::class, 'index']);
            Route::get('families/{parentRequest}', [Centre\FamilyController::class, 'show']);
            Route::post('families/{parentRequest}/reply', [Centre\FamilyController::class, 'reply']);
            Route::put('families/{parentRequest}/status', [Centre\FamilyController::class, 'updateStatus']);
        });

        // ── Professional ───────────────────────────────────────────
        Route::middleware('role:professional|administrator')->group(function () {
            Route::post('professionals', [ProfessionalDirectoryController::class, 'store'])->middleware('throttle:public-form');
            Route::post('professionals/{listing}/claim', [ProfessionalDirectoryController::class, 'claim'])->whereNumber('listing')->middleware('throttle:uploads');
        });
        Route::middleware('role:professional')->prefix('professional')->group(function () {
            Route::get('listings', [Professional\ListingController::class, 'index']);
            Route::put('listings/{listing}', [Professional\ListingController::class, 'update'])->whereNumber('listing');
            Route::post('listings/{listing}/logo', [Professional\ListingController::class, 'uploadLogo'])
                ->whereNumber('listing')->defaults('kind', 'image')->middleware('throttle:uploads');
        });

        // ── Administrator ──────────────────────────────────────────
        Route::middleware('role:administrator')->prefix('admin')->group(function () {
            Route::get('overview', [ModerationController::class, 'overview']);
            Route::get('centres', [ModerationController::class, 'centres']);
            Route::post('centres/{centre}/{action}', [ModerationController::class, 'centreAction'])->whereIn('action', ['approve', 'verify', 'unverify', 'suspend']);
            Route::get('credentials', [ModerationController::class, 'credentials']);
            Route::put('credentials/{credential}', [ModerationController::class, 'verifyCredential']);
            Route::get('claims', [ModerationController::class, 'claims']);
            Route::post('claims/{claim}/{action}', [ModerationController::class, 'claimAction'])->whereIn('action', ['approve', 'reject']);
            Route::get('claims/{claim}/document', [ModerationController::class, 'claimDocument']);
            Route::get('listings', [ModerationController::class, 'listings']);
            Route::post('listings/{listing}/{action}', [ModerationController::class, 'listingAction'])->whereIn('action', ['approve', 'reject']);
        });
    });
});
