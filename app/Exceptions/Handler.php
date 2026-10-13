<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Segregation of duties: never a bare 403 - tell the user who can do it and go back.
        $this->renderable(function (SegregationOfDuties $e, Request $r) {
            return back()->with('toast', ['msg' => $e->getMessage(), 'kind' => 'warn']);
        });

        // Business-rule violation from a service: toast + keep the form input and re-open the dialog.
        $this->renderable(function (BusinessRule $e, Request $r) {
            return back()->withInput()->with('toast', ['msg' => $e->getMessage(), 'kind' => 'bad']);
        });
    }
}
