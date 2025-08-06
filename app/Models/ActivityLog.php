<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function getActionDisplayAttribute()
    {
        $actions = [
            'file_created' => 'Fail Dicipta',
            'file_updated' => 'Fail Dikemaskini',
            'file_deleted' => 'Fail Dipadam',
            'file_moved' => 'Fail Dipindahkan',
            'file_borrowed' => 'Fail Dipinjam',
            'file_returned' => 'Fail Dikembalikan',
            'user_created' => 'Pengguna Dicipta',
            'user_updated' => 'Pengguna Dikemaskini',
            'user_deleted' => 'Pengguna Dipadam',
            'location_created' => 'Lokasi Dicipta',
            'location_updated' => 'Lokasi Dikemaskini',
            'location_deleted' => 'Lokasi Dipadam',
            'login' => 'Log Masuk',
            'logout' => 'Log Keluar',
        ];

        return $actions[$this->action] ?? $this->action;
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeBySubject($query, $subjectType, $subjectId = null)
    {
        $query = $query->where('subject_type', $subjectType);
        
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        
        return $query;
    }

    public static function logActivity($action, $description, $subject = null, $properties = [])
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (empty($log->ip_address)) {
                $log->ip_address = request()->ip();
            }
            
            if (empty($log->user_agent)) {
                $log->user_agent = request()->userAgent();
            }
        });
    }
}