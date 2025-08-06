<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'file_id',
        'title',
        'reference_number',
        'document_year',
        'department',
        'document_type',
        'description',
        'status',
        'location_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'document_year' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movements()
    {
        return $this->hasMany(FileMovement::class);
    }

    public function borrowingRecords()
    {
        return $this->hasMany(BorrowingRecord::class);
    }

    public function currentBorrowing()
    {
        return $this->hasOne(BorrowingRecord::class)->where('status', 'dipinjam');
    }

    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public static function generateFileId()
    {
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return 'FAIL' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function getDocumentTypeDisplayAttribute()
    {
        $types = [
            'surat_rasmi' => 'Surat Rasmi',
            'perjanjian' => 'Perjanjian',
            'permit' => 'Permit',
            'laporan' => 'Laporan',
            'lain_lain' => 'Lain-lain'
        ];

        return $types[$this->document_type] ?? $this->document_type;
    }

    public function getStatusDisplayAttribute()
    {
        $statuses = [
            'tersedia' => 'Tersedia',
            'dipinjam' => 'Dipinjam',
            'arkib' => 'Arkib',
            'tidak_aktif' => 'Tidak Aktif'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function isAvailable()
    {
        return $this->status === 'tersedia';
    }

    public function isBorrowed()
    {
        return $this->status === 'dipinjam';
    }

    public function canBeBorrowed()
    {
        return $this->status === 'tersedia';
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'tersedia');
    }

    public function scopeBorrowed($query)
    {
        return $query->where('status', 'dipinjam');
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('document_year', $year);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('reference_number', 'like', "%{$search}%")
              ->orWhere('file_id', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($file) {
            if (empty($file->file_id)) {
                $file->file_id = self::generateFileId();
            }
        });
    }
}