<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rest_times', function (Blueprint $table) {
            $table->foreignId('attendance_correct_request_id')
                ->nullable()
                ->after('attendance_id')
                ->constrained('attendance_correct_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rest_times', function (Blueprint $table) {
            $table->dropForeign(['attendance_correct_request_id']);
            $table->dropColumn('attendance_correct_request_id');
        });
    }
};