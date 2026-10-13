<?php

namespace App\Http\Controllers;

use App\Services\CommandCenterService;
use Illuminate\Http\Request;

// Sidebar group: Command Center
class CommandCenterController extends Controller
{
    public function index(CommandCenterService $svc)
    {
        return view('command-center.index', $svc->overview());
    }

    /** Task-card "Open": switch the working case, then go to the target page. */
    public function goCase(Request $r, CommandCenterService $svc)
    {
        $d = $r->validate(['c' => 'nullable|string|max:40', 'to' => 'required|string|max:40']);
        return redirect($svc->goCase($d['c'] ?? null, $d['to']));
    }
}
