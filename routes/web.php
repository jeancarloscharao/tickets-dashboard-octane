<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardProController;


Route::get('/dashboard-fast', [DashboardController::class, 'index']);
Route::get('/dashboard-pro', DashboardProController::class);

Route::view('/', 'dashboard');
