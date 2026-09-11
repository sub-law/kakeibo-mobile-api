# ER図

この文書は`database/migrations`を正として、業務・認証テーブルとLaravel管理テーブルを分けて表現しています。図はGitHub上で更新内容を確認しやすいMermaid形式です。

## 1. 業務・認証テーブル

```mermaid
erDiagram
    USERS ||--o{ INCOMES : "所有する"
    USERS ||--o{ EXPENSES : "所有する"
    USERS ||--o{ ACCOUNTS : "所有する"
    USERS ||--o{ ASSET_BALANCES : "所有する"
    USERS ||--o{ BUDGET_ALERT_SETTINGS : "設定する"
    USERS ||--o{ FIXED_EXPENSES : "設定する"
    USERS ||--o{ LOGIN_HISTORIES : "記録する"
    CATEGORY_GROUPS ||--o{ CATEGORIES : "分類する"
    CATEGORIES ||--o{ EXPENSES : "分類する"
    CATEGORIES ||--o{ BUDGET_ALERT_SETTINGS : "監視対象になる"
    CATEGORIES ||--o{ FIXED_EXPENSES : "分類する"
    ACCOUNTS ||--o{ ASSET_BALANCES : "月次残高を持つ"
    BUDGET_ALERT_SETTINGS ||--o{ BUDGET_ALERT_READS : "既読状態を持つ"
    FIXED_EXPENSES ||--o{ FIXED_EXPENSE_PROCESSES : "月次処理される"
    EXPENSES o|--o| FIXED_EXPENSE_PROCESSES : "固定費から生成される"

    USERS {
        bigint id PK
        varchar name
        varchar email UK
        timestamp email_verified_at "NULL可"
        varchar password
        varchar remember_token "NULL可"
        timestamp created_at
        timestamp updated_at
    }

    INCOMES {
        bigint id PK
        bigint user_id FK
        integer amount
        date date
        varchar memo "NULL可"
        timestamp created_at
        timestamp updated_at
    }

    CATEGORY_GROUPS {
        bigint id PK
        varchar name
        timestamp created_at
        timestamp updated_at
    }

    CATEGORIES {
        bigint id PK
        bigint category_group_id FK
        varchar name
        timestamp created_at
        timestamp updated_at
    }

    EXPENSES {
        bigint id PK
        bigint user_id FK
        bigint category_id FK
        date date
        integer amount
        varchar memo "NULL可"
        timestamp created_at
        timestamp updated_at
    }

    ACCOUNTS {
        bigint id PK
        bigint user_id FK
        varchar name
        varchar type "bank / securities / cash"
        timestamp created_at
        timestamp updated_at
    }

    ASSET_BALANCES {
        bigint id PK
        bigint user_id FK "複合UK"
        bigint account_id FK "複合UK"
        integer amount "NULL可"
        date date "複合UK・月初日"
        timestamp created_at
        timestamp updated_at
    }

    BUDGET_ALERT_SETTINGS {
        bigint id PK
        bigint user_id FK "複合UK"
        bigint category_id FK "複合UK"
        unsigned_integer monthly_budget
        unsigned_tinyint warning_threshold_percent "既定値70"
        boolean is_enabled "既定値true"
        timestamp created_at
        timestamp updated_at
    }

    BUDGET_ALERT_READS {
        bigint id PK
        bigint budget_alert_setting_id FK "複合UK"
        unsigned_smallint year "複合UK"
        unsigned_tinyint month "複合UK"
        varchar level "複合UK・最大20文字"
        timestamp read_at
    }

    FIXED_EXPENSES {
        bigint id PK
        bigint user_id FK
        bigint category_id FK
        unsigned_integer amount
        varchar memo
        boolean is_enabled "既定値true"
        timestamp created_at
        timestamp updated_at
    }

    FIXED_EXPENSE_PROCESSES {
        bigint id PK
        bigint fixed_expense_id FK "複合UK"
        bigint expense_id FK "NULL可・UK"
        date target_month "複合UK"
        timestamp created_at
        timestamp updated_at
    }

    LOGIN_HISTORIES {
        bigint id PK
        bigint user_id FK "複合INDEX"
        timestamp logged_in_at "複合INDEX"
        varchar ip_address "NULL可・最大45文字"
        varchar user_agent "NULL可・最大255文字"
        timestamp read_at "NULL可"
    }
```

