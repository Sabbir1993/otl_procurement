<?php

use App\Http\Controllers\RequisitionController;
use Illuminate\Support\Facades\Route;

// Module B - requisition board
Route::post('/requisitions/lines', [RequisitionController::class, 'saveLine'])->name('rq.line.save');
Route::post('/requisitions/lines/bulk-accept', [RequisitionController::class, 'bulkAccept'])->name('rq.bulk.accept');
Route::post('/requisitions/lines/bulk-reject', [RequisitionController::class, 'bulkReject'])->name('rq.bulk.reject');
Route::post('/requisitions/lines/{line}/accept', [RequisitionController::class, 'accept'])->name('rq.line.accept');
Route::post('/requisitions/lines/{line}/revert', [RequisitionController::class, 'revert'])->name('rq.line.revert');
Route::delete('/requisitions/lines/{line}', [RequisitionController::class, 'destroy'])->name('rq.line.delete');
Route::post('/requisitions/freeze', [RequisitionController::class, 'freeze'])->name('rq.freeze');

// Module B - confirmed book qty
Route::get('/requisitions/booking/report', [RequisitionController::class, 'bkDl'])->name('rq.booking.report');
Route::post('/requisitions/booking/lines', [RequisitionController::class, 'bkAdd'])->name('rq.bk.add');
Route::post('/requisitions/booking/save', [RequisitionController::class, 'bkSave'])->name('rq.bk.save');
Route::delete('/requisitions/booking/lines/{pid}', [RequisitionController::class, 'bkDel'])->name('rq.bk.delete');
Route::post('/requisitions/booking/csv', [RequisitionController::class, 'bkCsv'])->name('rq.bk.csv');
Route::post('/requisitions/booking/confirm', [RequisitionController::class, 'bkConfirm'])->name('rq.bk.confirm');
