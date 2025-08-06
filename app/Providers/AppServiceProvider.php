<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Define Gates for authorization
        Gate::define('create-files', function ($user) {
            return in_array($user->role, ['admin', 'staff_jabatan', 'staff_pembantu']);
        });

        Gate::define('manage-locations', function ($user) {
            return in_array($user->role, ['admin', 'staff_jabatan']);
        });

        Gate::define('manage-borrowings', function ($user) {
            return in_array($user->role, ['admin', 'staff_jabatan', 'staff_pembantu']);
        });

        Gate::define('view-reports', function ($user) {
            return in_array($user->role, ['admin', 'staff_jabatan']);
        });

        Gate::define('manage-users', function ($user) {
            return $user->role === 'admin';
        });
    }
}