# 家計簿アプリAPI側

LaravelとLaravel Sanctumで構築した、家計簿アプリのJSON APIです。
入出金、月次資産残高、年次集計、予算アラート、月次固定費をユーザーごとに管理します。

## プロジェクト構成

APIとフロントエンドは別々のリポジトリです。ローカルでは、次のように同じ親ディレクトリへ配置できます。

```text
kakeibo-mobile-project/
├── kakeibo-mobile-api/      # Laravel API（このリポジトリ）
└── kakeibo-mobile-front/    # Next.jsフロントエンド
```

- API：[`kakeibo-mobile-api`](https://github.com/sub-law/kakeibo-mobile-api)
- フロントエンド：[`kakeibo-mobile-front`](https://github.com/sub-law/kakeibo-mobile-front)

## 設計資料

- [API仕様書](docs/api-specification.md)
- [ER図（Mermaid形式）](docs/er-diagram.md)
- [認証フロー](docs/authentication-flow.md)

## 🚀 セットアップ手順

### 0. Docker Desktopを起動

初回セットアップを始める前に、Docker Desktopを起動し、Docker Engineが利用可能な状態になるまで待ってください。
WindowsでWSL 2を使用する場合は、Docker DesktopのWSL Integrationで利用するディストリビューションを有効にします。

```bash
docker version
```

このコマンドでClientとServerの情報が表示されてから、以下の手順へ進みます。

### 1. リポジトリをクローン

```bash
git clone <リポジトリURL> <フォルダ名>
cd <フォルダ名>
```

### 2. `.env` を作成

```bash
cp .env.example .env
```

#### コピーされた`.env`のデフォルトユーザー設定項目を編集

実在しないローカル確認用メールアドレス（例：`local-user@example.test`）と、
私用のパスワードとは異なるローカル専用パスワードを設定してください。以下は記入例です。

```dotenv
DEFAULT_USER_EMAIL=local-user@example.test
DEFAULT_USER_PASSWORD=change-this-local-password
```

## 🧰 初回セットアップ

### 3. Composer インストール（初回のみ）

ローカルに PHP を入れていない場合でも、Sail の公式 Composer イメージでインストールできます。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. コンテナのビルド

```bash
./vendor/bin/sail build
```

### 5. コンテナをバックグラウンドで起動

```bash
./vendor/bin/sail up -d
```

---

## 🔧 Laravel 初期設定

### 6. アプリキー生成

```bash
./vendor/bin/sail artisan key:generate
```

### 7. マイグレーション & 初期データ投入

以下のSeederで、ローカル確認に必要な初期ユーザー、カテゴリ、口座、架空のデモデータを登録します。

- `UserSeeder`
- `CategoryGroupSeeder`
- `CategorySeeder`
- `AccountSeeder`
- `DemoDataSeeder`（実行月と直近2か月の入出金・資産残高、予算アラート、月次固定費）

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

エラーが発生した場合は、コンテナの起動状態、DB接続設定、マイグレーションの適用状況を確認してください。
DBを初期化するコマンドは既存データを削除するため、必要性と対象を確認せず実行しないでください。

DB確認用URL：

```
http://localhost:8080
```

---

## 各キャッシュクリアコマンド

```bash
./vendor/bin/sail artisan route:clear
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
```

## 🔐 認証・認可の設計判断

| 項目 | 実装と目的 |
| --- | --- |
| 認証方式 | Next.jsから利用するJSON APIとして、Laravel SanctumのBearer Tokenを採用 |
| APIの保護 | ログイン以外の全APIを`auth:sanctum`配下に置き、未認証アクセスを`401`で拒否 |
| 所有データの分離 | 入出金・口座・残高・設定などを認証ユーザーの関連または`user_id`条件から取得 |
| 他ユーザーのID指定 | ID指定の取得・更新・削除では、データの存在を外部へ示さないため、本人の対象として見つからない場合は`404`を返す |
| トークン管理 | ログイン時は既存トークンを全削除して1件発行、ログアウト時は使用中トークンだけを削除 |
| パスワード変更 | 更新と本人の全トークン削除を同じトランザクションで行い、再ログインを要求 |
| ログイン試行制限 | 正規化したメールアドレスとIPアドレスをHMAC化したキーで、5回・60秒の制限を適用 |

実装の流れは[認証フロー](docs/authentication-flow.md)、レスポンスとステータスの詳細は[API仕様書](docs/api-specification.md)を参照してください。

### アクセストークンの有効期限

SanctumのBearerトークンは、発行から30日（43,200分）で無効になります。
既定値は `config/sanctum.php` に設定されており、必要な場合は環境変数で上書きできます。

```dotenv
SANCTUM_EXPIRATION=43200
```

- 有効期限は最終利用日時ではなく、トークンの発行日時を基準に判定されます
- APIへ設定を反映した時点で、発行から30日を超えた既存トークンも無効になります
- 期限切れトークンによるAPIアクセスは401を返し、再ログインが必要になります
- この設定変更にDBマイグレーションは必要ありません

## 🧪 テスト実行

テストは SQLite のインメモリデータベース上で実行されるため、開発用データベースには影響しません。

API側の全テストを実行：

```bash
./vendor/bin/sail artisan test
```

### CI

GitHub Actionsの[API CI](.github/workflows/ci.yml)で、次の条件により全テストを自動実行します。

- `develop`または`main`へのPull Request
- `develop`または`main`へのpush
- GitHub上からの手動実行

CIはPHP 8.4とSQLiteインメモリDBを使用し、`composer test`を実行します。デプロイ処理は含みません。

支出APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/ExpenseApiTest.php
```

入金APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/IncomeApiTest.php
```

口座・資産残高APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/AssetBalanceApiTest.php
```

認証・パスワード変更・アクセストークン有効期限のテストケースだけを実行：

- 発行から30日以内のトークンで認証済みAPIへアクセスできる
- 発行から30日を超えたトークンは401で拒否される

```bash
./vendor/bin/sail artisan test tests/Feature/AuthApiTest.php
```

ログイン履歴・既読APIのテストケースだけを実行：

- 未認証ユーザーはログイン履歴を既読にできない
- 本人のログイン履歴を既読にでき、履歴自体は保持される
- 他ユーザーのログイン履歴を既読にできない

```bash
./vendor/bin/sail artisan test tests/Feature/LoginHistoryApiTest.php
```

年次集計APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/StatsApiTest.php
```

予算アラート設定APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/BudgetAlertSettingApiTest.php
```

予算アラート判定・既読APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/BudgetAlertStatusApiTest.php
```

固定費設定APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/FixedExpenseApiTest.php
```

固定費一括出金処理のテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/ProcessFixedExpensesTest.php
```

デモデータSeederの内容と再実行時の重複防止を確認：

```bash
./vendor/bin/sail artisan test tests/Feature/DemoDataSeederTest.php
```

### マルチユーザー・口座分離の自動テスト

以下のケースをまとめて確認します。

- ログインユーザーには本人の口座だけが返される
- 所有者が設定されていない口座はDBのNOT NULL制約により登録を拒否される
- 他ユーザーの口座を指定した月次残高登録は拒否される
- 月次残高と年次集計に他ユーザーのデータが混在しない
- 非公開の対話コマンドでユーザーと専用口座を作成できる

```bash
./vendor/bin/sail artisan test \
    tests/Feature/AssetBalanceApiTest.php \
    tests/Feature/StatsApiTest.php \
    tests/Feature/ProvisionHouseholdUserCommandTest.php
```

### マルチユーザー・口座分離の手動確認

1. 未適用のマイグレーションを実行します。

```bash
./vendor/bin/sail artisan migrate
```

2. Tinkerで、既存口座が既存ユーザーに紐付いていることを確認します。

```bash
./vendor/bin/sail artisan tinker
```

```php
App\Models\User::with('accounts:id,user_id,name,type')->get(['id', 'name']);
App\Models\Account::whereNull('user_id')->count();
```

すべての既存口座の `user_id` が既存ユーザーの `id` と一致し、
未紐付け口座の件数が `0` なら正常です。

3. Tinkerを終了し、2人目のユーザーと専用口座を作成します。

```php
exit
```

```bash
./vendor/bin/sail artisan app:provision-household-user
```

名前、メールアドレス、12文字以上のパスワード、口座名、口座種別を
対話形式で入力します。実際の認証情報はREADMEやGitへ記録しないでください。

4. 既存ユーザーで以下を確認します。

- 従来の入出金、口座、月次残高が表示される
- 2人目のユーザーの口座やデータが表示されない

5. ログアウトして2人目のユーザーで以下を確認します。

- 2人目のユーザーとしてログインできる
- 作成時に登録した専用口座だけが表示される
- 入出金と月次残高が初期状態では空である
- 登録した入出金と月次残高が既存ユーザー側に表示されない

## 📦 動作環境

- Laravel Framework **13.5.0**
- Laravel Sanctum **4.3.1**
- PHP **8.5**（ローカルのLaravel Sailランタイム）
- PHP **8.4**（GitHub Actions）
- PHP要件 **^8.4**
- Laravel Sail **1.57.0**
- MySQL **8.4**（ローカル開発DB）
- SQLite インメモリDB（自動テスト）

バージョンは`compose.yaml`、`composer.json`、`composer.lock`、`.github/workflows/ci.yml`の現行設定に基づきます。

## 既知の制約

- ローカルでのセットアップと提出前確認を対象とし、デプロイ設定は含みません
- 公開ユーザー登録APIはなく、初期ユーザーは`.env`の`DEFAULT_USER_EMAIL`と`DEFAULT_USER_PASSWORD`を使ってSeederから作成します
- カテゴリと口座は初期データとしてSeederで管理し、APIでは一覧取得だけを提供します
- アクセストークンの更新専用APIはありません。期限切れやパスワード変更後は再ログインが必要です
- `DemoDataSeeder`は実行月を基準に直近3か月分を作成し、最初のユーザーへ紐付けます
- ローカル開発はMySQL、自動テストはSQLiteを使用するため、DBエンジン固有の差異はCIに加えてローカル確認でも確認します

---

## 📘 補足

- READMEではSailコマンドを`./vendor/bin/sail`で記載しています。必要に応じて`sail`エイリアスを設定できます
- `.env` の DB 接続情報は Sail のデフォルト設定で動作します
- MySQL・Redis・Mailpit などは Sail 起動時に自動で立ち上がります
