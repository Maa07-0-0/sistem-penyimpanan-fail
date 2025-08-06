<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'from_location_id',
        'to_location_id',
        'moved_by',
        'reason',
        'moved_at',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function mover()
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('moved_at', '>=', now()->subDays($days));
    }

    public function scopeByFile($query, $fileId)
    {
        return $query->where('file_id', $fileId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('moved_by', $userId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movement) {
            if (empty($movement->moved_at)) {
                $movement->moved_at = now();
            }
        });

        static::created(function ($movement) {
            ActivityLog::create([
                'user_id' => $movement->moved_by,
                'action' => 'file_moved',
                'subject_type' => File::class,
                'subject_id' => $movement->file_id,
                'description' => "Fail {$movement->file->file_id} dipindahkan dari {$movement->fromLocation?->full_location} ke {$movement->toLocation->full_location}",
                'properties' => [
                    'from_location' => $movement->fromLocation?->full_location,
                    'to_location' => $movement->toLocation->full_location,
                    'reason' => $movement->reason,
                ],
            ]);
        });
    }
}