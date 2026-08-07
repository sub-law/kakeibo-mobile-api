# API要件・テストケースレポート

## 1. 文書情報

| 項目 | 内容 |
|---|---|
| 文書名 | API要件・テストケースレポート |
| 対象システム | 家計簿モバイルアプリケーション API |
| 対象プロジェクト | kakeibo-mobile-api |
| 更新日 | 2026-08-06 |
| APIフレームワーク | Laravel 13.5.0 |
| PHP | 8.4.8 |
| 認証方式 | Laravel Sanctum 4.3.1 / Bearer Token |
| 自動テスト | PHPUnit 12.5.23 / Laravel Feature Test |

### 1.1 目的

本書は、現行の routes/api.php、Controller、FormRequest、Service、Model、Migration、Feature Testを基準として、API仕様とテスト結果を整理する。

### 1.2 対象機能

- ログイン、認証ユーザー取得、ログアウト、パスワード変更
- 入金の一覧、登録、詳細、更新、削除
- 支出カテゴリ一覧
- 支出の一覧、登録、詳細、更新、削除
- 口座一覧、月次資産残高の一覧と一括登録
- 年次の入出金・資産集計
- 予算アラート設定、判定、既読管理
- 固定費設定、出金プレビュー、一括出金

## 2. 共通API仕様

### 2.1 ベースパス

すべてのAPIルートは /api 配下で提供する。

### 2.2 認証

POST /api/login を除く全APIは auth:sanctum ミドルウェアで保護する。

~~~http
Authorization: Bearer {token}
Accept: application/json
~~~

ログイン成功時は同ユーザーの既存トークンを削除して新しいトークンを発行する。ログアウト時は現在使用中のトークンだけを削除し、パスワード変更時は対象ユーザーの全トークンを削除する。

### 2.3 ユーザー分離

入金、支出、資産残高、年次集計、予算アラート設定・既読、固定費設定・処理履歴は、認証ユーザーのデータだけを対象とする。

他ユーザーが所有する詳細データの参照・更新・削除・既読操作は404とし、口座とカテゴリは共通マスタとして扱う。

### 2.4 主なHTTPステータス

| ステータス | 用途 |
|---:|---|
| 200 OK | 取得、更新、削除、認証、集計、一括処理の成功 |
| 201 Created | 入金、支出、予算アラート設定、固定費の登録成功 |
| 401 Unauthorized | 認証失敗、トークンなし、無効なトークン |
| 404 Not Found | 対象なし、または他ユーザーのデータを指定 |
| 409 Conflict | 現在表示対象でない予算アラートの既読操作 |
| 422 Unprocessable Entity | 入力値のバリデーションエラー |

## 3. APIルート仕様

