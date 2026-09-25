<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StampCorrectionRequestController extends Controller
{
    /**
     * 1. 一般ユーザー：修正申請の保存処理
     */
    public function store(StampCorrectionRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $date = Carbon::parse($attendance->date)->format('Y-m-d');

        $clockInTime = "{$date} {$request->new_clock_in}:00";
        $clockOutTime = "{$date} {$request->new_clock_out}:00";

        // フォームから送信された休憩データの収集
        $breakTimes = [];
        if ($request->has('new_break_in')) {
            foreach ($request->new_break_in as $index => $breakIn) {
                $breakOut = $request->new_break_out[$index] ?? null;
                if (!empty($breakIn) && !empty($breakOut)) {
                    $breakTimes[] = [
                        'start_time' => "{$date} {$breakIn}:00",
                        'end_time'   => "{$date} {$breakOut}:00",
                    ];
                }
            }
        }

        AttendanceCorrectRequest::create([
            'user_id'       => Auth::id(),
            'attendance_id' => $attendance->id,
            'clock_in'      => $clockInTime,
            'clock_out'     => $clockOutTime,
            'break_times'   => !empty($breakTimes) ? $breakTimes : null,
            'remarks'       => $request->comment,
            'status'        => 0, // 0: 承認待ち
        ]);

        return redirect()->route('request.list')->with('success', '修正申請を送信しました。');
    }

    /**
     * 2. 一般ユーザー：申請一覧画面表示
     */
    public function list()
    {
        $user = Auth::user();

        // ★管理者の場合は管理者用一覧へリダイレクト
        if ($user && ($user->admin_status || $user->can('admin'))) {
            return redirect()->route('admin.request.list');
        }

        // ログインユーザー自身の申請一覧を取得
        $requests = AttendanceCorrectRequest::with(['user', 'attendance'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // user.user-application-list 用のデータ整形
        $formattedApplications = $requests->map(function ($req) {
            $statusText = match ((int) $req->status) {
                0       => '承認待ち',
                1       => '承認済み',
                default => '承認待ち',
            };

            $attendanceDate = $req->attendance
                ? Carbon::parse($req->attendance->date)->format('Y/m/d')
                : ($req->clock_in ? Carbon::parse($req->clock_in)->format('Y/m/d') : '');

            $applicationDate = $req->created_at
                ? Carbon::parse($req->created_at)->format('Y/m/d')
                : '';

            return [
                'id'               => $req->id,
                'attendance_id'    => $req->attendance_id,
                'approval_status'  => $statusText,
                'date'             => $attendanceDate,
                'comment'          => $req->remarks,
                'application_date' => $applicationDate,
                'user_name'        => $req->user ? $req->user->name : '',
            ];
        });

        $applications = $requests->map(function ($req) {
            $req->approval_status = ((int) $req->status === 1) ? '承認済み' : '承認待ち';
            $req->comment = $req->remarks;
            $req->application_date = $req->created_at;
            $req->AttendanceRecord = $req->attendance;

            return $req;
        });

        return view('user.user-application-list', [
            'user'                  => $user,
            'formattedApplications' => $formattedApplications,
            'applications'          => $applications,
        ]);
    }

    /**
     * 3. 一般ユーザー：申請詳細画面表示
     */
    public function show($id)
    {
        $user = Auth::user();

        // IDが申請IDか勤怠IDかの両方に対応して取得
        $correctRequest = AttendanceCorrectRequest::with(['attendance.restTimes', 'user'])
            ->where('user_id', $user->id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('attendance_id', $id);
            })
            ->orderBy('created_at', 'desc')
            ->firstOrFail();

        $attendance = $correctRequest->attendance;
        $date = $attendance ? Carbon::parse($attendance->date) : Carbon::parse($correctRequest->clock_in);

        // 休憩データの取得
        $breaks = [];
        if (!empty($correctRequest->break_times) && is_array($correctRequest->break_times)) {
            foreach ($correctRequest->break_times as $b) {
                $breaks[] = [
                    'break_in'  => isset($b['start_time']) ? Carbon::parse($b['start_time'])->format('H:i') : '',
                    'break_out' => isset($b['end_time']) ? Carbon::parse($b['end_time'])->format('H:i') : '',
                ];
            }
        } elseif ($attendance && $attendance->restTimes) {
            $breaks = $attendance->restTimes->map(function ($rest) {
                return [
                    'break_in'  => $rest->start_time ? Carbon::parse($rest->start_time)->format('H:i') : '',
                    'break_out' => $rest->end_time ? Carbon::parse($rest->end_time)->format('H:i') : '',
                ];
            })->toArray();
        }

        $data = [
            'id'              => $attendance ? $attendance->id : $correctRequest->attendance_id,
            'name'            => $user->name ?? '',
            'year'            => $date->format('Y年'),
            'date'            => $date->format('m月d日'),
            'clock_in'        => $correctRequest->clock_in ? Carbon::parse($correctRequest->clock_in)->format('H:i') : '',
            'clock_out'       => $correctRequest->clock_out ? Carbon::parse($correctRequest->clock_out)->format('H:i') : '',
            'breaks'          => $breaks,
            'comment'         => $correctRequest->remarks,
            'approval_status' => $correctRequest->status,
            'application'     => $correctRequest,
        ];

        return view('user.user-detail', [
            'user'           => $user,
            'data'           => $data,
            'attendance'     => $attendance,
            'pendingRequest' => $correctRequest,
        ]);
    }
}