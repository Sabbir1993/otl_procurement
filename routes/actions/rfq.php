<?php

use App\Http\Controllers\SourcingController;
use Illuminate\Support\Facades\Route;

// Module C - Sourcing / RFQ actions (loaded inside the auth group; the server enforces permissions via Perm::need in RfqService)
Route::post('/sourcing/suppliers/save', [SourcingController::class, 'spSave'])->name('sp.save');
Route::post('/sourcing/suppliers/{id}/toggle', [SourcingController::class, 'spToggle'])->whereNumber('id')->name('sp.toggle');
Route::post('/sourcing/suppliers/{id}/delete', [SourcingController::class, 'spDelete'])->whereNumber('id')->name('sp.delete');

Route::post('/rfq/{id}/save', [SourcingController::class, 'rfqSave'])->where('id', '[^/]+')->name('rfq.save');
Route::post('/rfq/{id}/send', [SourcingController::class, 'drSend'])->where('id', '[^/]+')->name('rfq.send');
Route::post('/rfq/{id}/schedule', [SourcingController::class, 'drSchedule'])->where('id', '[^/]+')->name('rfq.schedule');
Route::post('/rfq/{id}/run', [SourcingController::class, 'drRun'])->where('id', '[^/]+')->name('rfq.run');
Route::post('/rfq/{id}/close', [SourcingController::class, 'rfqClose'])->where('id', '[^/]+')->name('rfq.close');
Route::post('/rfq/{id}/remind', [SourcingController::class, 'ivRemind'])->where('id', '[^/]+')->name('rfq.remind');
Route::post('/rfq/{id}/revoke', [SourcingController::class, 'ivRevoke'])->where('id', '[^/]+')->name('rfq.revoke');
Route::post('/rfq/{id}/extend', [SourcingController::class, 'ivExtend'])->where('id', '[^/]+')->name('rfq.extend');
