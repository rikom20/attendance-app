<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampCorrectionRequest;
use App\Models\Attendance;
use App\Models\RestTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // 1. 打刻画面の表示
    public function index()
    {
        $user = Auth::user();
        $now = Carbon::now();
        $today = $now->format('Y-m-d');

        // 本日の勤怠データを取得
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // 数値の status からステータスを指定
        $statusNum = $attendance ? $attendance->status : 0;

        $statusText = match ($statusNum) {
            1 => '出勤中',
            2 => '休憩中',
            3 => '退勤済',
            default => '勤務外',
        };

        $user->attendance_status = $statusText;

        return view('user.attendance-register', [
            'user' => $user,
            'attendance' => $attendance,
            'status' => $statusNum,
            'formattedDate' => $now->isoFormat('YYYY年M月D日(ddd)'),
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    // 2. 打刻ボタンの送信処理
    public function store(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $action = $request->input('action'); 

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        switch ($action) {
            case 'clock_in': // 出勤
                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $today,
                    'clock_in' => $now,
                    'status' => 1, // 出勤中
                ]);
                break;

            case 'break_in': // 休憩入
                if ($attendance) {
                    $attendance->update(['status' => 2]); // 休憩中
                    RestTime::create([
                        'attendance_id' => $attendance->id,
                        'start_time' => $now,
                    ]);
                }
                break;

            case 'break_out': // 休憩戻
                if ($attendance) {
                    $attendance->update(['status' => 1]); // 出勤中
                    // 終了時間が null（未完了）の休憩レコードを更新
                    $rest = RestTime::where('attendance_id', $attendance->id)
                        ->whereNull('end_time')
                        ->first();
                    if ($rest) {
                        $rest->update(['end_time' => $now]);
                    }
                }
                break;

            case 'clock_out': // 退勤
                if ($attendance) {
                    $attendance->update([
                        'clock_out' => $now,
                        'status' => 3, // 退勤済
                    ]);
                }
                break;
        }

        return redirect()->route('attendance.index');
    }

    // 3. 勤怠一覧画面（月次）
    public function list(Request $request)
    {
        $user = Auth::user();

        // クエリパラメーター（?date=2026-08）がなければ当月を設定
        $targetMonth = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now();

        $attendances = Attendance::with('restTimes')
            ->where('user_id', $user->id)
            ->whereYear('date', $targetMonth->year)
            ->whereMonth('date', $targetMonth->month)
            ->orderBy('date', 'asc')
            ->get();

        return view('user.user-attendance-list', [
            'formattedAttendanceRecords' => $attendances,
            'date' => $targetMonth,
            'previousMonth' => $targetMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $targetMonth->copy()->addMonth()->format('Y-m'),
        ]);
    }

    // 4. 勤怠詳細画面
    public function show($id)
    {
        $attendance = Attendance::with(['restTimes', 'attendanceCorrectRequests.proposedBreaks', 'user'])->findOrFail($id);

        $pendingRequest = $attendance->attendanceCorrectRequests()
            ->where('status', 0)
            ->first();

        // Carbon で日付を分解・整形
        $date = Carbon::parse($attendance->date);
        $user = $attendance->user ?? Auth::user();

        $breakSource = ($pendingRequest && $pendingRequest->proposedBreaks->isNotEmpty())
            ? $pendingRequest->proposedBreaks
            : $attendance->restTimes;

        // 休憩時間の配列（Bladeが参照する 'break_in' と 'break_out' にキー名を変更）
        $breaks = $breakSource->map(function ($rest) {
            return [
                'break_in'  => $rest->start_time ? Carbon::parse($rest->start_time)->format('H:i') : '',
                'break_out' => $rest->end_time ? Carbon::parse($rest->end_time)->format('H:i') : '',
            ];
        })->toArray();

        $data = [
            'id' => $attendance->id,
            'name' => $user->name ?? '',
            'year' => $date->format('Y年'),
            'date' => $date->format('m月d日'),
            'clock_in' => $pendingRequest
                ? Carbon::parse($pendingRequest->clock_in)->format('H:i')
                : ($attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : ''),
            'clock_out' => $pendingRequest
                ? Carbon::parse($pendingRequest->clock_out)->format('H:i')
                : ($attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : ''),
            'breaks' => $breaks,
            'comment' => $pendingRequest ? $pendingRequest->remarks : ($attendance->remarks ?? ''),
            'application' => $pendingRequest, 
        ];

        return view('user.user-detail', [
            'user' => $user,
            'data' => $data,
            'attendance' => $attendance,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    // 5. 勤怠修正申請の処理
    public function correct(StampCorrectionRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        // 既に承認待ちの申請がある場合は修正不可
        $hasPending = $attendance->attendanceCorrectRequests()
            ->where('status', 0)
            ->exists();

        if ($hasPending) {
            return redirect()->back()->withErrors(['message' => '承認待ちの修正申請が既に存在します。']);
        }

        $date = Carbon::parse($attendance->date)->format('Y-m-d');
        $clockInTime = "{$date} {$request->new_clock_in}:00";
        $clockOutTime = "{$date} {$request->new_clock_out}:00";

        $correctRequest = $attendance->attendanceCorrectRequests()->create([
            'user_id'   => Auth::id(),
            'clock_in'  => $clockInTime,
            'clock_out' => $clockOutTime,
            'remarks'   => $request->input('comment'),
            'status'    => 0,
        ]);

        // rest_times に申請中の休憩として保存
        if ($request->has('new_break_in')) {
            foreach ($request->new_break_in as $index => $breakIn) {
                $breakOut = $request->new_break_out[$index] ?? null;
                if (!empty($breakIn) && !empty($breakOut)) {
                    RestTime::create([
                        'attendance_id'                 => $attendance->id,
                        'attendance_correct_request_id' => $correctRequest->id,
                        'start_time'                    => "{$date} {$breakIn}:00",
                        'end_time'                       => "{$date} {$breakOut}:00",
                    ]);
                }
            }
        }

        return redirect()->route('attendance.show', ['id' => $id]);
    }
}