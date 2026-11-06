<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoundItemsController;
Route::get('/items',[FoundItemsController::class,'index']);
Route::post('/items',[FoundItemsController::class,'store']);
Route::post('/items/{item}/return',[FoundItemsController::class,'markReturned']);
Route::get('/items/{item}',[FoundItemsController::class,'show']);
