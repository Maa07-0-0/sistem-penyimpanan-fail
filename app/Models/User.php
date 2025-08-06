<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'position',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function createdFiles()
    {
        return $this->hasMany(File::class, 'created_by');
    }

    public function updatedFiles()
    {
        return $this->hasMany(File::class, 'updated_by');
    }

    public function borrowedFiles()
    {
        return $this->hasMany(BorrowingRecord::class, 'borrower_id');
    }

    public function approvedBorrowings()
    {
        return $this->hasMany(BorrowingRecord::class, 'approved_by');
    }

    public function returnedBorrowings()
    {
        return $this->hasMany(BorrowingRecord::class, 'returned_to');
    }

    public function fileMovements()
    {
        return $this->hasMany(FileMovement::class, 'moved_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    public function canManageFiles()
    {
        return in_array($this->role, ['admin', 'staff_jabatan', 'staff_pembantu']);
    }

    public function canManageUsers()
    {
        return $this->role === 'admin';
    }

    public function canBorrowFiles()
    {
        return in_array($this->role, ['admin', 'staff_jabatan', 'staff_pembantu']);
    }

    public function canViewReports()
    {
        return in_array($this->role, ['admin', 'staff_jabatan']);
    }

    public function getRoleDisplayAttribute()
    {
        $roles = [
            'admin' => 'Pentadbir Sistem',
            'staff_jabatan' => 'Pegawai Jabatan',
            'staff_pembantu' => 'Pembantu Tadbir',
            'user_view' => 'Pengguna Lihat Sahaja'
        ];

        return $roles[$this->role] ?? $this->role;
    }
}