<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BiologicosController;
use App\Http\Controllers\CluesController;

Route::post('vacunas/biologicos/preview', [BiologicosController::class, 'preview']);

Route::get('/vacunas/clues/search', [CluesController::class, 'search'])->name('api.vacunas.clues.search');
