<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = User::orderBy('name');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,staff_jabatan,staff_pembantu,user_view',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Nama diperlukan.',
            'email.required' => 'Email diperlukan.',
            'email.email' => 'Format email tidak sah.',
            'email.unique' => 'Email telah digunakan.',
            'password.required' => 'Kata laluan diperlukan.',
            'password.min' => 'Kata laluan mestilah sekurang-kurangnya 6 aksara.',
            'password.confirmed' => 'Pengesahan kata laluan tidak sepadan.',
            'role.required' => 'Peranan diperlukan.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'department' => $validated['department'],
            'position' => $validated['position'],
            'phone' => $validated['phone'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        ActivityLog::logActivity(
            'user_created',
            "Pengguna baharu '{$user->name}' telah dicipta",
            $user,
            ['role' => $user->role, 'department' => $user->department]
        );

        return redirect()->route('users.show', $user)
            ->with('success', 'Pengguna berjaya dicipta.');
    }

    public function show(User $user)
    {
        $user->load(['createdFiles', 'borrowedFiles.file']);
        
        $recentActivities = ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('users.show', compact('user', 'recentActivities'));
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|in:admin,staff_jabatan,staff_pembantu,user_view',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ]);

        $oldData = $user->toArray();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'department' => $validated['department'],
            'position' => $validated['position'],
            'phone' => $validated['phone'],
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        ActivityLog::logActivity(
            'user_updated',
            "Pengguna '{$user->name}' telah dikemaskini",
            $user,
            ['old' => $oldData, 'new' => $validated]
        );

        return redirect()->route('users.show', $user)
            ->with('success', 'Pengguna berjaya dikemaskini.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak boleh memadam akaun sendiri.');
        }

        if ($user->createdFiles()->count() > 0 || $user->borrowedFiles()->active()->count() > 0) {
            return back()->with('error', 'Pengguna tidak boleh dipadam kerana mempunyai rekod fail atau peminjaman aktif.');
        }

        ActivityLog::logActivity(
            'user_deleted',
            "Pengguna '{$user->name}' telah dipadam",
            $user
        );

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Pengguna berjaya dipadam.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak boleh mengubah status akaun sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        
        ActivityLog::logActivity(
            'user_status_changed',
            "Status pengguna '{$user->name}' telah {$status}",
            $user,
            ['new_status' => $user->is_active]
        );

        return back()->with('success', "Status pengguna berjaya {$status}.");
    }
}