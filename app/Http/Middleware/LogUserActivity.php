<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && $request->isMethod('post')) {
            $this->logActivity($request);
        }

        return $response;
    }

    private function logActivity(Request $request)
    {
        $route = $request->route();
        $action = $route ? $route->getActionName() : 'unknown';
        $method = $request->method();
        $path = $request->path();

        $description = $this->generateDescription($method, $path, $action);

        if ($description) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $this->getActionType($path),
                'description' => $description,
                'properties' => [
                    'method' => $method,
                    'path' => $path,
                    'action' => $action,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }
    }

    private function generateDescription($method, $path, $action)
    {
        $descriptions = [
            'files' => 'Menguruskan fail',
            'locations' => 'Menguruskan lokasi',
            'borrowings' => 'Menguruskan peminjaman',
            'users' => 'Menguruskan pengguna',
            'login' => 'Log masuk ke sistem',
            'logout' => 'Log keluar dari sistem',
        ];

        foreach ($descriptions as $key => $desc) {
            if (strpos($path, $key) !== false) {
                return $desc;
            }
        }

        return null;
    }

    private function getActionType($path)
    {
        if (strpos($path, 'files') !== false) return 'file_action';
        if (strpos($path, 'locations') !== false) return 'location_action';
        if (strpos($path, 'borrowings') !== false) return 'borrowing_action';
        if (strpos($path, 'users') !== false) return 'user_action';
        if (strpos($path, 'login') !== false) return 'login';
        if (strpos($path, 'logout') !== false) return 'logout';

        return 'general_action';
    }
}