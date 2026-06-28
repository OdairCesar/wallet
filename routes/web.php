<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::redirect('/docs', '/docs/api');
Route::redirect('/swagger', '/docs/api');
