<?php

namespace App\Models;

//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

   
    public function attendanceCorrectRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectRequest::class);
    }

    public function isAdmin(): bool
    {
        return $this->admin_status === true; // admin_statusの真偽で判定
    }
}