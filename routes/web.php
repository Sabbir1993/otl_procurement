<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommandCenterController;
use App\Http\Controllers\PurchaseCaseController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\SourcingController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ComparativeStatementController;
use App\Http\Controllers\ErpHandoffController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SampleController;

// Route names == sidebar page keys (see config/menu.php). One section per sidebar group.

// ---- Authentication (demo role login is local-only, see config/oilpms.php) ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/demo/login', [AuthController::class, 'demo'])->name('demo.login');
});

// ---- Public supplier portal (module C): tokenised, no login, throttled - see routes/portal.php ----
require base_path('routes/portal.php');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/demo/switch', [AuthController::class, 'demoSwitch'])->name('demo.switch');
    Route::post('/case/select', [AuthController::class, 'selectCase'])->name('case.select');

    // Action routes (POST) live in routes/actions/<module>.php; page routes (GET) are listed below.
    foreach (glob(base_path('routes/actions/*.php')) as $file) {
        require $file;
    }

    // ---- Command Center ----
    Route::get('/', [CommandCenterController::class, 'index'])->name('home');

    // ---- Purchase Cases ----
    Route::get('/cases', [PurchaseCaseController::class, 'index'])->name('cases');
    Route::get('/case/{id}', [PurchaseCaseController::class, 'show'])->where('id', '[^/]+')->name('case.show');

    // ---- Requisitions ----
    Route::get('/requisitions/pr', [ErpHandoffController::class, 'pr'])->name('pr');
    Route::get('/requisitions/board', [RequisitionController::class, 'board'])->name('rq.board');
    Route::get('/requisitions/booking', [RequisitionController::class, 'booking'])->name('rq.booking');

    // ---- Sourcing / RFQ ----
    Route::get('/sourcing/suppliers', [SourcingController::class, 'shortlist'])->name('src.supp');
    Route::get('/sourcing/rfqs', [SourcingController::class, 'rfqs'])->name('src.rfq');
    Route::get('/sourcing/tracking', [SourcingController::class, 'tracking'])->name('src.track');
    Route::get('/sourcing/quotes', [SourcingController::class, 'quotes'])->name('src.quotes');
    Route::get('/sourcing/comparative-statement', [ComparativeStatementController::class, 'index'])->name('src.cs');
    Route::get('/sourcing/samples', [SampleController::class, 'index'])->name('src.samples');
    Route::get('/rfq/{id}', [SourcingController::class, 'showRfq'])->where('id', '[^/]+')->name('rfq.show');
    Route::get('/quote/{id}', [SourcingController::class, 'showQuote'])->where('id', '[^/]+')->name('quote.show');

    // ---- Negotiation & Awards ----
    Route::get('/awards/negotiation', [AwardController::class, 'negotiation'])->name('aw.neg');
    Route::get('/awards/recommendation', [AwardController::class, 'recommendation'])->name('aw.rec');
    Route::get('/awards/approvals', [AwardController::class, 'approvals'])->name('aw.appr');
    Route::get('/award/{id}', [AwardController::class, 'show'])->where('id', '[^/]+')->name('award.show');

    // ---- Purchase Orders ----
    Route::get('/po/register', [PurchaseOrderController::class, 'register'])->name('po.reg');
    Route::get('/po/acknowledgments', [PurchaseOrderController::class, 'acknowledgments'])->name('po.ack');
    Route::get('/po/disputes', [PurchaseOrderController::class, 'disputes'])->name('po.disp');
    Route::get('/po/{id}', [PurchaseOrderController::class, 'show'])->where('id', '[^/]+')->name('po.show');

    // ---- Import & Warehouse ----
    Route::get('/import/pi', [ImportController::class, 'pi'])->name('imp.pi');
    Route::get('/import/lc', [ImportController::class, 'lc'])->name('imp.lc');
    Route::get('/erp/shipment', [ErpHandoffController::class, 'shipment'])->name('ship');
    Route::get('/erp/grn', [ErpHandoffController::class, 'grn'])->name('grn');
    Route::get('/erp/payment', [ErpHandoffController::class, 'payment'])->name('pay');
    Route::get('/pi/{id}', [ImportController::class, 'showPi'])->where('id', '[^/]+')->name('pi.show');

    // ---- Suppliers ----
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('sup.dir');
    Route::get('/portal', [PortalController::class, 'portal'])->name('portal');
    Route::get('/supplier/{id}', [SupplierController::class, 'show'])->where('id', '[^/]+')->name('sup.show');

    // ---- Reports ----
    Route::get('/reports/performance', [ReportController::class, 'performance'])->name('an.perf');
    Route::get('/reports/pipeline', [ReportController::class, 'pipeline'])->name('an.pipe');

    // ---- Control ----
    Route::get('/control/exceptions', [ControlController::class, 'exceptions'])->name('exc');
    Route::get('/control/notifications', [ControlController::class, 'notifications'])->name('notif');
    Route::get('/control/audit', [ControlController::class, 'audit'])->name('audit');

    // ---- Administration ----
    Route::get('/admin/roles', [AdminController::class, 'roles'])->name('ad.roles');
    Route::get('/admin/masters', [AdminController::class, 'masters'])->name('ad.master');
    Route::get('/admin/calendars', [AdminController::class, 'calendars'])->name('ad.cal');
    Route::get('/admin/templates', [AdminController::class, 'templates'])->name('ad.tpl');
    Route::get('/admin/policy', [AdminController::class, 'policy'])->name('ad.policy');
    Route::get('/admin/tests', [AdminController::class, 'tests'])->name('ad.tests');
    Route::get('/admin/demo', [AdminController::class, 'demo'])->name('ad.demo');
});
