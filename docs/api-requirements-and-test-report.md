# API要件・テストケースレポート

## 1. 文書情報

| 項目 | 内容 |
|---|---|
| 文書名 | API要件・テストケースレポート |
| 対象システム | 家計簿モバイルアプリケーション API |
| 対象プロジェクト | `kakeibo-mobile-public-api` |
| 作成日 | 2026-07-19 |
| APIフレームワーク | Laravel 13 |
| PHP | 8.4 |
| 認証方式 | Laravel Sanctum Bearer Token |
| 自動テスト | PHPUnit 12 / Laravel Feature Test |

### 1.1 目的

本書は、家計簿モバイルアプリケーションのAPIについて、以下を要件定義書へ転記できる形で整理することを目的とする。

- APIルート仕様
- Controller仕様
- リクエストおよびレスポンス仕様
- テーブル仕様
- APIテストケース
- テスト実行方法および実行結果

### 1.2 対象機能

- ログイン・ログアウト・認証ユーザー取得
- 入金の一覧・登録・詳細・更新・削除
- 支出カテゴリ一覧
- 支出の一覧・登録・詳細・更新・削除
- 口座一覧
- 月次資産残高の一覧・一括登録

### 1.3 対象外

- 削除済みの旧資産CRUD API
- フロントエンドのUIテスト
- 本番環境や外部サービスを利用するテスト
- Laravel内部管理用のキャッシュ・ジョブ関連テーブルの詳細
- 月次資産残高の日付を必ず月初に限定する検証

## 2. 共通API仕様

### 2.1 ベースパス

すべてのAPIルートは`/api`配下で提供する。

### 2.2 認証

`POST /api/login`を除く業務APIは`auth:sanctum`ミドルウェアで保護する。認証が必要なAPIでは、次のHTTPヘッダーを送信する。

```http
Authorization: Bearer {token}
Accept: application/json
```

### 2.3 主なHTTPステータス

| ステータス | 用途 |
|---:|---|
| 200 OK | 取得・更新・削除・ログイン・ログアウト・月次残高登録成功 |
| 201 Created | 入金または支出の新規登録成功 |
| 401 Unauthorized | 認証失敗、トークンなし、無効なトークン |
| 404 Not Found | 対象データが存在しない、または他ユーザーのデータを指定した場合 |
| 422 Unprocessable Entity | 入力値のバリデーションエラー |

### 2.4 ユーザー分離

入金、支出、月次資産残高はログインユーザーの`user_id`で絞り込む。他ユーザーのデータは取得・更新・削除できない。

口座および支出カテゴリは共通マスタとして扱い、ユーザーには紐づけない。

## 3. APIルート仕様

### 3.1 ルート一覧

| No. | 機能 | メソッド | エンドポイント | 認証 | Controller / 処理 |
|---:|---|---|---|---|---|
| 1 | ログイン | POST | `/api/login` | 不要 | `LoginController::__invoke` |
| 2 | 認証ユーザー取得 | GET | `/api/user` | 必要 | ルートクロージャ |
| 3 | ログアウト | POST | `/api/logout` | 必要 | `LogoutController::__invoke` |
| 4 | 入金一覧 | GET | `/api/incomes` | 必要 | `IncomeController::index` |
| 5 | 入金登録 | POST | `/api/incomes` | 必要 | `IncomeController::store` |
| 6 | 入金詳細 | GET | `/api/incomes/{id}` | 必要 | `IncomeController::show` |
| 7 | 入金更新 | PUT | `/api/incomes/{id}` | 必要 | `IncomeController::update` |
| 8 | 入金削除 | DELETE | `/api/incomes/{id}` | 必要 | `IncomeController::destroy` |
| 9 | カテゴリ一覧 | GET | `/api/categories` | 必要 | `CategoryController::index` |
| 10 | 支出一覧 | GET | `/api/expenses` | 必要 | `ExpenseController::index` |
| 11 | 支出登録 | POST | `/api/expenses` | 必要 | `ExpenseController::store` |
| 12 | 支出詳細 | GET | `/api/expenses/{id}` | 必要 | `ExpenseController::show` |
| 13 | 支出更新 | PUT | `/api/expenses/{id}` | 必要 | `ExpenseController::update` |
| 14 | 支出削除 | DELETE | `/api/expenses/{id}` | 必要 | `ExpenseController::destroy` |
| 15 | 口座一覧 | GET | `/api/accounts` | 必要 | `AccountController::index` |
| 16 | 月次資産残高一覧 | GET | `/api/asset-balances` | 必要 | `AssetBalanceController::index` |
| 17 | 月次資産残高一括登録 | POST | `/api/asset-balances/bulk` | 必要 | `AssetBalanceController::bulk` |

