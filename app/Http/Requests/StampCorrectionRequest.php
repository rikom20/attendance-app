<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StampCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'after:new_clock_in', 'before:new_clock_out'],
            'new_break_out.*' => ['nullable', 'date_format:H:i', 'after:new_break_in.*', 'before_or_equal:new_clock_out'],
            'comment' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // 1. 出勤時間・退勤時間の比較エラー
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            // 2. 休憩開始時間が出勤前・退勤後になっている場合のエラー
            'new_break_in.*.after' => '休憩時間が不適切な値です',
            'new_break_in.*.before' => '休憩時間が不適切な値です',

            // 3. 休憩終了時間が退勤後になっている場合のエラー
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',

            // 4. 備考欄未入力エラー
            'comment.required' => '備考を記入してください',
        ];
    }
}