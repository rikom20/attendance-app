<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // 管理者ページのアクセスの場合は /admin/login へ
        if ($request->is('admin/*') || $request->is('admin')) {
            return route('admin.login');
        }

        // それ以外は一般ユーザー用の /login へ
        return route('login');
    }
}