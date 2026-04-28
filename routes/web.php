<?php

use App\Http\Controllers\AltCallCenterController;
use App\Http\Controllers\CallCenterController;
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

Route::get('/', function () {
    return view('welcome');
});

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::get('/call-center', CallCenterController::class)->name('call-center');

Route::prefix('/alt')->name('alt.')->group(function () {
    Route::redirect('/', '/alt/login');
    Route::view('/login', 'alt.auth.login')->name('login');
    Route::view('/register', 'alt.auth.register')->name('register');
    Route::get('/call-center', AltCallCenterController::class)->name('call-center');
});