| No. | 機能 | メソッド | エンドポイント | Controller / 処理 |
|---:|---|---|---|---|
| 1 | ログイン | POST | /api/login | LoginController::__invoke |
| 2 | 認証ユーザー取得 | GET | /api/user | ルートクロージャ |
| 3 | ログアウト | POST | /api/logout | LogoutController::__invoke |
| 4 | パスワード変更 | PUT | /api/user/password | PasswordController::update |
| 5 | 入金一覧 | GET | /api/incomes | IncomeController::index |
| 6 | 入金登録 | POST | /api/incomes | IncomeController::store |
| 7 | 入金詳細 | GET | /api/incomes/{id} | IncomeController::show |
| 8 | 入金更新 | PUT | /api/incomes/{id} | IncomeController::update |
| 9 | 入金削除 | DELETE | /api/incomes/{id} | IncomeController::destroy |
| 10 | カテゴリ一覧 | GET | /api/categories | CategoryController::index |
| 11 | 支出一覧 | GET | /api/expenses | ExpenseController::index |
| 12 | 支出登録 | POST | /api/expenses | ExpenseController::store |
| 13 | 支出詳細 | GET | /api/expenses/{id} | ExpenseController::show |
| 14 | 支出更新 | PUT | /api/expenses/{id} | ExpenseController::update |
| 15 | 支出削除 | DELETE | /api/expenses/{id} | ExpenseController::destroy |
| 16 | 口座一覧 | GET | /api/accounts | AccountController::index |
| 17 | 月次資産残高一覧 | GET | /api/asset-balances | AssetBalanceController::index |
| 18 | 月次資産残高一括登録 | POST | /api/asset-balances/bulk | AssetBalanceController::bulk |
| 19 | 年次集計 | GET | /api/stats/{year}/monthly-summary | StatsController::monthlySummary |
| 20 | 予算アラート設定一覧 | GET | /api/budget-alert-settings | BudgetAlertSettingController::index |
| 21 | 予算アラート設定登録 | POST | /api/budget-alert-settings | BudgetAlertSettingController::store |
| 22 | 予算アラート設定詳細 | GET | /api/budget-alert-settings/{id} | BudgetAlertSettingController::show |
| 23 | 予算アラート設定更新 | PUT | /api/budget-alert-settings/{id} | BudgetAlertSettingController::update |
| 24 | 予算アラート設定削除 | DELETE | /api/budget-alert-settings/{id} | BudgetAlertSettingController::destroy |
| 25 | 現在の予算アラート取得 | GET | /api/budget-alert-status | BudgetAlertStatusController::show |
| 26 | 予算アラート既読 | POST | /api/budget-alert-settings/{id}/read | BudgetAlertReadController::store |
| 27 | 固定費一覧 | GET | /api/fixed-expenses | FixedExpenseController::index |
| 28 | 固定費登録 | POST | /api/fixed-expenses | FixedExpenseController::store |
| 29 | 固定費詳細 | GET | /api/fixed-expenses/{id} | FixedExpenseController::show |
| 30 | 固定費更新 | PUT | /api/fixed-expenses/{id} | FixedExpenseController::update |
| 31 | 固定費出金プレビュー | GET | /api/fixed-expenses/process-preview | FixedExpenseProcessController::preview |
| 32 | 固定費一括出金 | POST | /api/fixed-expenses/process | FixedExpenseProcessController::store |

POST /api/login 以外は認証が必要である。

## 4. 機能別仕様

### 4.1 認証

#### ログイン

| 入力 | 規則 |
|---|---|
| email | 必須、メールアドレス形式 |
| password | 必須、文字列 |

成功時は既存トークンを削除し、トークンと id・name・email を含むユーザー情報を返す。不一致は401、入力不備は日本語の422を返す。

#### パスワード変更

| 入力 | 規則 |
|---|---|
| current_password | 必須、現在のパスワードと一致 |
| password | 必須、8文字以上、確認入力と一致、現在のパスワードと異なる |
| password_confirmation | password の確認入力 |

成功時はパスワードを更新して対象ユーザーの全トークンを削除する。

### 4.2 入金・支出

入金と支出の一覧は、year・monthを省略した場合に現在年月を使用し、認証ユーザーのデータを日付昇順で返す。

| 対象 | 主な入力 |
|---|---|
| 入金登録・更新 | date、1円以上のamount、任意のmemo |
| 支出登録・更新 | date、1円以上のamount、存在するcategory_id、任意のmemo |

支出の一覧と詳細にはカテゴリおよびカテゴリグループを含める。詳細・更新・削除は所有者のデータだけを対象とする。

### 4.3 口座・月次資産残高

GET /api/accounts は共通口座マスタを返す。

GET /api/asset-balances はyear・monthで対象月を指定し、認証ユーザーの残高を口座ID順で返す。monthは1から12の範囲とする。

POST /api/asset-balances/bulk の入力は次のとおり。

| 入力 | 規則 |
|---|---|
| date | 必須、有効な日付 |
| balances | 必須、配列 |
| balances.*.account_id | 必須、accounts.idに存在 |
| balances.*.amount | 任意、整数、0以上。NULL・省略は0 |

