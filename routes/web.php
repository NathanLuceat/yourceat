<?php

use App\Http\Controllers\ActivitiesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectReadmeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);
Route::view('/curriculo', 'curriculo')->name('curriculo');
Route::get('/atividades', ActivitiesController::class)->middleware('throttle:60,1');
Route::get('/projetos/{repo}/readme', ProjectReadmeController::class)
    ->where('repo', '[A-Za-z0-9][A-Za-z0-9._-]{0,99}')
    ->middleware('throttle:30,1');
