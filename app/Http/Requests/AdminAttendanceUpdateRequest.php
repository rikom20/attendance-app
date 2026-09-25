<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->admin_status;
    }

    public function rules(): array
    {
        return [
            'new_clock_in'    => ['required', 'date_format:H:i'],
            'new_clock_out'   => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'comment'         => ['required', 'string'],
            'new_break_in.*'  => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator)
    {
         $validator->after(function ($validator) {
        $clockIn  = $this->input('new_clock_in');
        $clockOut = $this->input('new_clock_out');
        $breakIns  = $this->input('new_break_in', []);
        $breakOuts = $this->input('new_break_out', []);

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            // 休憩開始時間が出勤時間より前、または退勤時間より後の場合
            if (!empty($breakIn) && $clockIn && $clockOut) {
                if ($breakIn < $clockIn || $breakIn > $clockOut) {
                    $validator->errors()->add("new_break_in.{$index}", '休憩時間が不適切な値です');
                }
            }

            // 休憩終了時間が退勤時間より後の場合のみをチェック
            if (!empty($breakOut) && $clockOut) {
                if ($breakOut > $clockOut) {
                    $validator->errors()->add("new_break_out.{$index}", '休憩時間もしくは退勤時間が不適切な値です');
                }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required'  => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after'    => '出勤時間もしくは退勤時間が不適切な値です',
            'comment.required'       => '備考を記入してください',
        ];
    }
}