同じuser_id・account_id・dateのレコードは上書きする。現行フロントエンドは対象月の月初日をdateとして送信する。

### 4.4 年次集計

GET /api/stats/{year}/monthly-summary は1900から2100の年を受け付け、次の情報を返す。

- 口座一覧
- 1月から12月の入金、支出、総資産、口座別資産
- 年間入金、年間支出、年間収支
- 最新総資産、年内の資産増減

データのない月は0で補完し、認証ユーザーのデータだけを集計する。

### 4.5 予算アラート

設定は認証ユーザーとカテゴリの組み合わせで一意とする。

| 入力 | 規則 |
|---|---|
| category_id | 必須、存在するカテゴリ、同一ユーザー内で重複不可 |
| monthly_budget | 必須、1から4,294,967,295 |
| warning_threshold_percent | 必須、1から99 |
| is_enabled | 必須、真偽値 |

当月1日から当日までのカテゴリ別支出を集計し、警告割合以上かつ予算未満はwarning、予算以上はdangerを返す。既読は設定ID・年・月・レベル単位で保存し、設定更新時に既読情報を削除する。

### 4.6 固定費

| 入力 | 規則 |
|---|---|
| category_id | 必須、存在するカテゴリ |
| amount | 必須、1から4,294,967,295 |
| memo | 必須、255文字以内 |
| is_enabled | 必須、真偽値 |

出金処理は当月のYYYY-MMだけを受け付ける。プレビューは有効かつ未処理の固定費、件数、合計、登録日となる月初日を返す。

一括出金は、処理時点の金額・用途・カテゴリを月初日付のexpensesへコピーし、fixed_expense_processesへ履歴を保存する。同月の処理済みデータはスキップし、生成した支出が削除されても処理履歴は残す。

## 5. Controller・Service構成

| クラス | 主な責務 |
|---|---|
| LoginController | 認証、既存トークン削除、新規トークン発行 |
| LogoutController | 現在のアクセストークン削除 |
| PasswordController | 現在パスワード照合、更新、全トークン削除 |
| IncomeController | 入金CRUD、年月絞り込み、所有者制御 |
| ExpenseController | 支出CRUD、カテゴリ読込、所有者制御 |
| CategoryController | カテゴリグループとカテゴリの階層返却 |
| AccountController | 共通口座マスタ返却 |
| AssetBalanceController | 月次残高一覧、一括登録・上書き |
| StatsController | 年検証、MonthlyStatsService呼び出し |
| BudgetAlertSettingController | 予算アラート設定CRUD、更新時の既読削除 |
| BudgetAlertStatusController | 現在の未読アラート返却 |
| BudgetAlertReadController | 現在のアラートレベルの既読保存 |
| FixedExpenseController | 固定費の一覧、登録、詳細、更新 |
| FixedExpenseProcessController | プレビューと一括出金処理 |
| MonthlyStatsService | 月別・年間の入出金と資産集計 |
| BudgetAlertService | warning・danger判定と既読除外 |
| FixedExpenseProcessingService | 固定費のプレビュー、支出・履歴作成 |

## 6. テーブル仕様

### 6.1 業務・認証対象テーブル

| No. | テーブル | 用途 | 主な制約 |
|---:|---|---|---|
| 1 | users | ユーザー情報 | emailが一意 |
| 2 | personal_access_tokens | Sanctumトークン | tokenが一意 |
| 3 | incomes | ユーザー別入金 | user_idを外部キーで保持 |
| 4 | category_groups | カテゴリ大分類 | categoriesと1対多 |
| 5 | categories | カテゴリ小分類 | category_group_idを外部キーで保持 |
| 6 | expenses | ユーザー別支出 | user_id・category_idを外部キーで保持 |
| 7 | accounts | 共通口座マスタ | name・typeを保持 |
| 8 | asset_balances | 口座別資産残高 | user_id・account_id・dateが一意 |
| 9 | budget_alert_settings | カテゴリ別予算設定 | user_id・category_idが一意 |
| 10 | budget_alert_reads | アラート既読 | setting_id・year・month・levelが一意 |
| 11 | fixed_expenses | 固定費設定 | user_id・category_idを外部キーで保持 |
| 12 | fixed_expense_processes | 月次出金履歴 | fixed_expense_id・target_monthとexpense_idが一意 |