## 2. 主な制約

| テーブル | 制約 | 目的 |
|---|---|---|
| `users` | `email` UNIQUE | メールアドレスの重複防止 |
| `asset_balances` | `user_id`, `account_id`, `date` UNIQUE | 同一口座・同一月の残高を1件に限定 |
| `budget_alert_settings` | `user_id`, `category_id` UNIQUE | ユーザーごとに同一カテゴリの設定を1件に限定 |
| `budget_alert_reads` | `budget_alert_setting_id`, `year`, `month`, `level` UNIQUE | 月・警告レベル単位の既読状態を1件に限定 |
| `fixed_expense_processes` | `fixed_expense_id`, `target_month` UNIQUE | 同じ固定費の月次重複処理を防止 |
| `fixed_expense_processes` | `expense_id` UNIQUE・NULL可 | 1件の支出と複数処理履歴の紐付けを防止 |
| `login_histories` | `user_id`, `logged_in_at` INDEX | ユーザーの直近ログイン取得を補助 |

### 外部キー削除時の動作

- `users`を削除すると、関連する入金、支出、口座、資産残高、予算設定、固定費、ログイン履歴を連動して削除します。
- `category_groups`を削除するとカテゴリを、カテゴリを削除すると関連する支出、予算設定、固定費を連動して削除します。
- `accounts`を削除すると関連する資産残高を連動して削除します。
- `budget_alert_settings`を削除すると関連する既読状態を連動して削除します。
- `fixed_expenses`を削除すると関連する月次処理履歴を連動して削除します。
- 固定費から生成した`expenses`を削除した場合、処理履歴の`expense_id`だけを`NULL`にします。処理済み状態は保持されます。

### アプリケーション側の制約

- 口座、資産残高、入出金、予算設定、固定費、ログイン履歴は認証ユーザーの関連から操作します。
- `asset_balances.date`はAPIで`YYYY-MM-01`に限定します。DBカラム自体は`DATE`型です。
- `accounts.type`は`bank`、`securities`、`cash`を使用します。DBカラム自体は文字列で、列制約による値の限定はありません。

## 3. Laravel管理テーブル

```mermaid
erDiagram
    USERS o|--o{ SESSIONS : "論理参照"
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : "ポリモーフィック関連"

    PASSWORD_RESET_TOKENS {
        varchar email PK
        varchar token
        timestamp created_at "NULL可"
    }

    SESSIONS {
        varchar id PK
        bigint user_id "NULL可・INDEX"
        varchar ip_address "NULL可・最大45文字"
        text user_agent "NULL可"
        longtext payload
        integer last_activity "INDEX"
    }

    CACHE {
        varchar key PK
        mediumtext value
        bigint expiration "INDEX"
    }

    CACHE_LOCKS {
        varchar key PK
        varchar owner
        bigint expiration "INDEX"
    }

    JOBS {
        bigint id PK
        varchar queue "INDEX"
        longtext payload
        unsigned_tinyint attempts
        unsigned_integer reserved_at "NULL可"
        unsigned_integer available_at
        unsigned_integer created_at
    }

    JOB_BATCHES {
        varchar id PK
        varchar name
        integer total_jobs
        integer pending_jobs
        integer failed_jobs
        longtext failed_job_ids
        mediumtext options "NULL可"
        integer cancelled_at "NULL可"
        integer created_at
        integer finished_at "NULL可"
    }

    FAILED_JOBS {
        bigint id PK
        varchar uuid UK
        text connection
        text queue
        longtext payload
        longtext exception
        timestamp failed_at
    }

    PERSONAL_ACCESS_TOKENS {
        bigint id PK
        varchar tokenable_type "複合INDEX"
        bigint tokenable_id "複合INDEX"
        text name
        varchar token UK "ハッシュ値・64文字"
        text abilities "NULL可"
        timestamp last_used_at "NULL可"
        timestamp expires_at "NULL可・INDEX"
        timestamp created_at
        timestamp updated_at
    }
```

`sessions.user_id`、`password_reset_tokens.email`には外部キー制約がありません。`personal_access_tokens`もポリモーフィック関連のため`users`への外部キー制約を持ちません。
