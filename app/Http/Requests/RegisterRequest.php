<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * リクエストの実行を許可するかどうか
     */
    public function authorize(): bool
    {
        return true; // 誰でも登録フォームを送信できるように true に変更
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    /**
     * カスタムエラーメッセージ（要件定義書に完全一致）
     */
    public function messages(): array
    {
        return [
            'name.required' => 'お名前を入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスはメール形式で入力してください',
            'password.required' => 'パスワードを入力してください',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'password.confirmed' => 'パスワードと一致しません',
        ];
    }
}