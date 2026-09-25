<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    /**
     * 管理者ログアウト処理
     */
    public function logout(Request $request)
    {
        // ログアウト処理
        Auth::guard('web')->logout();

        // セッションの破棄とトークンの再生成
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 管理者ログイン画面へリダイレクト
        return redirect('/admin/login');
    }
}