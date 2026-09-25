<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate; 
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //admin 権限（Gate）の定義を追加
        Gate::define('admin', function (User $user) {
            return (bool) $user->admin_status;
        });
    }
}