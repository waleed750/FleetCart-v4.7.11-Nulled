<?php

use Illuminate\Support\Facades\Route;

Route::get('egypt', [
    'as' => 'admin.egypt.index',
    'uses' => 'EgyptSettingsController@index',
    'middleware' => 'can:admin.egypt.index',
]);

Route::put('egypt/tracking', [
    'as' => 'admin.egypt.tracking.update',
    'uses' => 'EgyptSettingsController@updateTracking',
    'middleware' => 'can:admin.egypt.edit',
]);

Route::put('egypt/shipping', [
    'as' => 'admin.egypt.shipping.update',
    'uses' => 'EgyptSettingsController@updateShipping',
    'middleware' => 'can:admin.egypt.edit',
]);

Route::put('egypt/features', [
    'as' => 'admin.egypt.features.update',
    'uses' => 'EgyptSettingsController@updateFeatures',
    'middleware' => 'can:admin.egypt.features',
]);
