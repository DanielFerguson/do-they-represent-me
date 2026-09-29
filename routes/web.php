<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/quiz', 'quiz')->name('quiz');
Route::view('/results', 'results')->name('results');
