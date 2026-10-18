<?php

use App\Modules\Submissions\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('uploads', [UploadController::class, 'store'])
    ->middleware(['auth', 'throttle:uploads'])
    ->name('uploads.store');
