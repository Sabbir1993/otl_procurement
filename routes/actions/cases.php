<?php

use App\Http\Controllers\PurchaseCaseController;
use Illuminate\Support\Facades\Route;

// Purchase Cases - actions
Route::post('/cases', [PurchaseCaseController::class, 'store'])->name('cases.store');
Route::post('/case/{id}/work', [PurchaseCaseController::class, 'work'])->name('case.work');
Route::get('/search', \App\Http\Controllers\SearchController::class)->name('gs.search');
