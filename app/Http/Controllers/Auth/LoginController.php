<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'Email diperlukan.',
            'password.required' => 'Kata laluan diperlukan.',
            'password.min' => 'Kata laluan mestilah sekurang-kurangnya 6 aksara.',
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        return $this->guard()->attempt(
            array_merge(
                $this->credentials($request),
                ['is_active' => true]
            ),
            $request->boolean('remember')
        );
    }

    protected function authenticated(Request $request, $user)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => "Pengguna {$user->name} log masuk ke sistem",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended($this->redirectPath());
    }

    protected function loggedOut(Request $request)
    {
        if (auth()->check()) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'logout',
                'description' => "Pengguna " . auth()->user()->name . " log keluar dari sistem",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect('/');
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        return back()->withErrors([
            $this->username() => 'Maklumat log masuk tidak sah atau akaun tidak aktif.',
        ])->withInput($request->only($this->username(), 'remember'));
    }
}