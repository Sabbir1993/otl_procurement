<?php

use App\Http\Controllers\AwardController;
use App\Http\Controllers\ComparativeStatementController;
use App\Http\Controllers\SampleController;
use Illuminate\Support\Facades\Route;

// Module D - comparative statement, samples & QA, awards / approvals (actions)
Route::post('/cs/{id}/allocate', [ComparativeStatementController::class, 'allocate'])->where('id', '[^/]+')->name('cs.allocate');
Route::post('/cs/{id}/clear', [ComparativeStatementController::class, 'clear'])->where('id', '[^/]+')->name('cs.clear');
Route::post('/cs/{id}/justify', [ComparativeStatementController::class, 'justify'])->where('id', '[^/]+')->name('cs.justify');
Route::post('/cs/{id}/finance-remark', [ComparativeStatementController::class, 'finRemark'])->where('id', '[^/]+')->name('cs.fin');
Route::post('/cs/{id}/negotiate', [ComparativeStatementController::class, 'negotiate'])->where('id', '[^/]+')->name('cs.negotiate');
Route::post('/cs/{id}/exclude', [ComparativeStatementController::class, 'exclude'])->where('id', '[^/]+')->name('cs.exclude');
Route::post('/cs/{id}/include', [ComparativeStatementController::class, 'reinclude'])->where('id', '[^/]+')->name('cs.include');
Route::post('/cs/{id}/bulk', [ComparativeStatementController::class, 'bulk'])->where('id', '[^/]+')->name('cs.bulk');
Route::get('/cs/{id}/csv', [ComparativeStatementController::class, 'csv'])->where('id', '[^/]+')->name('cs.csv');

Route::post('/samples/result', [SampleController::class, 'save'])->name('smp.save');

Route::post('/award/{id}/submit', [AwardController::class, 'submit'])->where('id', '[^/]+')->name('aw.submit');
Route::post('/award/{id}/decide', [AwardController::class, 'decide'])->where('id', '[^/]+')->name('appr.decide');
Route::post('/award/{id}/change', [AwardController::class, 'change'])->where('id', '[^/]+')->name('appr.change');
