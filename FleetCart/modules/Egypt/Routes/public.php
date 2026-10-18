<?php

use Illuminate\Support\Facades\Route;

// Product catalogue for Google Merchant Center and Meta Commerce Manager.
// ?lang=ar|en picks the language of titles and descriptions.
Route::get('feeds/products.xml', 'ProductFeedController@index')->name('egypt.feeds.products');
