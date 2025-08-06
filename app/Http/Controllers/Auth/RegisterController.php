<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'in:admin,staff_jabatan,staff_pembantu,user_view'],
            'department' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Nama diperlukan.',
            'email.required' => 'Email diperlukan.',
            'email.email' => 'Format email tidak sah.',
            'email.unique' => 'Email telah digunakan.',
            'password.required' => 'Kata laluan diperlukan.',
            'password.min' => 'Kata laluan mestilah sekurang-kurangnya 6 aksara.',
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
            'role.required' => 'Peranan diperlukan.',
            'role.in' => 'Peranan tidak sah.',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);
    }

    protected function registered(Request $request, $user)
    {
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'user_registered',
            'description' => "Pengguna baharu {$user->name} telah mendaftar",
            'properties' => [
                'role' => $user->role,
                'department' => $user->department,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect($this->redirectPath());
    }
}