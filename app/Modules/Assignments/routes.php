<?php

use App\Modules\Assignments\Http\Controllers\AssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('assignments')->name('assignments.')->group(function () {
    Route::get('create', [AssignmentController::class, 'create'])->name('create');
    Route::post('/', [AssignmentController::class, 'store'])->middleware('throttle:submissions')->name('store');
});
