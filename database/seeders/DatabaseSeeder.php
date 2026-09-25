<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Attendance;
use App\Models\RestTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ユーザーアカウントの作成
        $user1 = User::create([
            'name' => 'ユーザー1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        $user2 = User::create([
            'name' => 'ユーザー2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        $admin = User::create([
            'name' => '管理者ユーザー',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'admin_status' => true, // 管理者は true
        ]);

        // 2. user1の過去5ヶ月分の通常勤務データ (各月平日15日, 9:00-18:00)
        for ($m = 5; $m >= 1; $m--) {
            $baseMonth = Carbon::now()->subMonths($m)->startOfMonth();
            $dayCount = 0;

            for ($d = 1; $d <= 28; $d++) {
                $date = $baseMonth->copy()->addDays($d - 1);
                if (!$date->isWeekend() && $dayCount < 15) {
                    $this->createAttendanceRecord($user1->id, $date->format('Y-m-d'), '09:00:00', '18:00:00');
                    $dayCount++;
                }
            }
        }

        // 3. user1の当月（17日分）の各種パターン生成
        $patterns = [
            // 通常 10日
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            // 残業 3日 (9:00-20:00)
            ['in' => '09:00:00', 'out' => '20:00:00'],
            ['in' => '09:00:00', 'out' => '20:00:00'],
            ['in' => '09:00:00', 'out' => '20:00:00'],
            // 遅刻 2日 (9:30-18:00)
            ['in' => '09:30:00', 'out' => '18:00:00'],
            ['in' => '09:30:00', 'out' => '18:00:00'],
            // 早退 1日 (9:00-17:00)
            ['in' => '09:00:00', 'out' => '17:00:00'],
            // 長時間労働 1日 (8:00-21:00)
            ['in' => '08:00:00', 'out' => '21:00:00'],
        ];

        $currentMonth = Carbon::now()->startOfMonth();
        $dayIndex = 0;

        for ($d = 1; $d <= 30; $d++) {
            if ($dayIndex >= count($patterns)) break;

            $date = $currentMonth->copy()->addDays($d - 1);
            if (!$date->isWeekend()) {
                $p = $patterns[$dayIndex];
                $this->createAttendanceRecord($user1->id, $date->format('Y-m-d'), $p['in'], $p['out']);
                $dayIndex++;
            }
        }
    }

    /**
     * 勤怠レコードと固定休憩（12:00-13:00）を生成
     */
    private function createAttendanceRecord(int $userId, string $date, string $clockIn, string $clockOut): void
    {
        $attendance = Attendance::create([
            'user_id' => $userId,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => "{$date} {$clockOut}",
            'status' => 3, // 退勤済
        ]);

        // 固定休憩を作成
        RestTime::create([
            'attendance_id' => $attendance->id,
            'start_time' => "{$date} 12:00:00",
            'end_time' => "{$date} 13:00:00",
        ]);
    }
}