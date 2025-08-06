<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\ActivityLog;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,staff_jabatan')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Location::withCount('files')
            ->orderBy('room')
            ->orderBy('rack')
            ->orderBy('slot');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('room', 'like', "%{$search}%")
                  ->orWhere('rack', 'like', "%{$search}%")
                  ->orWhere('slot', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('room') && !empty($request->room)) {
            $query->byRoom($request->room);
        }

        if ($request->has('status')) {
            if ($request->status === 'available') {
                $query->available();
            } elseif ($request->status === 'occupied') {
                $query->has('files');
            }
        }

        $locations = $query->paginate(20);

        $rooms = Location::distinct()->pluck('room')->sort();

        return view('locations.index', compact('locations', 'rooms'));
    }

    public function create()
    {
        $this->authorize('create', Location::class);
        
        $rooms = Location::distinct()->pluck('room')->sort();
        
        return view('locations.create', compact('rooms'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Location::class);
        
        $validated = $request->validate([
            'room' => 'required|string|max:50',
            'rack' => 'required|string|max:50',
            'slot' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_available' => 'boolean',
        ], [
            'room.required' => 'Bilik diperlukan.',
            'rack.required' => 'Rak diperlukan.',
            'slot.required' => 'Slot diperlukan.',
        ]);

        $exists = Location::where('room', $validated['room'])
            ->where('rack', $validated['rack'])
            ->where('slot', $validated['slot'])
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->with('error', 'Lokasi dengan kombinasi Bilik-Rak-Slot ini sudah wujud.');
        }

        $location = Location::create($validated);

        ActivityLog::logActivity(
            'location_created',
            "Lokasi baharu '{$location->full_location}' telah dicipta",
            $location,
            $validated
        );

        return redirect()->route('locations.show', $location)
            ->with('success', 'Lokasi berjaya dicipta.');
    }

    public function show(Location $location)
    {
        $location->load(['files.creator', 'files.currentBorrowing.borrower']);
        
        return view('locations.show', compact('location'));
    }

    public function edit(Location $location)
    {
        $this->authorize('update', $location);
        
        $rooms = Location::distinct()->pluck('room')->sort();
        
        return view('locations.edit', compact('location', 'rooms'));
    }

    public function update(Request $request, Location $location)
    {
        $this->authorize('update', $location);
        
        $validated = $request->validate([
            'room' => 'required|string|max:50',
            'rack' => 'required|string|max:50',
            'slot' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_available' => 'boolean',
        ]);

        $exists = Location::where('room', $validated['room'])
            ->where('rack', $validated['rack'])
            ->where('slot', $validated['slot'])
            ->where('id', '!=', $location->id)
            ->exists();

        if ($exists) {
            return back()->withInput()
                ->with('error', 'Lokasi dengan kombinasi Bilik-Rak-Slot ini sudah wujud.');
        }

        $oldData = $location->toArray();
        
        $location->update($validated);

        ActivityLog::logActivity(
            'location_updated',
            "Lokasi '{$location->full_location}' telah dikemaskini",
            $location,
            ['old' => $oldData, 'new' => $validated]
        );

        return redirect()->route('locations.show', $location)
            ->with('success', 'Lokasi berjaya dikemaskini.');
    }

    public function destroy(Location $location)
    {
        $this->authorize('delete', $location);
        
        if ($location->files()->count() > 0) {
            return back()->with('error', 'Lokasi tidak boleh dipadam kerana masih mengandungi fail.');
        }

        ActivityLog::logActivity(
            'location_deleted',
            "Lokasi '{$location->full_location}' telah dipadam",
            $location
        );

        $location->delete();

        return redirect()->route('locations.index')
            ->with('success', 'Lokasi berjaya dipadam.');
    }

    public function files(Location $location)
    {
        $files = $location->files()
            ->with(['creator', 'currentBorrowing.borrower'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('locations.files', compact('location', 'files'));
    }
}