<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'clock_in',
        'clock_out',
        'remarks',
        'status',
        'approved_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    // アクセサ
    public function getAttendanceRecordAttribute()
    {
        return $this->attendance;
    }

    public function getApprovalStatusAttribute(): string
    {
        return ((int) $this->status === 1) ? '承認済み' : '承認待ち';
    }

    public function getCommentAttribute()
    {
        return $this->remarks;
    }

    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    public function proposedBreaks(): HasMany
    {
    return $this->hasMany(RestTime::class); // attendance_correct_request_id で自動的に絞り込まれる
    }
}