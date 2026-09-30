<?php

use App\Http\Controllers\DistrictController;
use App\Http\Controllers\InfoPagesController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\QuizPagesController;
use App\Http\Controllers\StanceController;
use Illuminate\Support\Facades\Route;

/*
 * Public pages, served without a session or cookies (the "public"
 * middleware group). Nothing here may use $errors, @csrf or old().
 */

Route::get('/', [QuizPagesController::class, 'home'])->name('home');
Route::get('/quiz', [QuizPagesController::class, 'quiz'])->name('quiz');
Route::get('/results', [QuizPagesController::class, 'results'])->name('results');

Route::get('/districts', [DistrictController::class, 'index'])->name('districts.index');
Route::get('/districts/{district}', [DistrictController::class, 'show'])->where('district', '[a-z0-9-]+')->name('districts.show');

Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
Route::get('/policies/{policy}', [PolicyController::class, 'show'])->where('policy', '[a-z0-9-]+')->name('policies.show');

Route::get('/methodology', [InfoPagesController::class, 'methodology'])->name('methodology');
Route::get('/privacy', [InfoPagesController::class, 'privacy'])->name('privacy');
Route::get('/about', [InfoPagesController::class, 'about'])->name('about');

Route::get('/stances/{hash}.json', StanceController::class)
    ->where('hash', '[0-9a-f]{64}')
    ->name('stances.show');

Route::get('/localities/{hash}.json', LocalityController::class)
    ->where('hash', '[0-9a-f]{64}')
    ->name('localities.show');

Route::middleware('signed:relative')->prefix('preview')->name('preview.')->group(function () {
    Route::get('/quiz', [PreviewController::class, 'quiz'])->name('quiz');
    Route::get('/results', [PreviewController::class, 'results'])->name('results');
    Route::get('/stances.json', [PreviewController::class, 'stances'])->name('stances');
    Route::get('/policies/{policy}', [PreviewController::class, 'policy'])->where('policy', '[a-z0-9-]+')->name('policies.show');
});
