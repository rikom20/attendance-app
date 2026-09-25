<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse; 
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1. 画面（ビュー）の指定
        Fortify::registerView(function () {
            return view('user.register');
        });

        Fortify::loginView(function (Request $request) {
            if ($request->is('admin/*') || $request->is('admin')) {
                return view('admin.admin-login');
            }
            return view('user.user-login');
        });

        // 2. FormRequest（バリデーション）の適用
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

        // 3. 新規ユーザー登録クラスの指定
        Fortify::createUsersUsing(CreateNewUser::class);

        // 4. カスタム認証処理（管理者権限チェック）
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {

                // 管理者ログイン画面（/admin/login）からの送信の場合
                if ($request->is('admin/*') || $request->is('admin') || $request->routeIs('admin.*')) {
                    if (!$user->admin_status) {
                        return null;
                    }
                }

                return $user;
            }

            return null;
        });

        // 5. ログアウト後の遷移先指定
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            public function toResponse($request)
            {
                $referer = $request->headers->get('referer') ?? '';

                if ($request->is('admin/*') || $request->is('admin') || str_contains($referer, '/admin')) {
                    return redirect('/admin/login');
                }

                return redirect('/login');
            }
        });

        // 6. ログイン成功後のリダイレクト先の分岐処理
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                $user = Auth::user();

                // 管理者の場合は「勤怠一覧画面（管理者）」へ遷移
                if ($user && $user->admin_status) {
                    return redirect('/admin/attendance/list');
                }

                // 一般ユーザーは「出勤登録画面（一般ユーザー）」へ遷移
                return redirect('/attendance');
            }
        });
    }
}