詳細なカラムと関連は er-diagram.md およびマイグレーションを参照する。

### 6.2 Laravelフレームワーク管理用テーブル

| テーブル | 用途 |
|---|---|
| password_reset_tokens | パスワードリセット用トークン |
| sessions | セッション情報 |
| cache | キャッシュ |
| cache_locks | キャッシュロック |
| jobs | キュー |
| job_batches | ジョブバッチ |
| failed_jobs | 失敗ジョブ |

マイグレーションで作成するテーブルは、業務・認証対象12テーブルとLaravel管理用7テーブルの合計19テーブルである。

## 7. APIテスト

### 7.1 方針

- Laravel Feature TestでHTTPリクエストからDB更新までを検証する。
- RefreshDatabaseでテストごとにDB状態を初期化する。
- SQLiteインメモリDBを使用し、開発用DBへ影響を与えない。
- Sanctum認証、入力検証、所有者制御、境界値を検証する。
- バリデーションエラーの日本語メッセージを検証する。

### 7.2 テスト構成

| テストファイル | 件数 | 主な対象 |
|---|---:|---|
| AuthApiTest.php | 11 | ログイン、認証、ログアウト、パスワード変更 |
| IncomeApiTest.php | 8 | 入金CRUD、月別一覧、所有者制御 |
| ExpenseApiTest.php | 8 | 支出CRUD、カテゴリ情報、所有者制御 |
| AssetBalanceApiTest.php | 11 | 口座、残高一括登録、一覧、入力検証 |
| StatsApiTest.php | 4 | 12か月集計、ゼロ補完、所有者制御、年検証 |
| BudgetAlertSettingApiTest.php | 8 | 設定CRUD、重複、入力検証、所有者制御 |
| BudgetAlertStatusApiTest.php | 8 | warning・danger、既読、翌月、所有者制御 |
| FixedExpenseApiTest.php | 4 | 固定費設定、入力検証、所有者制御 |
| ProcessFixedExpensesTest.php | 6 | プレビュー、一括出金、二重防止、履歴 |
| 業務APIテスト合計 | 68 | 上記Feature Test |
| Laravel雛形テスト | 2 | Feature・Unit各1件 |
| 全テスト | 70 | 業務APIテストと雛形テスト |

### 7.3 実行方法

全テスト：

~~~bash
./vendor/bin/sail artisan test
~~~

機能別：

~~~bash
./vendor/bin/sail artisan test tests/Feature/AuthApiTest.php
./vendor/bin/sail artisan test tests/Feature/IncomeApiTest.php
./vendor/bin/sail artisan test tests/Feature/ExpenseApiTest.php
./vendor/bin/sail artisan test tests/Feature/AssetBalanceApiTest.php
./vendor/bin/sail artisan test tests/Feature/StatsApiTest.php
./vendor/bin/sail artisan test tests/Feature/BudgetAlertSettingApiTest.php
./vendor/bin/sail artisan test tests/Feature/BudgetAlertStatusApiTest.php
./vendor/bin/sail artisan test tests/Feature/FixedExpenseApiTest.php
./vendor/bin/sail artisan test tests/Feature/ProcessFixedExpensesTest.php
~~~

## 8. テスト実行結果

2026年8月4日にホストPHPで実行した結果：

| 項目 | 結果 |
|---|---:|
| 業務APIテスト | 68件成功 |
| Laravel雛形テスト | 2件成功 |
| 全テスト | 70件成功 |
| 全アサーション | 501件成功 |
| 失敗 | 0件 |

テストDBはSQLiteインメモリDBを使用した。SailはDockerまたはPodman停止のため、この実行結果では使用していない。
