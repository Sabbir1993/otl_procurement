<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Perm;
use App\Support\WorkingCase;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Enforce segregation of duties. Throws SegregationOfDuties -> toast + redirect back (see Handler). */
    protected function need(string $permission): void
    {
        Perm::need($permission);
    }

    /** The working case from the top-bar selector (never null once a case exists). */
    protected function workingCase(): ?CaseBook
    {
        return WorkingCase::get();
    }

    /** Redirect back with a toast. kind: '' | 'ok' | 'warn' | 'bad'. $action = ['url' => ..., 'label' => ...] optional button. */
    protected function done(string $msg, string $kind = '', ?array $action = null)
    {
        return back()->with('toast', ['msg' => $msg, 'kind' => $kind, 'action' => $action]);
    }

    protected function doneTo(string $url, string $msg, string $kind = '', ?array $action = null)
    {
        return redirect($url)->with('toast', ['msg' => $msg, 'kind' => $kind, 'action' => $action]);
    }
}
