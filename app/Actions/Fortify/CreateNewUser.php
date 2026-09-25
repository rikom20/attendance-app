<?php

namespace App\Actions\Fortify;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * 新規登録ユーザーのバリデーションと作成
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // RegisterRequest のルールとメッセージを使用してバリデーション実行
        Validator::make(
            $input,
            (new RegisterRequest())->rules(),
            (new RegisterRequest())->messages()
        )->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'admin_status' => false, // 一般ユーザーとして作成
        ]);
    }
}