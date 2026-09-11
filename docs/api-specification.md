# API仕様書

## 1. 概要

家計簿モバイルアプリケーションのNext.jsフロントエンドへ、認証、入出金、資産残高、集計、予算アラート、固定費管理の機能を提供するJSON APIです。

この文書は、次の実装を正として記載しています。

- `routes/api.php`
- `app/Http/Controllers`
- `app/Http/Requests`
- `app/Services`
- `database/migrations`

### ベースパス

```text
/api
```

### 認証方式

`POST /api/login`以外の全APIでLaravel SanctumのBearer Token認証が必要です。

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access-token>
```

トークンの発行・失効とログイン制限の詳細は[認証フロー](authentication-flow.md)を参照してください。

## 2. 共通仕様

### HTTPステータス

| ステータス | 用途 |
|---:|---|
| `200 OK` | 取得、更新、削除、ログイン、処理成功 |
| `201 Created` | 入金、支出、予算設定、固定費の登録成功 |
| `401 Unauthorized` | 認証情報の不一致、トークンなし・無効・期限切れ |
| `404 Not Found` | 対象が存在しない、または他ユーザーが所有する対象を指定 |
| `409 Conflict` | 既読にできる予算アラートが存在しない |
| `422 Unprocessable Entity` | 入力値のバリデーションエラー |
| `429 Too Many Requests` | ログイン試行回数の超過 |

### 未認証レスポンス

```json
{
  "message": "Unauthenticated"
}
```

### バリデーションエラー

入力エラーはフィールド単位の日本語メッセージを返します。

```json
{
  "message": "<validation-message>",
  "errors": {
    "amount": [
      "金額は1円以上で入力してください。"
    ]
  }
}
```

### データ所有権

- 入金、支出、口座、資産残高、予算設定、固定費、ログイン履歴は認証ユーザー単位で扱います。
- ID指定APIは認証ユーザーとの関連から検索します。他ユーザーのIDを指定した場合は`404`を返します。
- 支出カテゴリとカテゴリグループは全ユーザー共通のマスタです。

### 共通リソース

以下は主要フィールドです。Eloquentモデルを直接返すレスポンスには`created_at`と`updated_at`も含まれます。

#### User

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | ユーザーID |
| `name` | string | ユーザー名 |
| `email` | string | メールアドレス |

#### Income

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | 入金ID |
| `user_id` | integer | 所有ユーザーID |
| `date` | string | 入金日（`YYYY-MM-DD`） |
| `amount` | integer | 金額 |
| `memo` | string / null | 備考 |

#### Expense

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | 支出ID |
| `user_id` | integer | 所有ユーザーID |
| `category_id` | integer | カテゴリID |
| `date` | string | 支出日（`YYYY-MM-DD`） |
| `amount` | integer | 金額 |
| `memo` | string / null | 備考 |
| `category` | object | 詳細・一覧取得時のカテゴリとカテゴリグループ |

#### Account

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | 口座ID |
| `user_id` | integer | 所有ユーザーID |
| `name` | string | 口座名 |
| `type` | string | `bank` / `securities` / `cash`を想定 |

## 3. エンドポイント一覧

認証欄が「必要」のAPIは`auth:sanctum`ミドルウェア配下です。

| 機能 | メソッド | エンドポイント | 認証 |
|---|---|---|---|
| ログイン | `POST` | `/api/login` | 不要 |
| 認証ユーザー取得 | `GET` | `/api/user` | 必要 |
| ログアウト | `POST` | `/api/logout` | 必要 |
| パスワード変更 | `PUT` | `/api/user/password` | 必要 |
| ログイン履歴を既読化 | `POST` | `/api/login-histories/{id}/read` | 必要 |
| 入金一覧 | `GET` | `/api/incomes` | 必要 |
| 入金登録 | `POST` | `/api/incomes` | 必要 |
| 入金詳細 | `GET` | `/api/incomes/{id}` | 必要 |
| 入金更新 | `PUT` | `/api/incomes/{id}` | 必要 |
| 入金削除 | `DELETE` | `/api/incomes/{id}` | 必要 |
| カテゴリ一覧 | `GET` | `/api/categories` | 必要 |
| 支出一覧 | `GET` | `/api/expenses` | 必要 |
| 支出登録 | `POST` | `/api/expenses` | 必要 |
| 支出詳細 | `GET` | `/api/expenses/{id}` | 必要 |
| 支出更新 | `PUT` | `/api/expenses/{id}` | 必要 |
| 支出削除 | `DELETE` | `/api/expenses/{id}` | 必要 |
| 口座一覧 | `GET` | `/api/accounts` | 必要 |
| 月次資産残高一覧 | `GET` | `/api/asset-balances` | 必要 |
| 月次資産残高一括登録 | `POST` | `/api/asset-balances/bulk` | 必要 |
| 年次月別集計 | `GET` | `/api/stats/{year}/monthly-summary` | 必要 |
| 予算設定一覧 | `GET` | `/api/budget-alert-settings` | 必要 |
| 予算設定登録 | `POST` | `/api/budget-alert-settings` | 必要 |
| 予算設定詳細 | `GET` | `/api/budget-alert-settings/{id}` | 必要 |
| 予算設定更新 | `PUT` | `/api/budget-alert-settings/{id}` | 必要 |
| 予算設定削除 | `DELETE` | `/api/budget-alert-settings/{id}` | 必要 |
| 予算アラートを既読化 | `POST` | `/api/budget-alert-settings/{id}/read` | 必要 |
| 当月予算アラート | `GET` | `/api/budget-alert-status` | 必要 |
| 固定費処理プレビュー | `GET` | `/api/fixed-expenses/process-preview` | 必要 |
| 固定費一括出金 | `POST` | `/api/fixed-expenses/process` | 必要 |
| 固定費一覧 | `GET` | `/api/fixed-expenses` | 必要 |
| 固定費登録 | `POST` | `/api/fixed-expenses` | 必要 |
| 固定費詳細 | `GET` | `/api/fixed-expenses/{id}` | 必要 |
| 固定費更新 | `PUT` | `/api/fixed-expenses/{id}` | 必要 |

## 4. 認証・ユーザー

### `POST /api/login`

ログインに成功すると既存の全アクセストークンを削除し、新しいトークンを1件発行します。同時に今回のログイン履歴を保存し、直前のログイン履歴をレスポンスへ含めます。

#### リクエスト

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `email` | string | 必須 | メールアドレス形式 |
| `password` | string | 必須 | 文字列 |

```json
{
  "email": "demo@example.test",
  "password": "<password>"
}
```

#### 成功レスポンス `200`

```json
{
  "token": "<access-token>",
  "previous_login": {
    "id": 10,
    "logged_in_at": "2026-09-10T12:00:00+09:00",
    "ip_address": "192.0.2.10",
    "user_agent": "Example Browser"
  },
  "user": {
    "id": 1,
    "name": "デモユーザー",
    "email": "demo@example.test"
  }
}
```

初回ログイン時の`previous_login`は`null`です。認証情報が一致しない場合は`401`、同一メールアドレスとIPアドレスによる失敗が5回記録された後の試行は`429`です。

### `GET /api/user`

トークンに対応する`User`を返します。

### `POST /api/logout`

現在のリクエストで使用したトークンだけを削除します。

```json
{
  "message": "Logout"
}
```

### `PUT /api/user/password`

#### リクエスト

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `current_password` | string | 必須 | 現在のパスワードと一致 |
| `password` | string | 必須 | 8文字以上、現在のパスワードと異なる |
| `password_confirmation` | string | 必須 | `password`と一致 |

成功するとパスワードを更新して本人の全トークンを削除します。再ログインが必要です。

```json
{
  "message": "パスワードを変更しました。再度ログインしてください。"
}
```

### `POST /api/login-histories/{id}/read`

本人のログイン履歴の`read_at`を現在日時で更新します。すでに既読の場合も`200`を返します。他ユーザーの履歴は`404`です。

```json
{
  "message": "ログイン履歴を既読にしました。"
}
```

## 5. 入金

### `GET /api/incomes`

指定月に属する本人の入金を日付昇順で返します。

| クエリ | 型 | 必須 | 制約・初期値 |
|---|---|---:|---|
| `year` | integer | 任意 | 1900～2100。省略時は現在年 |
| `month` | integer | 任意 | 1～12。省略時は現在月 |

レスポンスは`Income`の配列です。

### `POST /api/incomes`

### `PUT /api/incomes/{id}`

登録と更新は同じ入力規則を使用します。

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `date` | date | 必須 | 有効な日付 |
| `amount` | integer | 必須 | 1以上 |
| `memo` | string / null | 任意 | 文字列 |

登録は`201`、更新は`200`で`Income`を返します。

### `GET /api/incomes/{id}`

本人の`Income`を返します。

### `DELETE /api/incomes/{id}`

本人の入金を削除します。

```json
{
  "message": "Deleted"
}
```

## 6. カテゴリ・支出

### `GET /api/categories`

カテゴリグループと、その配下のカテゴリを返します。

```json
[
  {
    "id": 1,
    "name": "生活費",
    "categories": [
      {
        "id": 1,
        "name": "食料品"
      }
    ]
  }
]
```

### `GET /api/expenses`

指定月に属する本人の支出を、カテゴリ・カテゴリグループ付きの日付昇順で返します。

| クエリ | 型 | 必須 | 制約・初期値 |
|---|---|---:|---|
| `year` | integer | 任意 | 1900～2100。省略時は現在年 |
| `month` | integer | 任意 | 1～12。省略時は現在月 |

### `POST /api/expenses`

### `PUT /api/expenses/{id}`

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `date` | date | 必須 | 有効な日付 |
| `amount` | integer | 必須 | 1以上 |
| `memo` | string / null | 任意 | 文字列 |
| `category_id` | integer | 必須 | 存在するカテゴリID |

登録は`201`、更新は`200`で`Expense`を返します。

### `GET /api/expenses/{id}`

本人の`Expense`をカテゴリ・カテゴリグループ付きで返します。

### `DELETE /api/expenses/{id}`

本人の支出を削除します。

```json
{
  "message": "Deleted"
}
```

## 7. 口座・月次資産残高

### `GET /api/accounts`

本人が所有する口座をID昇順で返します。レスポンスは`Account`の配列です。

### `GET /api/asset-balances`

指定月に属する本人の資産残高を口座ID昇順で返します。各残高には`account`を含みます。

| クエリ | 型 | 必須 | 制約・初期値 |
|---|---|---:|---|
| `year` | integer | 任意 | 1900～2100。省略時は現在年 |
| `month` | integer | 任意 | 1～12。省略時は現在月 |

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "account_id": 1,
      "amount": 150000,
      "date": "2026-09-01",
      "account": {
        "id": 1,
        "user_id": 1,
        "name": "A銀行",
        "type": "bank"
      }
    }
  ]
}
```

