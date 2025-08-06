<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'room',
        'rack',
        'slot',
        'description',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function fromMovements()
    {
        return $this->hasMany(FileMovement::class, 'from_location_id');
    }

    public function toMovements()
    {
        return $this->hasMany(FileMovement::class, 'to_location_id');
    }

    public function getFullLocationAttribute()
    {
        return "{$this->room} - {$this->rack} - {$this->slot}";
    }

    public function getLocationCodeAttribute()
    {
        return strtoupper(substr($this->room, 0, 1) . substr($this->rack, 0, 1) . $this->slot);
    }

    public static function getAvailableLocations()
    {
        return self::where('is_available', true)
                   ->whereDoesntHave('files', function ($query) {
                       $query->whereIn('status', ['tersedia', 'dipinjam']);
                   })
                   ->get();
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByRoom($query, $room)
    {
        return $query->where('room', $room);
    }

    public function scopeByRack($query, $rack)
    {
        return $query->where('rack', $rack);
    }
}