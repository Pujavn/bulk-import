<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ProductImageController;


Route::post('/imports/products', [ImportController::class, 'products']);


Route::post('/uploads/init', [UploadController::class, 'init']);
Route::post('/uploads/{publicId}/chunk', [UploadController::class, 'chunk']);
Route::post('/uploads/{publicId}/complete', [UploadController::class, 'complete']);


Route::post('/products/{product}/attach-upload/{publicId}', [ProductImageController::class, 'attach']);
