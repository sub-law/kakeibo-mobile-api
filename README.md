# 家計簿アプリAPI側

## プロジェクト構成

この家計簿アプリは、フロントエンドとAPIを別々のGitHubリポジトリで管理しています。

- フロントエンド：`kakeibo-mobile-front`
- API：`kakeibo-mobile-api`

ローカルでは、次の構成で配置することを推奨します。

````text
kakeibo-mobile/
├── kakeibo-mobile-front/
└── kakeibo-mobile-api/
````

## 🚀 セットアップ手順

### 1. リポジトリをクローン

```bash
git clone <リポジトリURL> <フォルダ名>
cd <フォルダ名>
````

### 2. `.env` を作成

```bash
cp .env.example .env
```

#### コピーされた.envファイル内のデフォルトユーザー設定項目に任意のメールアドレス・パスワードを入力

DEFAULT_USER_EMAIL=  
DEFAULT_USER_PASSWORD=

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

### 7. マイグレーション & ダミーデータ投入

以下のファイルは自身で編集、使用可  
AccountSeeder（口座名）  
CategoryGroupSeeder（大分類）  
CategorySeeder（小分類）

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan db:seed --class=DashboardDummyDataSeeder
```

上手くいかなかった場合  
※データの削除も伴うので注意して使用してください

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

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

## 🧪 テスト実行

テストは SQLite のインメモリデータベース上で実行されるため、開発用データベースには影響しません。

API側の全テストを実行：

```bash
./vendor/bin/sail artisan test
```

支出APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/ExpenseApiTest.php
```

入金APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/IncomeApiTest.php
```

資産残高APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/AssetBalanceApiTest.php
```

認証APIのテストケースだけを実行：

```bash
./vendor/bin/sail artisan test tests/Feature/AuthApiTest.php
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

## 📦 動作環境

- Laravel Framework **13.5.0**
- PHP **8.5**
- Laravel Sail（Docker ベース）

---

## 📘 補足

- READMEでは./vendor/bin/sailを使用します。エイリアスを設定している場合はsailに置き換えられます。
- `.env` の DB 接続情報は Sail のデフォルト設定で動作します
- MySQL・Redis・Mailpit などは Sail 起動時に自動で立ち上がります

## ER図

現在のマイグレーションを基準にしたMermaid形式のER図は、[docs/er-diagram.md](docs/er-diagram.md)を参照してください。