### 3.2 認証API

#### POST `/api/login`

登録済みユーザーを認証し、既存トークンをすべて削除したうえで新しいSanctumトークンを発行する。

| パラメータ | 型 | 必須 | 検証内容 |
|---|---|---:|---|
| email | string | 必須 | メールアドレス形式 |
| password | string | 必須 | 文字列 |

成功レスポンス例：

```json
{
  "token": "1|plain-text-token",
  "user": {
    "id": 1,
    "name": "テストユーザー",
    "email": "user@example.com"
  }
}
```

認証情報が一致しない場合は`401`と`{"message":"Unauthorized"}`を返す。

#### GET `/api/user`

Bearer Tokenに対応する認証ユーザー情報を返す。トークンがない、または無効な場合は`401`を返す。

#### POST `/api/logout`

現在のリクエストで使用したアクセストークンだけを削除する。同一ユーザーの別トークンや他ユーザーのトークンは削除しない。

成功レスポンス：

```json
{"message":"Logout"}
```

### 3.3 入金API

#### GET `/api/incomes`

ログインユーザーの指定年月の入金を日付昇順で返す。

| クエリ | 型 | 必須 | 初期値 |
|---|---|---:|---|
| year | integer | 任意 | 現在年 |
| month | integer | 任意 | 現在月 |

レスポンスは入金オブジェクトの配列である。

#### POST `/api/incomes`

| パラメータ | 型 | 必須 | 検証内容 |
|---|---|---:|---|
| date | date | 必須 | 有効な日付 |
| amount | integer | 必須 | 1以上 |
| memo | string | 任意 | NULL許可 |

ログインユーザーの入金として登録し、`201`で登録データを返す。

#### GET `/api/incomes/{id}`

ログインユーザーが所有する入金を返す。対象が存在しない場合、または他ユーザーの入金の場合は`404`を返す。

#### PUT `/api/incomes/{id}`

登録時と同じ入力規則で、ログインユーザーが所有する入金を更新する。成功時は`200`で更新データを返す。

#### DELETE `/api/incomes/{id}`

ログインユーザーが所有する入金を削除する。

```json
{"message":"Deleted"}
```

### 3.4 カテゴリAPI

#### GET `/api/categories`

カテゴリグループと、その配下のカテゴリを階層形式で返す。

```json
[
  {
    "id": 1,
    "name": "生活費",
    "categories": [
      {"id": 1, "name": "食費"}
    ]
  }
]
```

### 3.5 支出API

#### GET `/api/expenses`

ログインユーザーの指定年月の支出を日付昇順で返す。各支出にはカテゴリとカテゴリグループを含める。

| クエリ | 型 | 必須 | 初期値 |
|---|---|---:|---|
| year | integer | 任意 | 現在年 |
| month | integer | 任意 | 現在月 |

#### POST `/api/expenses`

| パラメータ | 型 | 必須 | 検証内容 |
|---|---|---:|---|
| date | date | 必須 | 有効な日付 |
| amount | integer | 必須 | 1以上 |
| memo | string | 任意 | NULL許可 |
| category_id | integer | 必須 | `categories.id`に存在すること |

ログインユーザーの支出として登録し、`201`で登録データを返す。

#### GET `/api/expenses/{id}`

ログインユーザーが所有する支出を、カテゴリおよびカテゴリグループとともに返す。他ユーザーの支出または存在しない支出は`404`とする。

#### PUT `/api/expenses/{id}`

登録時と同じ入力規則で、ログインユーザーが所有する支出を更新する。

#### DELETE `/api/expenses/{id}`

ログインユーザーが所有する支出を削除する。

```json
{"message":"Deleted"}
```

### 3.6 口座・月次資産残高API

#### GET `/api/accounts`

口座マスタを配列で返す。各要素は`id`、`name`、`type`を持つ。

#### GET `/api/asset-balances`

ログインユーザーの指定年月の月次資産残高を、口座ID昇順で返す。各残高には口座情報を含める。