### `POST /api/asset-balances/bulk`

本人が所有する複数口座の月次残高を一括登録します。同じユーザー・口座・日付のデータは上書きします。

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `date` | string | 必須 | `YYYY-MM-01`、1900-01-01～2100-12-01 |
| `balances` | array | 必須 | 1件以上 |
| `balances.*.account_id` | integer | 必須 | 本人の口座ID、配列内で重複不可 |
| `balances.*.amount` | integer / null | 任意 | 0～2,147,483,647。`null`・省略は0として保存 |

```json
{
  "date": "2026-09-01",
  "balances": [
    {
      "account_id": 1,
      "amount": 150000
    }
  ]
}
```

成功時は`200`で登録・更新した資産残高を`data`へ返します。

## 8. 年次月別集計

### `GET /api/stats/{year}/monthly-summary`

`year`は1900～2100です。本人の入金、支出、資産残高を1月から12月まで集計します。

レスポンス構造の例です。`monthly`は説明用に1要素だけ掲載していますが、実際のレスポンスでは1月から12月までの12要素を返します。

```json
{
  "year": 2026,
  "accounts": [
    {
      "id": 1,
      "name": "A銀行",
      "type": "bank"
    }
  ],
  "monthly": [
    {
      "month": 1,
      "income": 300000,
      "expense": 68000,
      "assets": 360000,
      "assets_by_account": {
        "1": 360000
      }
    }
  ],
  "totals": {
    "income": 300000,
    "expense": 68000,
    "balance": 232000,
    "latest_assets": 360000,
    "asset_change": 0
  }
}
```

