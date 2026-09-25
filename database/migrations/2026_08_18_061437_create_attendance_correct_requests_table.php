<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_correct_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->dateTime('clock_in'); 
            $table->dateTime('clock_out'); 
            $table->text('remarks'); 
            $table->tinyInteger('status')->default(0); // 申請状態（0: 承認待ち, 1: 承認済み）
            $table->timestamp('approved_at')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correct_requests');
    }
};