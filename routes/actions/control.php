<?php

use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\ControlController;
use Illuminate\Support\Facades\Route;

// Module A - Command Center / Control actions
Route::post('/home/go-case', [CommandCenterController::class, 'goCase'])->name('home.goCase');
Route::post('/control/exceptions', [ControlController::class, 'saveException'])->name('exc.save');
Route::post('/control/notifications/read', [ControlController::class, 'readAll'])->name('notif.readAll');
Route::post('/control/notifications/{id}/open', [ControlController::class, 'openNotification'])->where('id', '[^/]+')->name('notif.open');
Route::get('/control/audit/export', [ControlController::class, 'exportAudit'])->name('audit.export');