- `monthly`は常に12件です。データがない月は0になります。
- `accounts`は指定年に資産残高が存在する本人の口座です。
- `latest_assets`は指定年で最後に記録された月の資産合計です。
- `asset_change`は指定年の最初と最後に記録された月の資産合計差です。

## 9. 予算アラート

### 予算設定リソース

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | 設定ID |
| `user_id` | integer | 所有ユーザーID |
| `category_id` | integer | 対象カテゴリID |
| `monthly_budget` | integer | 月間予算 |
| `warning_threshold_percent` | integer | 警告割合 |
| `is_enabled` | boolean | 有効状態 |
| `category` | object | カテゴリとカテゴリグループ |

### `GET /api/budget-alert-settings`

本人の設定をカテゴリID昇順で返します。

### `POST /api/budget-alert-settings`

### `PUT /api/budget-alert-settings/{id}`

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `category_id` | integer | 必須 | 存在するカテゴリ。同一ユーザー内で重複不可 |
| `monthly_budget` | integer | 必須 | 1～4,294,967,295 |
| `warning_threshold_percent` | integer | 必須 | 1～99 |
| `is_enabled` | boolean | 必須 | 真偽値 |

登録は`201`、更新は`200`です。更新時はその設定に紐付く既読状態を削除します。

### `GET /api/budget-alert-settings/{id}`

