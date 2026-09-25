<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'attendance_correct_request_id',
        'start_time',
        'end_time',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function attendanceCorrectRequest(): BelongsTo
    {
    return $this->belongsTo(AttendanceCorrectRequest::class);
    }
}