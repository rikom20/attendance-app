<?php

use Laravel\Fortify\Features;

return [

    // Guardの指定（標準の一般ユーザー用）
    'guard' => 'web',

    // ログイン・登録後のリダイレクト先（一般ユーザー用）
    'home' => '/attendance',

    // 利用する機能のオン/オフ
    'features' => [
        Features::registration(),            // 会員登録機能
        Features::resetPasswords(),          // パスワードリセット機能
        //Features::emailVerification(),       // メール認証機能
    ],

];