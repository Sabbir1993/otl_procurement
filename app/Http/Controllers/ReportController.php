<?php

namespace App\Http\Controllers;

use App\Domain\Perm;
use App\Services\ReportService;

// Sidebar group: Reports
class ReportController extends Controller
{
    public function performance(ReportService $svc)
    {
        if (!Perm::navAllows('an', 'an.perf')) {
            return $this->doneTo(route('home'), 'Your role cannot open this report.', 'warn');
        }
        return view('reports.performance', $svc->performance());
    }

    public function pipeline(ReportService $svc)
    {
        if (!Perm::navAllows('an', 'an.pipe')) {
            return $this->doneTo(route('home'), 'Your role cannot open this report.', 'warn');
        }
        return view('reports.pipeline', ['rows' => $svc->pipeline()]);
    }
}
