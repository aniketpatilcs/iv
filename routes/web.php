<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Feature routes live with their module in app/Modules/<Module>/routes.php
| and are loaded automatically by ModuleServiceProvider. Keep only
| app-wide routes here.
|
*/

Route::redirect('/', '/dashboard');
