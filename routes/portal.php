<?php

use App\Http\Controllers\SupplierPortalController;
use App\Http\Middleware\SupplierPortalActor;
use Illuminate\Support\Facades\Route;

/*
 | PUBLIC supplier portal (module C). Required from routes/web.php OUTSIDE the `auth` group, so it inherits only the `web`
 | middleware group (session + CSRF). No login: the secret is the 40-hex link token ({token}); only its SHA-256 is stored in the case.
 | Every route is throttled; the SupplierPortalActor middleware makes the audit trail say "Supplier" and denies all internal permissions.
 |   GET  /s/{token}                 RFQ / quotation form (tabs via ?tab=)           supplier.portal
 |   POST /s/{token}/save            save tab / save draft                           supplier.portal.save
 |   POST /s/{token}/submit          final submission (cut-off + required fields enforced server-side)
 |   POST /s/{token}/revise          start a new revision while the link is open
 |   POST /s/{token}/revise/cancel   drop the revision draft
 |   GET  /s/{token}/po              purchase order acknowledgment screen
 |   POST /s/{token}/po/{no}/accept | /dispute
 */
Route::prefix('s/{token}')->where(['token' => '[A-Za-z0-9]{8,64}'])->middleware(['throttle:60,1', SupplierPortalActor::class])->group(function () {
    Route::get('/', [SupplierPortalController::class, 'show'])->name('supplier.portal');
    Route::get('/po', [SupplierPortalController::class, 'po'])->name('supplier.portal.po');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/save', [SupplierPortalController::class, 'save'])->name('supplier.portal.save');
        Route::post('/submit', [SupplierPortalController::class, 'submit'])->name('supplier.portal.submit');
        Route::post('/revise', [SupplierPortalController::class, 'revise'])->name('supplier.portal.revise');
        Route::post('/revise/cancel', [SupplierPortalController::class, 'cancelRevision'])->name('supplier.portal.revise.cancel');
        Route::post('/po/{no}/accept', [SupplierPortalController::class, 'accept'])->name('supplier.portal.po.accept');
        Route::post('/po/{no}/dispute', [SupplierPortalController::class, 'dispute'])->name('supplier.portal.po.dispute');
    });
});
