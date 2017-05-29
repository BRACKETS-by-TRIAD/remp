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

Route::get('banners/json', 'BannerController@json')->name('banners.json');
Route::get('campaigns/json', 'CampaignController@json')->name('campaigns.json');
Route::get('banners/preview/{uuid}', 'BannerController@preview')->name('banners.preview');
Route::get('campaigns/showtime/{uuid}', 'CampaignController@showtime')->name('campaigns.showtime');
Route::get('dashboard', 'DashboardController@index')->name('dashboard');

Route::resource('banners', 'BannerController');
Route::resource('campaigns', 'CampaignController');
