<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BiologicosController;

Route::post('vacunas/biologicos/preview', [BiologicosController::class, 'preview']);
