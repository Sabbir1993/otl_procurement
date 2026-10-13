<?php

namespace App\Http\Controllers;

use App\Domain\Workflow;
use App\Models\User;
use App\Support\WorkingCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login', ['demo' => config('oilpms.demo_login'), 'users' => User::orderBy('id')->get()]);
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (!Auth::attempt($cred + ['status' => 'Active'], $r->boolean('remember'))) {
            return back()->withInput($r->only('email'))->withErrors(['email' => 'These credentials do not match our records.']);
        }
        $r->session()->regenerate();
        return redirect()->intended(route('home'));
    }

    /** LOCAL DEMO ONLY: sign in as the seeded user of a role (no password). */
    public function demo(Request $r)
    {
        abort_unless(config('oilpms.demo_login'), 404);
        $user = User::where('role', $r->input('role'))->where('status', 'Active')->firstOrFail();
        Auth::login($user);
        $r->session()->regenerate();
        return redirect()->intended(route('home'));
    }

    /** LOCAL DEMO ONLY: role switcher (test segregation of duties) = re-login as that role, keep the page. */
    public function demoSwitch(Request $r)
    {
        abort_unless(config('oilpms.demo_login'), 404);
        $user = User::where('role', $r->input('role'))->where('status', 'Active')->firstOrFail();
        $case = session('case_id');
        Auth::login($user);
        $r->session()->regenerate();
        session(['case_id' => $case]);
        return back()->with('toast', ['msg' => 'Demo role: ' . config("roles.roles.{$user->role}.n") . " ({$user->name})", 'kind' => '']);
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function selectCase(Request $r)
    {
        $id = (string) $r->input('id');
        WorkingCase::set($id);
        return back()->with('toast', ['msg' => 'Working case: ' . $id, 'kind' => '']);
    }
}
