<?php

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
    return redirect(route('dashboard'));
});

Route::get('/error', function() {
    return 'error during login: ' . $_GET['error'];
})->name('sso.error');

Route::middleware('auth.jwt')->group(function () {
    Route::get('dashboard', 'DashboardController@index')->name('dashboard');
});

Route::get('auth/login', 'AuthController@login')->name('auth.login');
Route::get('auth/google', 'Auth\GoogleController@redirect')->name('auth.google');
Route::get('auth/google/callback', 'Auth\GoogleController@callback');