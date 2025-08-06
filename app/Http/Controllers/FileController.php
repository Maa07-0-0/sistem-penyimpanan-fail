<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Location;
use App\Models\ActivityLog;
use App\Models\FileMovement;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,staff_jabatan,staff_pembantu')->except(['index', 'show', 'search']);
    }

    public function index(Request $request)
    {
        $query = File::with(['location', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $query->search($request->search);
        }

        if ($request->has('department') && !empty($request->department)) {
            $query->byDepartment($request->department);
        }

        if ($request->has('document_type') && !empty($request->document_type)) {
            $query->byType($request->document_type);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('year') && !empty($request->year)) {
            $query->byYear($request->year);
        }

        $files = $query->paginate(20);

        $departments = File::distinct()->pluck('department')->sort();
        $years = File::distinct()->pluck('document_year')->sort()->reverse();

        return view('files.index', compact('files', 'departments', 'years'));
    }

    public function create()
    {
        $this->authorize('create', File::class);
        
        $locations = Location::available()->get();
        $departments = ['Pentadbiran', 'Kewangan', 'Pembangunan', 'Kejuruteraan', 'Perancangan'];
        
        return view('files.create', compact('locations', 'departments'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', File::class);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'document_year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'department' => 'required|string|max:100',
            'document_type' => 'required|in:surat_rasmi,perjanjian,permit,laporan,lain_lain',
            'description' => 'nullable|string',
            'location_id' => 'required|exists:locations,id',
        ], [
            'title.required' => 'Tajuk fail diperlukan.',
            'document_year.required' => 'Tahun dokumen diperlukan.',
            'department.required' => 'Jabatan diperlukan.',
            'document_type.required' => 'Jenis dokumen diperlukan.',
            'location_id.required' => 'Lokasi diperlukan.',
            'location_id.exists' => 'Lokasi tidak sah.',
        ]);

        DB::beginTransaction();
        try {
            $file = File::create([
                'title' => $validated['title'],
                'reference_number' => $validated['reference_number'],
                'document_year' => $validated['document_year'],
                'department' => $validated['department'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'],
                'location_id' => $validated['location_id'],
                'created_by' => auth()->id(),
                'status' => 'tersedia',
            ]);

            ActivityLog::logActivity(
                'file_created',
                "Fail baharu '{$file->title}' dengan ID {$file->file_id} telah dicipta",
                $file,
                $validated
            );

            DB::commit();
            
            return redirect()->route('files.show', $file)
                ->with('success', 'Fail berjaya dicipta dengan ID: ' . $file->file_id);
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Ralat berlaku semasa mencipta fail.');
        }
    }

    public function show(File $file)
    {
        $file->load(['location', 'creator', 'updater', 'movements.fromLocation', 'movements.toLocation', 'movements.mover', 'borrowingRecords.borrower', 'currentBorrowing.borrower']);
        
        return view('files.show', compact('file'));
    }

    public function edit(File $file)
    {
        $this->authorize('update', $file);
        
        $locations = Location::available()->get();
        $departments = ['Pentadbiran', 'Kewangan', 'Pembangunan', 'Kejuruteraan', 'Perancangan'];
        
        return view('files.edit', compact('file', 'locations', 'departments'));
    }

    public function update(Request $request, File $file)
    {
        $this->authorize('update', $file);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'document_year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'department' => 'required|string|max:100',
            'document_type' => 'required|in:surat_rasmi,perjanjian,permit,laporan,lain_lain',
            'description' => 'nullable|string',
            'status' => 'required|in:tersedia,dipinjam,arkib,tidak_aktif',
        ]);

        $oldData = $file->toArray();
        
        $file->update([
            'title' => $validated['title'],
            'reference_number' => $validated['reference_number'],
            'document_year' => $validated['document_year'],
            'department' => $validated['department'],
            'document_type' => $validated['document_type'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'updated_by' => auth()->id(),
        ]);

        ActivityLog::logActivity(
            'file_updated',
            "Fail '{$file->title}' (ID: {$file->file_id}) telah dikemaskini",
            $file,
            ['old' => $oldData, 'new' => $validated]
        );

        return redirect()->route('files.show', $file)
            ->with('success', 'Fail berjaya dikemaskini.');
    }

    public function destroy(File $file)
    {
        $this->authorize('delete', $file);
        
        if ($file->borrowingRecords()->active()->exists()) {
            return back()->with('error', 'Fail tidak boleh dipadam kerana sedang dipinjam.');
        }

        ActivityLog::logActivity(
            'file_deleted',
            "Fail '{$file->title}' (ID: {$file->file_id}) telah dipadam",
            $file
        );

        $file->delete();

        return redirect()->route('files.index')
            ->with('success', 'Fail berjaya dipadam.');
    }

    public function search(Request $request)
    {
        $query = File::with(['location', 'creator']);

        if ($request->has('q') && !empty($request->q)) {
            $query->search($request->q);
        }

        $files = $query->paginate(20);

        if ($request->ajax()) {
            return view('files.partials.search-results', compact('files'))->render();
        }

        return view('files.search', compact('files'));
    }

    public function move(Request $request, File $file)
    {
        $this->authorize('update', $file);
        
        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($file->location_id == $validated['location_id']) {
            return back()->with('error', 'Fail sudah berada di lokasi tersebut.');
        }

        DB::beginTransaction();
        try {
            $oldLocationId = $file->location_id;
            
            $file->update(['location_id' => $validated['location_id']]);

            FileMovement::create([
                'file_id' => $file->id,
                'from_location_id' => $oldLocationId,
                'to_location_id' => $validated['location_id'],
                'moved_by' => auth()->id(),
                'reason' => $validated['reason'],
                'moved_at' => now(),
            ]);

            DB::commit();
            
            return back()->with('success', 'Fail berjaya dipindahkan ke lokasi baharu.');
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Ralat berlaku semasa memindahkan fail.');
        }
    }
}