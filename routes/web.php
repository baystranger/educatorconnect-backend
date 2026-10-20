<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\PublicFileController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth/{provider}/redirect', [AuthController::class, 'oauthRedirect'])
    ->middleware('throttle:auth')
    ->name('oauth.redirect');
Route::get('/oauth/{provider}/callback', [AuthController::class, 'oauthCallback'])
    ->middleware('throttle:auth')
    ->name('oauth.callback');

// Uploaded photos and videos, when the web server can't serve public_html/storage itself.
// No session or cookies: these are plain public files.
Route::get('/storage/{path}', PublicFileController::class)
    ->where('path', '.*')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('storage.public');
