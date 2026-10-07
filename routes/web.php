<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/oauth/{provider}/redirect', [AuthController::class, 'oauthRedirect'])
    ->middleware('throttle:auth')
    ->name('oauth.redirect');
Route::get('/oauth/{provider}/callback', [AuthController::class, 'oauthCallback'])
    ->middleware('throttle:auth')
    ->name('oauth.callback');
