<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

// Module E - purchase orders, PI & banking documents, LC. All POST; the PO / PI number travels in the body (`no`).

// Purchase orders
Route::post('/po/gen', [PurchaseOrderController::class, 'gen'])->name('po.gen');
Route::post('/po/dispatch', [PurchaseOrderController::class, 'dispatchSelected'])->name('po.dispatch');
Route::post('/po/dispatch-one', [PurchaseOrderController::class, 'dispatchOne'])->name('po.dispatch1');
Route::post('/po/schedule', [PurchaseOrderController::class, 'schedule'])->name('po.schedule');
Route::post('/po/run-due', [PurchaseOrderController::class, 'runDue'])->name('po.run');
Route::post('/po/retry', [PurchaseOrderController::class, 'retry'])->name('po.retry');
Route::post('/po/remind', [PurchaseOrderController::class, 'remind'])->name('po.remind');
Route::post('/po/resolve', [PurchaseOrderController::class, 'resolve'])->name('po.resolve');
Route::post('/po/update', [PurchaseOrderController::class, 'update'])->name('po.update');
Route::post('/po/dev-ok', [PurchaseOrderController::class, 'devOk'])->name('po.devok');

// PI & banking documents, LC
Route::post('/pi/pick', [ImportController::class, 'pick'])->name('pi.pick');
Route::post('/pi/record', [ImportController::class, 'record'])->name('pi.record');
Route::post('/pi/approve', [ImportController::class, 'approve'])->name('pi.approve');
Route::post('/pi/clarify', [ImportController::class, 'clarify'])->name('pi.clarify');
Route::post('/pi/bank', [ImportController::class, 'bank'])->name('pi.bank');
Route::post('/pi/doc', [ImportController::class, 'doc'])->name('pi.doc');
Route::post('/pi/docs-done', [ImportController::class, 'docsDone'])->name('pi.docsdone');
Route::post('/pi/lc-approve', [ImportController::class, 'lcApprove'])->name('lc.approve');
Route::post('/pi/lc-open', [ImportController::class, 'lcOpen'])->name('lc.open');
