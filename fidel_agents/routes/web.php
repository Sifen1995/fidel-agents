<?php

use App\Http\Controllers\HomeworkDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeworkDemoController::class, 'index']);
Route::get('/homework', [HomeworkDemoController::class, 'index']);
