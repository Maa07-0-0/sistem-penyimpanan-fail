<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BorrowingRecord;
use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BorrowingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,staff_jabatan,staff_pembantu');
    }

    public function index(Request $request)
    {
        $query = BorrowingRecord::with(['file', 'borrower', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('borrower') && !empty($request->borrower)) {
            $query->where('borrower_id', $request->borrower);
        }

        if ($request->has('overdue') && $request->overdue == '1') {
            $query->overdue();
        }

        if ($request->has('due_soon') && $request->due_soon == '1') {
            $query->dueSoon();
        }

        $borrowings = $query->paginate(20);

        $borrowers = \App\Models\User::whereHas('borrowedFiles')->get();

        return view('borrowings.index', compact('borrowings', 'borrowers'));
    }

    public function create(Request $request)
    {
        $fileId = $request->get('file_id');
        $file = null;
        
        if ($fileId) {
            $file = File::findOrFail($fileId);
            if (!$file->canBeBorrowed()) {
                return redirect()->route('files.show', $file)
                    ->with('error', 'Fail ini tidak boleh dipinjam pada masa ini.');
            }
        }

        $availableFiles = File::available()
            ->with('location')
            ->orderBy('title')
            ->get();

        $users = \App\Models\User::where('is_active', true)
            ->whereIn('role', ['admin', 'staff_jabatan', 'staff_pembantu', 'user_view'])
            ->orderBy('name')
            ->get();

        return view('borrowings.create', compact('file', 'availableFiles', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_id' => 'required|exists:files,id',
            'borrower_id' => 'required|exists:users,id',
            'purpose' => 'required|string|max:500',
            'due_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:500',
        ], [
            'file_id.required' => 'Fail diperlukan.',
            'borrower_id.required' => 'Peminjam diperlukan.',
            'purpose.required' => 'Tujuan peminjaman diperlukan.',
            'due_date.required' => 'Tarikh akhir diperlukan.',
            'due_date.after' => 'Tarikh akhir mestilah selepas hari ini.',
        ]);

        $file = File::findOrFail($validated['file_id']);
        
        if (!$file->canBeBorrowed()) {
            return back()->withInput()
                ->with('error', 'Fail ini tidak boleh dipinjam pada masa ini.');
        }

        DB::beginTransaction();
        try {
            $borrowing = BorrowingRecord::create([
                'file_id' => $validated['file_id'],
                'borrower_id' => $validated['borrower_id'],
                'approved_by' => auth()->id(),
                'purpose' => $validated['purpose'],
                'borrowed_date' => now()->toDateString(),
                'due_date' => $validated['due_date'],
                'notes' => $validated['notes'],
                'status' => 'dipinjam',
            ]);

            DB::commit();
            
            return redirect()->route('borrowings.show', $borrowing)
                ->with('success', 'Peminjaman fail berjaya direkodkan.');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()
                ->with('error', 'Ralat berlaku semasa merekodkan peminjaman.');
        }
    }

    public function show(BorrowingRecord $borrowing)
    {
        $borrowing->load(['file.location', 'borrower', 'approver', 'returner']);
        
        return view('borrowings.show', compact('borrowing'));
    }

    public function edit(BorrowingRecord $borrowing)
    {
        if ($borrowing->status !== 'dipinjam') {
            return redirect()->route('borrowings.show', $borrowing)
                ->with('error', 'Hanya peminjaman aktif boleh dikemaskini.');
        }

        return view('borrowings.edit', compact('borrowing'));
    }

    public function update(Request $request, BorrowingRecord $borrowing)
    {
        if ($borrowing->status !== 'dipinjam') {
            return redirect()->route('borrowings.show', $borrowing)
                ->with('error', 'Hanya peminjaman aktif boleh dikemaskini.');
        }

        $validated = $request->validate([
            'purpose' => 'required|string|max:500',
            'due_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldData = $borrowing->toArray();
        
        $borrowing->update($validated);

        ActivityLog::logActivity(
            'borrowing_updated',
            "Peminjaman fail '{$borrowing->file->title}' telah dikemaskini",
            $borrowing,
            ['old' => $oldData, 'new' => $validated]
        );

        return redirect()->route('borrowings.show', $borrowing)
            ->with('success', 'Peminjaman berjaya dikemaskini.');
    }

    public function returnFile(Request $request, BorrowingRecord $borrowing)
    {
        if ($borrowing->status !== 'dipinjam') {
            return back()->with('error', 'Fail ini sudah dikembalikan.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $borrowing->update([
                'returned_date' => now()->toDateString(),
                'returned_to' => auth()->id(),
                'status' => 'dikembalikan',
                'notes' => $validated['notes'],
            ]);

            DB::commit();
            
            return redirect()->route('borrowings.show', $borrowing)
                ->with('success', 'Fail berjaya dikembalikan.');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Ralat berlaku semasa memproses pemulangan fail.');
        }
    }

    public function destroy(BorrowingRecord $borrowing)
    {
        if ($borrowing->status === 'dipinjam') {
            return back()->with('error', 'Peminjaman aktif tidak boleh dipadam.');
        }

        ActivityLog::logActivity(
            'borrowing_deleted',
            "Rekod peminjaman fail '{$borrowing->file->title}' telah dipadam",
            $borrowing
        );

        $borrowing->delete();

        return redirect()->route('borrowings.index')
            ->with('success', 'Rekod peminjaman berjaya dipadam.');
    }
}