<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BorrowingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'borrower_id',
        'approved_by',
        'purpose',
        'borrowed_date',
        'due_date',
        'returned_date',
        'returned_to',
        'status',
        'notes',
    ];

    protected $casts = [
        'borrowed_date' => 'date',
        'due_date' => 'date',
        'returned_date' => 'date',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    public function isOverdue()
    {
        return $this->status === 'dipinjam' && $this->due_date < now()->toDateString();
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return Carbon::parse($this->due_date)->diffInDays(now());
    }

    public function getDaysRemainingAttribute()
    {
        if ($this->status !== 'dipinjam') {
            return null;
        }

        $daysRemaining = now()->diffInDays(Carbon::parse($this->due_date), false);
        return $daysRemaining >= 0 ? $daysRemaining : 0;
    }

    public function getStatusDisplayAttribute()
    {
        $statuses = [
            'dipinjam' => 'Dipinjam',
            'dikembalikan' => 'Dikembalikan',
            'overdue' => 'Overdue'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'dipinjam');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'dipinjam')->where('due_date', '<', now()->toDateString());
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'dikembalikan');
    }

    public function scopeByBorrower($query, $borrowerId)
    {
        return $query->where('borrower_id', $borrowerId);
    }

    public function scopeByFile($query, $fileId)
    {
        return $query->where('file_id', $fileId);
    }

    public function scopeDueSoon($query, $days = 3)
    {
        return $query->where('status', 'dipinjam')
                    ->where('due_date', '<=', now()->addDays($days)->toDateString())
                    ->where('due_date', '>=', now()->toDateString());
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($borrowing) {
            if (empty($borrowing->borrowed_date)) {
                $borrowing->borrowed_date = now()->toDateString();
            }
        });

        static::created(function ($borrowing) {
            $borrowing->file->update(['status' => 'dipinjam']);

            ActivityLog::create([
                'user_id' => $borrowing->borrower_id,
                'action' => 'file_borrowed',
                'subject_type' => File::class,
                'subject_id' => $borrowing->file_id,
                'description' => "Fail {$borrowing->file->file_id} dipinjam oleh {$borrowing->borrower->name}",
                'properties' => [
                    'purpose' => $borrowing->purpose,
                    'due_date' => $borrowing->due_date,
                ],
            ]);
        });

        static::updated(function ($borrowing) {
            if ($borrowing->isDirty('status') && $borrowing->status === 'dikembalikan') {
                $borrowing->file->update(['status' => 'tersedia']);

                ActivityLog::create([
                    'user_id' => $borrowing->returned_to ?: auth()->id(),
                    'action' => 'file_returned',
                    'subject_type' => File::class,
                    'subject_id' => $borrowing->file_id,
                    'description' => "Fail {$borrowing->file->file_id} dikembalikan oleh {$borrowing->borrower->name}",
                    'properties' => [
                        'returned_date' => $borrowing->returned_date,
                        'notes' => $borrowing->notes,
                    ],
                ]);
            }
        });
    }
}