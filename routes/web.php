<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/import-uploader', function () {
    abort_if(app()->environment('production'), 404); // hide in prod
    return view('import_uploader');                  // resources/views/import_uploader.blade.php
});
