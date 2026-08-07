# ER図

このER図は、`database/migrations` にあるマイグレーションを基準に作成しています。業務・認証対象12テーブルとLaravelフレームワーク管理用7テーブルの合計19テーブルを掲載しています。

## 業務テーブル

```mermaid
erDiagram
    USERS ||--o{ INCOMES : "収入を登録する"
    USERS ||--o{ EXPENSES : "支出を登録する"
    USERS ||--o{ ASSET_BALANCES : "資産残高を登録する"
    USERS ||--o{ BUDGET_ALERT_SETTINGS : "予算アラートを設定する"
    USERS ||--o{ FIXED_EXPENSES : "固定費を登録する"
    CATEGORY_GROUPS ||--o{ CATEGORIES : "カテゴリをまとめる"
    CATEGORIES ||--o{ EXPENSES : "支出に分類される"
    CATEGORIES ||--o{ BUDGET_ALERT_SETTINGS : "予算アラートの対象になる"
    CATEGORIES ||--o{ FIXED_EXPENSES : "固定費に分類される"
    ACCOUNTS ||--o{ ASSET_BALANCES : "口座別に残高を持つ"
    BUDGET_ALERT_SETTINGS ||--o{ BUDGET_ALERT_READS : "既読状態を持つ"
    FIXED_EXPENSES ||--o{ FIXED_EXPENSE_PROCESSES : "月ごとの出金履歴を持つ"
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
        varchar name
        varchar type "bank / securities / cash"
        timestamp created_at
        timestamp updated_at
    }

    ASSET_BALANCES {
        bigint id PK
        bigint user_id FK "複合UK構成列"
        bigint account_id FK "複合UK構成列"
        integer amount "NULL可"
        date date "複合UK構成列・画面は月初日を送信"
        timestamp created_at
        timestamp updated_at
    }

    BUDGET_ALERT_SETTINGS {
        bigint id PK
        bigint user_id FK "複合UK構成列"
        bigint category_id FK "複合UK構成列"
        unsigned_integer monthly_budget
        unsigned_tinyint warning_threshold_percent "デフォルト70"
        boolean is_enabled "デフォルトtrue"
        timestamp created_at
        timestamp updated_at
    }

    BUDGET_ALERT_READS {
        bigint id PK
        bigint budget_alert_setting_id FK "複合UK構成列"
        unsigned_smallint year "複合UK構成列"
        unsigned_tinyint month "複合UK構成列"
        varchar level "複合UK構成列"
        timestamp read_at
    }

    FIXED_EXPENSES {
        bigint id PK
        bigint user_id FK
        bigint category_id FK
        unsigned_integer amount
        varchar memo "NOT NULL"
        boolean is_enabled "デフォルトtrue"
        timestamp created_at
        timestamp updated_at
    }

    FIXED_EXPENSE_PROCESSES {
        bigint id PK
        bigint fixed_expense_id FK "複合UK構成列"
        bigint expense_id FK "NULL可・UK"
        date target_month "複合UK構成列"
        timestamp created_at
        timestamp updated_at
    }
```

### 制約

- `incomes.user_id`、`expenses.user_id`、`expenses.category_id`、`categories.category_group_id`、`asset_balances.user_id`、`asset_balances.account_id`、`budget_alert_settings.user_id`、`budget_alert_settings.category_id`、`budget_alert_reads.budget_alert_setting_id`、`fixed_expenses.user_id`、`fixed_expenses.category_id`、`fixed_expense_processes.fixed_expense_id` は、参照先の削除時に連動して削除されます。
- `asset_balances` は、`user_id`、`account_id`、`date` の組み合わせで一意です。
- `asset_balances.date` はAPIでは任意の有効日付を受理し、現行フロントエンドは対象月の月初日を送信します。
- `budget_alert_settings` は、`user_id`、`category_id` の組み合わせで一意です。
- `budget_alert_reads` は、`budget_alert_setting_id`、`year`、`month`、`level` の組み合わせで一意です。
- `fixed_expense_processes` は、`fixed_expense_id`、`target_month` の組み合わせで一意です。また、`expense_id` も一意です。
- `fixed_expense_processes.expense_id` はNULLを許可し、参照する出金が削除された場合はNULLになります。これにより、出金削除後も固定費の処理済み履歴が残ります。
- `accounts.type` の値は、マイグレーションのコメント上では `bank`、`securities`、`cash` を想定しています。DB上の列型は文字列で、値を限定する制約はありません。

## 認証・Laravelフレームワーク管理テーブル

```mermaid
erDiagram
    USERS o|--o{ SESSIONS : "user_idによる論理参照"

    PASSWORD_RESET_TOKENS {
        varchar email PK
        varchar token
        timestamp created_at "NULL可"
    }

    SESSIONS {
        varchar id PK
        bigint user_id "NULL可・INDEX"
        varchar ip_address "NULL可"
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
        tinyint attempts
        integer reserved_at "NULL可"
        integer available_at
        integer created_at
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
        varchar tokenable_type "複合INDEX構成列"
        bigint tokenable_id "複合INDEX構成列"
        text name
        varchar token UK
        text abilities "NULL可"
        timestamp last_used_at "NULL可"
        timestamp expires_at "NULL可・INDEX"
        timestamp created_at
        timestamp updated_at
    }
```

### 補足

- `personal_access_tokens` は認証対象12テーブルに含み、残る7テーブルをLaravelフレームワーク管理用として数えます。
- `sessions.user_id` にはインデックスがありますが、マイグレーション上の外部キー制約はありません。
- `password_reset_tokens.email` と `users.email` の間にも外部キー制約はありません。
- `personal_access_tokens` は `tokenable_type` と `tokenable_id` によるポリモーフィック関連のため、特定のテーブルへの外部キー制約はありません。
