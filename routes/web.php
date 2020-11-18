<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*Route::get('/', function () {
    return view('welcome');
});*/

Route::get('/', [ApplicationController::class, 'index']);

Route::get('/run', [ApplicationController::class, 'runApplication']);

Route::get('/auth', [AuthController::class, 'authorizeApplication']);

Route::get('/redirect', [AuthController::class, 'redirect']);


