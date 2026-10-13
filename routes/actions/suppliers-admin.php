<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// Module F - suppliers & administration actions (every action is re-checked server-side via Perm::need in the services)
Route::post('/suppliers', [SupplierController::class, 'store'])->name('sup.store');
Route::post('/supplier/{id}/verify', [SupplierController::class, 'verify'])->where('id', '[^/]+')->name('sup.verify');

Route::prefix('admin')->group(function () {
    Route::post('/users', [AdminController::class, 'userSave'])->name('ad.user.save');
    Route::post('/users/{id}/password', [AdminController::class, 'userPassword'])->whereNumber('id')->name('ad.user.pw');
    Route::post('/materials', [AdminController::class, 'materialSave'])->name('ad.mat.save');
    Route::post('/reasons', [AdminController::class, 'reasonAdd'])->name('ad.reason.add');
    Route::post('/reasons/delete', [AdminController::class, 'reasonDel'])->name('ad.reason.del');
    Route::post('/settings', [AdminController::class, 'settingsSave'])->name('ad.settings.save');
    Route::post('/calendars/{key}/approve', [AdminController::class, 'calApprove'])->name('ad.cal.on');
    Route::post('/calendars/{key}/revoke', [AdminController::class, 'calRevoke'])->name('ad.cal.off');
    Route::post('/calendars/{key}/day', [AdminController::class, 'calDay'])->name('ad.cal.day');
    Route::post('/calendars/{key}/year', [AdminController::class, 'calYear'])->name('ad.cal.year');
    Route::post('/templates/{code}', [AdminController::class, 'templateSave'])->name('ad.tpl.save');
    Route::post('/policy/{n}', [AdminController::class, 'policySave'])->whereNumber('n')->name('ad.policy.save');
    Route::post('/authority/{i}', [AdminController::class, 'authoritySave'])->whereNumber('i')->name('ad.auth.save');
    Route::post('/tests/{code}', [AdminController::class, 'testResult'])->name('ad.test.set');
    Route::post('/demo/fail-mail', [AdminController::class, 'failMail'])->name('ad.demo.mail');
    Route::post('/demo/export', [AdminController::class, 'export'])->name('ad.demo.export');
    Route::post('/demo/stage', [AdminController::class, 'stageLoad'])->name('ad.demo.stage');
    Route::post('/demo/reset', [AdminController::class, 'reset'])->name('ad.demo.reset');
});