本人の設定をカテゴリ・カテゴリグループ付きで返します。

### `DELETE /api/budget-alert-settings/{id}`

本人の設定と紐付く既読状態を削除します。

```json
{
  "message": "アラート設定を削除しました。"
}
```

### `GET /api/budget-alert-status`

当月1日から現在日までの支出をカテゴリ別に集計し、有効な設定について未読アラートを返します。

- 支出額が予算以上：`danger`
- 支出額が予算未満かつ警告割合以上：`warning`
- 警告割合未満：アラートなし

```json
{
  "alerts": [
    {
      "setting_id": 1,
      "category": {
        "id": 1,
        "name": "食料品",
        "group": {
          "id": 1,
          "name": "生活費"
        }
      },
      "level": "warning",
      "monthly_budget": 50000,
      "warning_threshold_percent": 70,
      "spent_amount": 38000,
      "usage_rate": 76,
      "message": "食料品の出金が設定金額の70%に達しました。"
    }
  ]
}
```

### `POST /api/budget-alert-settings/{id}/read`

現在発生している本人のアラートを、年・月・レベル単位で既読にします。同じ月でも`warning`既読後に`danger`へ変化した場合は再表示されます。

対象設定が無効、警告割合未満などで現在のアラートがない場合は`409`を返します。

## 10. 固定費

### 固定費リソース

| フィールド | 型 | 説明 |
|---|---|---|
| `id` | integer | 固定費ID |
| `user_id` | integer | 所有ユーザーID |
| `category_id` | integer | カテゴリID |
| `amount` | integer | 月額料金 |
| `memo` | string | 用途 |
| `is_enabled` | boolean | 有効状態 |
| `category` | object | カテゴリとカテゴリグループ |

### `GET /api/fixed-expenses`

本人の固定費をID昇順で返します。

### `POST /api/fixed-expenses`

### `PUT /api/fixed-expenses/{id}`

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `category_id` | integer | 必須 | 存在するカテゴリID |
| `amount` | integer | 必須 | 1～4,294,967,295 |
| `memo` | string | 必須 | 255文字以内 |
| `is_enabled` | boolean | 必須 | 真偽値 |

登録は`201`、更新は`200`で、カテゴリ・カテゴリグループ付きの固定費を返します。

### `GET /api/fixed-expenses/{id}`

本人の固定費をカテゴリ・カテゴリグループ付きで返します。

### `GET /api/fixed-expenses/process-preview`

| クエリ | 型 | 必須 | 制約 |
|---|---|---:|---|
| `target_month` | string | 必須 | `YYYY-MM`形式の現在月 |

有効かつ対象月に未処理の固定費を返します。

```json
{
  "target_month": "2026-09",
  "expense_date": "2026-09-01",
  "fixed_expenses": [],
  "count": 0,
  "total_amount": 0
}
```

### `POST /api/fixed-expenses/process`

| フィールド | 型 | 必須 | 制約 |
|---|---|---:|---|
| `target_month` | string | 必須 | `YYYY-MM`形式の現在月 |

対象月に未処理の有効な固定費から、月初日付の支出と処理履歴をトランザクション内で作成します。

```json
{
  "message": "固定費の出金処理が完了しました。",
  "target_month": "2026-09",
  "expense_date": "2026-09-01",
  "created_count": 2,
  "skipped_count": 0,
  "total_amount": 13000
}
```

- 同じ固定費と対象月の組み合わせは再処理しません。
- 初回処理後に追加した固定費は、同じ月に再実行すると追加分だけ処理します。
- 生成された支出を削除しても処理履歴は残り、同じ固定費を再生成しません。
