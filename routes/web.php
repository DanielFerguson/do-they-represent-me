<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

/*
 * Routes that need a session. The admin panel registers its own routes,
 * and the public pages are in routes/public.php.
 */

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
