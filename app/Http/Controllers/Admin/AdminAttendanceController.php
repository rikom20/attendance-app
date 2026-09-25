<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectRequest;
use App\Models\RestTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    /**
     * 1. 日別勤怠一覧画面
     * 指定された日付（未指定なら本日）の全一般ユーザーの勤怠一覧を表示
     */
    public function dailyList(Request $request)
    {
        $targetDate = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now();

        $users = User::where('admin_status', false)->get();

        $attendances = Attendance::with(['user', 'restTimes'])
            ->whereHas('user', function ($query) {
                $query->where('admin_status', false);
            })
            ->whereDate('date', $targetDate->format('Y-m-d'))
            ->get();

        $attendanceRecords = $attendances;

        return view('admin.admin-attendance-list', [
            'users'             => $users,
            'attendanceRecords' => $attendanceRecords,
            'attendances'       => $attendances,
            'currentDate'       => $targetDate->format('Y-m-d'),
            'formattedDate'     => $targetDate->isoFormat('YYYY年M月D日(ddd)'),
            'previousDay'       => $targetDate->copy()->subDay()->format('Y-m-d'),
            'nextDay'           => $targetDate->copy()->addDay()->format('Y-m-d'),
            'date'              => $targetDate,
        ]);
    }

    /**
     * 2. スタッフ一覧画面
     * 一般ユーザーの一覧を表示
     */
    public function staffList()
    {
        $staffs = User::where('admin_status', false)->get();

        return view('admin.staff-list', [
            'users' => $staffs,
        ]);
    }

    /**
     * 3. スタッフ別月次勤怠一覧画面
     * 特定のスタッフの1か月分の勤怠を表示
     */
    public function staffAttendance(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $targetMonth = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now();

        $attendances = Attendance::with('restTimes')
            ->where('user_id', $user->id)
            ->whereYear('date', $targetMonth->year)
            ->whereMonth('date', $targetMonth->month)
            ->get();

        $daysInMonth = $targetMonth->daysInMonth;
        $formattedAttendanceRecords = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = Carbon::createFromDate($targetMonth->year, $targetMonth->month, $day);
            $formattedCurrentDate = $currentDate->format('Y-m-d');

            $attendance = $attendances->first(function ($item) use ($formattedCurrentDate) {
                return Carbon::parse($item->date)->format('Y-m-d') === $formattedCurrentDate;
            });

            $totalBreakTime = null;
            $totalTime = null;

            if ($attendance && $attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalRestMinutes = 0;
                foreach ($attendance->restTimes as $rest) {
                    if ($rest->start_time && $rest->end_time) {
                        $totalRestMinutes += Carbon::parse($rest->end_time)->diffInMinutes(Carbon::parse($rest->start_time));
                    }
                }

                $totalWorkMinutes = $clockOut->diffInMinutes($clockIn) - $totalRestMinutes;

                if ($totalRestMinutes > 0) {
                    $totalBreakTime = sprintf('%d:%02d', floor($totalRestMinutes / 60), $totalRestMinutes % 60);
                }
                if ($totalWorkMinutes > 0) {
                    $totalTime = sprintf('%d:%02d', floor($totalWorkMinutes / 60), max(0, $totalWorkMinutes % 60));
                }
            }

            $formattedAttendanceRecords[] = [
                'id'               => $attendance->id ?? null,
                'date'             => $currentDate->format('m/d') . '(' . ['日', '月', '火', '水', '木', '金', '土'][$currentDate->dayOfWeek] . ')',
                'clock_in'         => ($attendance && $attendance->clock_in) ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out'        => ($attendance && $attendance->clock_out) ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $totalBreakTime,
                'total_time'       => $totalTime,
            ];
        }

        return view('admin.staff-attendance-list', [
            'user'                       => $user,
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
            'date'                       => $targetMonth,
            'previousMonth'              => $targetMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth'                  => $targetMonth->copy()->addMonth()->format('Y-m'),
        ]);
    }

    /**
     * 4. 管理者用 勤怠詳細画面表示
     */
    public function detail($id)
    {
        $attendance = Attendance::with(['user', 'restTimes'])->findOrFail($id);
        $user = $attendance->user;
        
        $pendingRequest = AttendanceCorrectRequest::where('attendance_id', $attendance->id)
            ->where('status', 0)
            ->first();

        $breaks = $attendance->restTimes->map(function ($rest) {
            return [
                'break_in'  => $rest->start_time ? Carbon::parse($rest->start_time)->format('H:i') : '',
                'break_out' => $rest->end_time ? Carbon::parse($rest->end_time)->format('H:i') : '',
            ];
        })->toArray();

        $attendanceDate = Carbon::parse($attendance->date);

        $attendanceRecord = [
            'id'        => $attendance->id,
            'name'      => $user->name ?? '',
            'year'      => $attendanceDate->format('Y年'),
            'date'      => Carbon::parse($attendance->date),
            'clock_in'  => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks'    => $breaks,
            'comment'   => $attendance->remarks ?? '',
        ];

        return view('admin.admin-detail', [
            'attendance'       => $attendance,
            'attendanceRecord' => $attendanceRecord,
            'user'             => $user,
            'pendingRequest'   => $pendingRequest, // ★追加
        ]);
    }

    /**
     * 5. 管理者用 勤怠データの直接修正処理
     */
    public function update(AdminAttendanceUpdateRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $hasPending = AttendanceCorrectRequest::where('attendance_id', $attendance->id)
            ->where('status', 0)
            ->exists();

        if ($hasPending) {
            return redirect()->back()->withErrors(['message' => '承認待ちのため修正はできません。']);
        }

        $date = Carbon::parse($attendance->date)->format('Y-m-d');

        $clockIn = $request->filled('new_clock_in') ? "{$date} {$request->new_clock_in}:00" : null;
        $clockOut = $request->filled('new_clock_out') ? "{$date} {$request->new_clock_out}:00" : null;

        $updateData = [
            'clock_in'  => $clockIn,
            'clock_out' => $clockOut,
            'remarks'   => $request->comment,
        ];

        $attendance->update($updateData);

        $attendance->restTimes()->delete();

        if ($request->has('new_break_in') && is_array($request->new_break_in)) {
            foreach ($request->new_break_in as $index => $breakIn) {
                $breakOut = $request->new_break_out[$index] ?? null;

                if (!empty($breakIn) && !empty($breakOut)) {
                    RestTime::create([
                        'attendance_id' => $attendance->id,
                        'start_time'    => "{$date} {$breakIn}:00",
                        'end_time'      => "{$date} {$breakOut}:00",
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', '勤怠データを更新しました');
    }

    /**
     * 6. 申請一覧画面
     */
    public function requestList()
    {
        $requests = AttendanceCorrectRequest::with(['user', 'attendance'])->get();

        $applications = $requests->map(function ($req) {
            $req->approval_status = ((int) $req->status === 1) ? '承認済み' : '承認待ち';
            $req->comment = $req->remarks;
            $req->application_date = $req->created_at;
            $req->AttendanceRecord = $req->attendance;

            return $req;
        });

        return view('admin.admin-application-list', [
            'applications' => $applications,
        ]);
    }

    /**
     * 7-1. 修正申請詳細（承認画面）の表示
     */
    public function showApproveForm($id)
    {
        $correctRequest = AttendanceCorrectRequest::with(['user', 'attendance.restTimes', 'proposedBreaks'])->findOrFail($id);

        $correctRequest->approval_status = match ((int) $correctRequest->status) {
            0       => '承認待ち',
            1       => '承認済み',
            default => '承認待ち',
        };

        $attendanceDate = $correctRequest->attendance ? Carbon::parse($correctRequest->attendance->date) : Carbon::now();
        $correctRequest->new_date = $attendanceDate;
        $correctRequest->new_clock_in = $correctRequest->clock_in ? Carbon::parse($correctRequest->clock_in)->format('H:i') : '';
        $correctRequest->new_clock_out = $correctRequest->clock_out ? Carbon::parse($correctRequest->clock_out)->format('H:i') : '';
        $correctRequest->comment = $correctRequest->remarks;

        $breakSource = $correctRequest->proposedBreaks->isNotEmpty()
        ? $correctRequest->proposedBreaks
        : $correctRequest->attendance->restTimes;

        $correctRequest->proposalBreaks = $breakSource->map(function ($rest) {
            return (object) [
                'break_in'  => $rest->start_time ? Carbon::parse($rest->start_time)->format('H:i') : '',
                'break_out' => $rest->end_time ? Carbon::parse($rest->end_time)->format('H:i') : '',
            ];
        });

        return view('admin.admin-application-detail', [
            'application' => $correctRequest,
            'user'        => $correctRequest->user,
        ]);
    }

    /**
     * 7-2. 修正申請の承認アクション
     */
    public function approve($id)
    {
        $correctRequest = AttendanceCorrectRequest::findOrFail($id);

        if ((int) $correctRequest->status === 1) {
            return redirect()->route('admin.request.list');
        }

        $attendance = Attendance::findOrFail($correctRequest->attendance_id);

        $updateData = [
            'clock_in'  => $correctRequest->clock_in,
            'clock_out' => $correctRequest->clock_out,
            'remarks'   => $correctRequest->remarks,
        ];

        $attendance->update([
            'clock_in'  => $correctRequest->clock_in,
            'clock_out' => $correctRequest->clock_out,
            'remarks'   => $correctRequest->remarks,
    ]);

    if ($correctRequest->proposedBreaks->isNotEmpty()) {
        $attendance->restTimes()->delete(); 
        RestTime::where('attendance_correct_request_id', $correctRequest->id)
            ->update(['attendance_correct_request_id' => null]);
    }

    $correctRequest->update(['status' => 1]);

    return redirect()->route('admin.request.list');
}

    /**
     * 8. CSV出力機能
     */
    public function exportCsv(Request $request)
    {
        $userId = $request->input('user_id');
        $month = $request->input('year_month', Carbon::now()->format('Y-m'));

        $user = User::findOrFail($userId);
        $targetMonth = Carbon::parse($month);

        $attendances = Attendance::with('restTimes')
            ->where('user_id', $user->id)
            ->whereYear('date', $targetMonth->year)
            ->whereMonth('date', $targetMonth->month)
            ->orderBy('date', 'asc')
            ->get();

        $fileName = "attendance_{$user->name}_{$targetMonth->format('Ym')}.csv";

        $response = new StreamedResponse(function () use ($attendances) {
            $stream = fopen('php://output', 'w');

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['日付', '出勤時間', '退勤時間', '休憩時間', '備考']);

            foreach ($attendances as $attendance) {
                $totalRestMinutes = 0;
                foreach ($attendance->restTimes as $rest) {
                    if ($rest->start_time && $rest->end_time) {
                        $start = Carbon::parse($rest->start_time);
                        $end = Carbon::parse($rest->end_time);
                        $totalRestMinutes += $end->diffInMinutes($start);
                    }
                }

                $restFormatted = sprintf('%02d:%02d', floor($totalRestMinutes / 60), $totalRestMinutes % 60);

                fputcsv($stream, [
                    Carbon::parse($attendance->date)->format('Y/m/d'),
                    $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                    $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                    $restFormatted,
                    $attendance->remarks ?? '',
                ]);
            }

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$fileName}\"");

        return $response;
    }
}