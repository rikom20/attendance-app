# アプリケーション名
勤怠管理アプリ

## 環境構築

### Docker・Laravel環境構築
* git clone git@github.com:rikom20/attendance-app.git
* cp .env.example .env
* composer install
* ./vendor/bin/sail up -d　(※ alias 設定済みの場合は sail up -d でも可,以下同)
* ./vendor/bin/sail artisan key:generate
* ./vendor/bin/sail npm install
* ./vendor/bin/sail npm run dev

### マイグレーションとダミーデータの投入
* ./vendor/bin/sail artisan migrate
* ./vendor/bin/sail artisan db:seed

### ダミーデータ（ログイン情報）
| 区分 | メールアドレス | パスワード | 備考 |
|---|---|---|---|
| 一般ユーザー1 | user1@example.com | password | メール認証済み。過去5ヶ月分＋当月分の勤怠ダミーデータあり |
| 一般ユーザー2 | user2@example.com | password | メール認証済み |
| 管理者ユーザー | user3@example.com | password | admin_status: true |

## 使用技術（実行環境）
* PHP 8.5.9
* Laravel 10.50.3
* MySQL 8.4
* Laravel Sail（Docker）
* Laravel Fortify（認証機能）

## ER図

```mermaid
erDiagram
    users ||--o{ attendances : "user_id"
    users ||--o{ attendance_correct_requests : "user_id"
    attendances ||--o{ rest_times : "attendance_id"
    attendances ||--o{ attendance_correct_requests : "attendance_id"
    attendance_correct_requests ||--o{ rest_times : "attendance_correct_request_id"

    users {
        bigint id PK
        varchar name "NOT NULL"
        varchar email "NOT NULL, UNIQUE"
        timestamp email_verified_at
        varchar password "NOT NULL"
        boolean admin_status "NOT NULL, default:false"
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }

    attendances {
        bigint id PK
        bigint user_id FK
        date date "NOT NULL"
        datetime clock_in "NOT NULL"
        datetime clock_out
        tinyint status "NOT NULL, default:0"
        timestamp created_at
        timestamp updated_at
    }

    rest_times {
        bigint id PK
        bigint attendance_id FK
        bigint attendance_correct_request_id FK "nullable"
        datetime start_time "NOT NULL"
        datetime end_time
        timestamp created_at
        timestamp updated_at
    }

    attendance_correct_requests {
        bigint id PK
        bigint user_id FK
        bigint attendance_id FK
        datetime clock_in "NOT NULL"
        datetime clock_out "NOT NULL"
        text remarks "NOT NULL"
        tinyint status "NOT NULL, default:0"
        timestamp approved_at
        timestamp created_at
        timestamp updated_at
    }
```

## URL
* 会員登録画面：http://localhost/register
* ログイン画面：http://localhost/login
* 管理者ログイン画面：http://localhost/admin/login
* phpMyAdmin：http://localhost:8080/