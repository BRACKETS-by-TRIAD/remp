<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/journal/{group}/categories/{category}/actions', function(\App\Contracts\JournalContract $journalContract, $group, $category) {
    return $journalContract->actions($group, $category);
});


Route::get('/journal/flags', function(\App\Contracts\JournalContract $journalContract) {
    return $journalContract->flags();
});