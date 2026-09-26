<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| 共通・一般ユーザー 認証後ルーティング
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // 打刻画面
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    // 勤怠一覧画面（一般ユーザー）
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    // 勤怠詳細画面（一般ユーザー）
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/{id}', [AttendanceController::class, 'correct'])->name('attendance.correct');

    // 申請一覧画面（一般ユーザー）
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'list'])->name('request.list');
});

/*
|--------------------------------------------------------------------------
| ログイン用ルーティング
|--------------------------------------------------------------------------
*/
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest')->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');

/*
|--------------------------------------------------------------------------
| 管理者専用ルーティング
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:admin'])->group(function () {
    // 勤怠一覧画面（管理者）
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'dailyList'])->name('admin.attendance.list');

    // 勤怠詳細画面（管理者）
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'detail'])->name('admin.attendance.detail');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'update'])->name('admin.attendance.update');

    // スタッフ一覧画面（管理者）
    Route::get('/admin/staff/list', [AdminAttendanceController::class, 'staffList'])->name('admin.staff.list');

    // スタッフ別勤怠一覧画面（管理者）
    Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendance'])->name('admin.staff.attendance');

    // 申請一覧画面（管理者）
    Route::get('/stamp_correction_request/list/admin', [AdminAttendanceController::class, 'requestList'])->name('admin.request.list');

    // 修正申請承認画面 & 承認処理（管理者）
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceController::class, 'showApproveForm'])->name('admin.request.approve.detail');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceController::class, 'approve'])->name('admin.request.approve');

    Route::post('/export', [AdminAttendanceController::class, 'exportCsv'])->name('admin.export');

});