| クエリ | 型 | 必須 | 検証内容・初期値 |
|---|---|---:|---|
| year | integer | 任意 | 省略時は現在年 |
| month | integer | 任意 | 1～12、省略時は現在月 |

成功レスポンス例：

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "account_id": 1,
      "amount": 150000,
      "date": "2026-07-01",
      "account": {
        "id": 1,
        "name": "普通預金",
        "type": "bank"
      }
    }
  ]
}
```

#### POST `/api/asset-balances/bulk`

複数口座の残高を一括登録する。同じユーザー、口座、日付のデータが存在する場合は上書きする。

| パラメータ | 型 | 必須 | 検証内容 |
|---|---|---:|---|
| date | date | 必須 | 有効な日付 |
| balances | array | 必須 | 残高データ配列 |
| balances.*.account_id | integer | 必須 | `accounts.id`に存在すること |
| balances.*.amount | integer | 任意 | NULL許可、0以上。NULLまたは省略時は0として保存 |

成功レスポンス例：

```json
{
  "message": "月次残高を登録しました（上書き含む）",
  "data": []
}
```

## 4. Controller仕様

| Controller | メソッド | Request | 使用モデル | 処理概要 |
|---|---|---|---|---|
| `LoginController` | `__invoke` | `LoginRequest` | `User`, `PersonalAccessToken` | 認証、既存トークン削除、新規トークン発行 |
| `LogoutController` | `__invoke` | `Request` | `PersonalAccessToken` | 現在使用中のトークン削除 |
| ルートクロージャ | `/api/user` | `Request` | `User` | 認証ユーザー返却 |
| `IncomeController` | `index` | `Request` | `Income` | ユーザー・年月で絞り込み、日付昇順で取得 |
| `IncomeController` | `store` | `StoreIncomeRequest` | `Income` | 認証ユーザーに紐づけて登録 |
| `IncomeController` | `show` | `Request` | `Income` | ユーザー・IDで詳細取得 |
| `IncomeController` | `update` | `UpdateIncomeRequest` | `Income` | ユーザー・IDで対象を特定して更新 |
| `IncomeController` | `destroy` | `Request` | `Income` | ユーザー・IDで対象を特定して削除 |
| `CategoryController` | `index` | なし | `CategoryGroup`, `Category` | グループとカテゴリを階層化して返却 |
| `ExpenseController` | `index` | `Request` | `Expense` | ユーザー・年月で絞り込み、カテゴリを含めて取得 |
| `ExpenseController` | `store` | `StoreExpenseRequest` | `Expense` | 認証ユーザーに紐づけて登録 |
| `ExpenseController` | `show` | `Request` | `Expense` | ユーザー・IDで詳細取得、カテゴリを付加 |
| `ExpenseController` | `update` | `UpdateExpenseRequest` | `Expense` | ユーザー・IDで対象を特定して更新 |
| `ExpenseController` | `destroy` | `Request` | `Expense` | ユーザー・IDで対象を特定して削除 |
| `AccountController` | `index` | なし | `Account` | 口座マスタ全件取得 |
| `AssetBalanceController` | `index` | `ListAssetBalanceRequest` | `AssetBalance`, `Account` | ユーザー・年月で絞り込み、口座ID順で取得 |
| `AssetBalanceController` | `bulk` | `BulkAssetBalanceRequest` | `AssetBalance` | ユーザー・口座・日付をキーに一括登録または更新 |

## 5. テーブル仕様書

### 5.1 テーブル関連

| 親テーブル | 子テーブル | 関係 | 削除時動作 |
|---|---|---|---|
| `users` | `incomes` | 1対多 | ユーザー削除時に入金を削除 |
| `users` | `expenses` | 1対多 | ユーザー削除時に支出を削除 |
| `users` | `asset_balances` | 1対多 | ユーザー削除時に残高を削除 |
| `users` | `personal_access_tokens` | 1対多（Polymorphic） | アプリケーション処理で管理 |
| `category_groups` | `categories` | 1対多 | グループ削除時にカテゴリを削除 |
| `categories` | `expenses` | 1対多 | カテゴリ削除時に支出を削除 |
| `accounts` | `asset_balances` | 1対多 | 口座削除時に残高を削除 |

### 5.2 `users`

ユーザーのログイン情報を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | ユーザーID |
| name | VARCHAR(255) | 不可 | - | ユーザー名 |
| email | VARCHAR(255) | 不可 | UNIQUE | メールアドレス |
| email_verified_at | TIMESTAMP | 可 | - | メール確認日時 |
| password | VARCHAR(255) | 不可 | - | ハッシュ化パスワード |
| remember_token | VARCHAR(100) | 可 | - | Remember Token |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.3 `personal_access_tokens`

SanctumのBearer Tokenを管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | トークンID |
| tokenable_type | VARCHAR(255) | 不可 | 複合INDEX | トークン所有モデル種別 |
| tokenable_id | BIGINT UNSIGNED | 不可 | 複合INDEX | トークン所有モデルID |
| name | TEXT | 不可 | - | トークン名 |
| token | VARCHAR(64) | 不可 | UNIQUE | ハッシュ化トークン |
| abilities | TEXT | 可 | - | 権限一覧 |
| last_used_at | TIMESTAMP | 可 | - | 最終利用日時 |
| expires_at | TIMESTAMP | 可 | INDEX | 有効期限 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.4 `incomes`

ユーザーごとの入金を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | 入金ID |
| user_id | BIGINT UNSIGNED | 不可 | FK → `users.id` | 所有ユーザーID |
| amount | INTEGER | 不可 | APIで1以上 | 入金額 |
| date | DATE | 不可 | - | 入金日 |
| memo | VARCHAR(255) | 可 | - | 備考 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.5 `category_groups`

支出カテゴリの大分類を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | カテゴリグループID |
| name | VARCHAR(255) | 不可 | - | カテゴリグループ名 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.6 `categories`

支出カテゴリの小分類を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | カテゴリID |
| category_group_id | BIGINT UNSIGNED | 不可 | FK → `category_groups.id` | 所属グループID |
| name | VARCHAR(255) | 不可 | - | カテゴリ名 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.7 `expenses`

ユーザーごとの支出を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | 支出ID |
| user_id | BIGINT UNSIGNED | 不可 | FK → `users.id` | 所有ユーザーID |
| category_id | BIGINT UNSIGNED | 不可 | FK → `categories.id` | 支出カテゴリID |
| date | DATE | 不可 | - | 支出日 |
| amount | INTEGER | 不可 | APIで1以上 | 支出額 |
| memo | VARCHAR(255) | 可 | - | 備考 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.8 `accounts`

資産残高を登録する口座マスタを管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | 口座ID |
| name | VARCHAR(255) | 不可 | - | 口座名 |
| type | VARCHAR(255) | 不可 | - | 口座種別（`bank` / `securities` / `cash`） |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

### 5.9 `asset_balances`

ユーザー・口座・日付単位の月次資産残高を管理する。

| カラム | 型 | NULL | キー・制約 | 説明 |
|---|---|---:|---|---|
| id | BIGINT UNSIGNED | 不可 | PK、自動採番 | 資産残高ID |
| user_id | BIGINT UNSIGNED | 不可 | FK → `users.id` | 所有ユーザーID |
| account_id | BIGINT UNSIGNED | 不可 | FK → `accounts.id` | 口座ID |
| amount | INTEGER | 可 | APIで0以上 | 残高。API登録時はNULL・省略を0として保存 |
| date | DATE | 不可 | 複合UNIQUE | 対象日。画面は月初日を送信 |
| created_at | TIMESTAMP | 可 | - | 作成日時 |
| updated_at | TIMESTAMP | 可 | - | 更新日時 |

複合ユニーク制約：

```text
user_id + account_id + date
```

### 5.10 詳細対象外のLaravel管理テーブル

| テーブル | 用途 |
|---|---|
| `password_reset_tokens` | パスワードリセット用トークン |
| `sessions` | セッション情報 |
| `cache`, `cache_locks` | キャッシュ管理 |
| `jobs`, `job_batches`, `failed_jobs` | キュー・ジョブ管理 |

旧`assets`テーブルは現行APIから使用されないため、本書の業務テーブル仕様から除外する。

## 6. APIテストケース

### 6.1 テスト方針

- Laravel Feature TestでHTTPリクエストからDB更新までを検証する。
- `RefreshDatabase`でテストごとにDB状態を初期化する。
- SQLiteインメモリDBを使用し、開発用DBへ影響を与えない。
- Sanctum認証を使用して認証済み・未認証を検証する。
- 他ユーザーのデータを取得・更新・削除できないことを検証する。
- バリデーションエラーの日本語メッセージを検証する。

### 6.2 認証機能

| ID | 分類 | 確認内容・入力 | 期待結果 | 自動テストメソッド |
|---|---|---|---|---|
| AUTH-001 | 正常系 | 登録済みメールアドレスと正しいパスワードでログイン | 200、トークンとユーザー情報を返し、トークンをDBへ保存 | `test_user_can_log_in_with_valid_credentials` |
| AUTH-002 | 異常系 | 未登録メールアドレスまたは誤ったパスワード | 401、トークンを発行しない | `test_user_cannot_log_in_with_unknown_email_or_wrong_password` |
| AUTH-003 | 異常系 | 必須値なし、または不正なメール形式 | 422、日本語の入力エラー | `test_login_returns_japanese_validation_errors` |
| AUTH-004 | 正常系 | 発行トークンで`GET /api/user`を実行 | 200、対象ユーザー情報を返す | `test_issued_token_can_access_authenticated_user_endpoint` |
| AUTH-005 | 異常系 | トークンなし、または不正トークンで認証APIを実行 | 401 | `test_user_endpoint_requires_a_valid_token` |
| AUTH-006 | 正常系 | 既存トークンを持つユーザーが再ログイン | 古いトークンを削除し、新トークンだけを利用可能にする | `test_logging_in_replaces_the_users_existing_tokens` |
| AUTH-007 | 正常系 | 複数トークンがある状態でログアウト | 現在のトークンだけを無効化し、別トークンには影響しない | `test_logout_invalidates_only_the_current_access_token` |

### 6.3 入金機能

| ID | 分類 | 確認内容・入力 | 期待結果 | 自動テストメソッド |
|---|---|---|---|---|
| INCOME-001 | 異常系 | 未認証で入金CRUD APIを実行 | 全APIが401 | `test_unauthenticated_user_cannot_access_income_endpoints` |
| INCOME-002 | 正常系 | 有効な日付・金額・備考で登録 | 201、認証ユーザーの入金としてDB保存 | `test_authenticated_user_can_create_an_income` |
| INCOME-003 | 異常系 | 必須値なし、不正日付、0円 | 422、日本語の入力エラー | `test_income_creation_returns_japanese_validation_errors` |
| INCOME-004 | 正常系 | 年月を指定して一覧取得 | 自分の対象月データだけを日付昇順で返す | `test_user_can_list_only_their_incomes_for_the_requested_month_in_date_order` |
| INCOME-005 | 正常系 | 自分の入金IDを指定 | 200、入金詳細を返す | `test_user_can_view_their_income` |
| INCOME-006 | 正常系 | 自分の入金を有効な値で更新 | 200、レスポンスとDBを更新 | `test_user_can_update_their_income` |
| INCOME-007 | 正常系 | 自分の入金を削除 | 200、DBから削除 | `test_user_can_delete_their_income` |
| INCOME-008 | 異常系・認可 | 他ユーザーの入金を参照・更新・削除 | 404、対象データを変更しない | `test_user_cannot_view_update_or_delete_another_users_income` |

### 6.4 支出機能

| ID | 分類 | 確認内容・入力 | 期待結果 | 自動テストメソッド |
|---|---|---|---|---|
| EXPENSE-001 | 異常系 | 未認証で支出CRUD APIを実行 | 全APIが401 | `test_unauthenticated_user_cannot_access_expense_endpoints` |
| EXPENSE-002 | 正常系 | 有効な日付・金額・カテゴリ・備考で登録 | 201、認証ユーザーの支出としてDB保存 | `test_authenticated_user_can_create_an_expense` |
| EXPENSE-003 | 異常系 | 必須値なし、不正日付、0円、存在しないカテゴリ | 422、日本語の入力エラー | `test_expense_creation_returns_japanese_validation_errors` |
| EXPENSE-004 | 正常系 | 年月を指定して一覧取得 | 自分の対象月データだけを日付昇順で返す | `test_user_can_list_only_their_expenses_for_the_requested_month_in_date_order` |
| EXPENSE-005 | 正常系 | 自分の支出IDを指定 | 200、カテゴリとグループを含む詳細を返す | `test_user_can_view_their_expense_with_its_category_and_group` |
| EXPENSE-006 | 正常系 | 自分の支出を有効な値で更新 | 200、レスポンスとDBを更新 | `test_user_can_update_their_expense` |
| EXPENSE-007 | 正常系 | 自分の支出を削除 | 200、DBから削除 | `test_user_can_delete_their_expense` |
| EXPENSE-008 | 異常系・認可 | 他ユーザーの支出を参照・更新・削除 | 404、対象データを変更しない | `test_user_cannot_view_update_or_delete_another_users_expense` |

### 6.5 口座・月次資産残高機能

| ID | 分類 | 確認内容・入力 | 期待結果 | 自動テストメソッド |
|---|---|---|---|---|
| ASSET-001 | 異常系 | 未認証で口座・残高APIを実行 | 全APIが401 | `test_unauthenticated_user_cannot_access_asset_balance_endpoints` |
| ASSET-002 | 正常系 | 認証済みで口座一覧を取得 | 200、ID・名称・種別を持つ配列 | `test_authenticated_user_can_list_accounts` |
| ASSET-003 | 正常系 | 口座がない状態で一覧取得 | 200、空配列 | `test_account_list_is_empty_when_no_accounts_exist` |
| ASSET-004 | 正常系 | 複数口座の残高を一括登録 | 200、メッセージと登録結果を返し、DB保存 | `test_user_can_register_multiple_monthly_asset_balances` |
| ASSET-005 | 正常系・認可 | 同じ日付の残高を再登録 | 自分の残高だけを上書きし、他ユーザーの残高を維持 | `test_bulk_registration_updates_only_the_authenticated_users_existing_balance` |
| ASSET-006 | 境界値 | 金額に0、NULL、省略を指定 | すべて0として保存 | `test_bulk_registration_treats_zero_null_and_missing_amount_as_zero` |
| ASSET-007 | 異常系 | 日付・残高配列なし | 422、日本語の必須エラー | `test_bulk_registration_returns_validation_errors_for_missing_values` |
| ASSET-008 | 異常系 | 不正日付、口座なし、存在しない口座、文字列・負数の金額 | 422、対象項目の入力エラー | `test_bulk_registration_rejects_invalid_balance_values` |
| ASSET-009 | 正常系・認可 | 年月を指定して残高一覧取得 | 自分の対象月データだけを口座ID順で返し、口座情報を含む | `test_user_can_list_only_their_balances_for_the_requested_month_in_account_order` |
| ASSET-010 | 正常系 | 対象月に残高がない状態で一覧取得 | 200、`{"data":[]}` | `test_asset_balance_list_is_empty_when_the_month_has_no_data` |
| ASSET-011 | 境界値 | 月に0または13を指定 | 422、日本語の範囲エラー | `test_asset_balance_list_rejects_months_outside_one_to_twelve` |

## 7. テスト実行方法

### 7.1 全テスト

```bash
./vendor/bin/sail artisan test
```

### 7.2 機能別テスト

```bash
./vendor/bin/sail artisan test tests/Feature/AuthApiTest.php
./vendor/bin/sail artisan test tests/Feature/IncomeApiTest.php
./vendor/bin/sail artisan test tests/Feature/ExpenseApiTest.php
./vendor/bin/sail artisan test tests/Feature/AssetBalanceApiTest.php
```

### 7.3 テスト環境

`phpunit.xml`で次を指定し、SQLiteインメモリDB上でテストを実行する。

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## 8. テスト実行結果

2026-07-19時点の実行結果：

| 項目 | 結果 |
|---|---:|
| 業務APIテストケース | 34件 |
| 認証API | 7件成功 |
| 入金API | 8件成功 |
| 支出API | 8件成功 |
| 資産残高API | 11件成功 |
| Laravel雛形テスト | 2件成功 |
| 全テスト | 36件成功 |
| 全アサーション | 198件成功 |
| 失敗 | 0件 |

## 9. 保留事項・今後の検討

| 項目 | 現状 | 今後の検討 |
|---|---|---|
| 月次残高の日付 | フロントは`YYYY-MM-01`を送信するが、APIは任意の有効日付を受理する | API側で月初へ正規化、または月初限定ルールを追加するか検討 |
| フロントエンドテスト | API Feature Testのみ実装済み | UI操作、認証切れ、通信エラー表示のテスト導入を検討 |
| 旧`assets`テーブル | 現行APIでは未使用 | マイグレーション適用状況に応じて別途整理 |
| カテゴリ一覧 | 自動APIテスト未実装 | 必要に応じて階層レスポンスと未認証ケースを追加 |
