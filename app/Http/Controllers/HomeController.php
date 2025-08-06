<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\BorrowingRecord;
use App\Models\Location;
use App\Models\User;
use App\Models\ActivityLog;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = auth()->user();
        
        $stats = [
            'total_files' => File::count(),
            'available_files' => File::where('status', 'tersedia')->count(),
            'borrowed_files' => File::where('status', 'dipinjam')->count(),
            'total_locations' => Location::count(),
            'total_users' => User::where('is_active', true)->count(),
        ];

        if ($user->canViewReports()) {
            $stats['overdue_files'] = BorrowingRecord::overdue()->count();
            $stats['due_soon'] = BorrowingRecord::dueSoon()->count();
        }

        $recentActivities = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($user->role === 'user_view') {
            $userBorrowings = BorrowingRecord::with('file')
                ->where('borrower_id', $user->id)
                ->active()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        } else {
            $userBorrowings = collect();
        }

        $chartData = [];
        if ($user->canViewReports()) {
            $chartData = $this->getChartData();
        }

        return view('dashboard', compact('stats', 'recentActivities', 'userBorrowings', 'chartData'));
    }

    private function getChartData()
    {
        $months = [];
        $filesCreated = [];
        $filesBorrowed = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = $date->format('M Y');
            
            $filesCreated[] = File::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            $filesBorrowed[] = BorrowingRecord::whereYear('borrowed_date', $date->year)
                ->whereMonth('borrowed_date', $date->month)
                ->count();
        }

        return [
            'months' => $months,
            'files_created' => $filesCreated,
            'files_borrowed' => $filesBorrowed,
        ];
    }
}