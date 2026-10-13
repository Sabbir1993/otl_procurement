<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Public supplier portal (/s/{token}): there is no login. For the duration of the request the "current user" is an in-memory,
 * never-persisted actor with role "Supplier" - so Workflow::log/raise/notify attribute rows to the supplier (never to an internal role
 * or "Audit viewer") and Perm::can() is false for every internal permission, even if a staff member happens to be signed in.
 */
class SupplierPortalActor
{
    public function handle(Request $request, Closure $next)
    {
        $guard = Auth::guard();
        $prev = $guard->hasUser() ? $guard->user() : null;
        $guard->setUser((new User())->forceFill(['name' => 'Supplier portal', 'role' => 'Supplier', 'status' => 'Active']));
        try {
            return $next($request);
        } finally {
            $prev ? $guard->setUser($prev) : $guard->forgetUser();
        }
    }
}
