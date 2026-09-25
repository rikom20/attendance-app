<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date'); 
            $table->dateTime('clock_in');// 出勤日時
            $table->dateTime('clock_out')->nullable(); // 退勤日時
            $table->tinyInteger('status')->default(0); // 状態（0: 勤務外, 1: 出勤中, 2: 休憩中, 3: 退勤済）
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};