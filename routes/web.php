<?php

use App\Http\Controllers\PreviewController;
use App\Http\Controllers\QuizPagesController;
use App\Http\Controllers\StanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QuizPagesController::class, 'home'])->name('home');
Route::get('/quiz', [QuizPagesController::class, 'quiz'])->name('quiz');
Route::get('/results', [QuizPagesController::class, 'results'])->name('results');

Route::get('/stances/{hash}.json', StanceController::class)
    ->where('hash', '[0-9a-f]{64}')
    ->withoutMiddleware('web')
    ->name('stances.show');

Route::middleware('signed:relative')->prefix('preview')->name('preview.')->group(function () {
    Route::get('/quiz', [PreviewController::class, 'quiz'])->name('quiz');
    Route::get('/results', [PreviewController::class, 'results'])->name('results');
    Route::get('/stances.json', [PreviewController::class, 'stances'])->name('stances');
});
