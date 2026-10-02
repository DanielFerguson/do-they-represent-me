<?php

use App\Http\Controllers\ContactController;
use App\Support\Csp\ContactPolicy;
use Illuminate\Support\Facades\Route;
use Spatie\Csp\AddCspHeaders;

/*
 * Routes that need a session. The admin panel registers its own routes,
 * and the public pages are in routes/public.php.
 */

Route::get('/contact', [ContactController::class, 'create'])->middleware(AddCspHeaders::class.':'.ContactPolicy::class)->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
