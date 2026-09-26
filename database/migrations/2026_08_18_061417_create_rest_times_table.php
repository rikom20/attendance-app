<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rest_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->dateTime('start_time'); // 休憩開始日時
            $table->dateTime('end_time')->nullable(); // 休憩終了日時（休憩中時はnull）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_times');
    }
};