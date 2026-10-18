<?php

use App\Modules\BidRequests\Http\Controllers\BidRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('bid-requests')->name('bid-requests.')->group(function () {
    Route::get('create', [BidRequestController::class, 'create'])->name('create');
    Route::post('/', [BidRequestController::class, 'store'])->middleware('throttle:submissions')->name('store');
});
