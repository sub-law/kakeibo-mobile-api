#!/bin/bash
set -e

echo "🚀 Starting deployment script..."

# 1. Laravel のキャッシュ系は削除（Railway 起動直後は失敗しやすい）
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

# 2. Nginx の設定テスト
echo "Testing Nginx configuration..."
nginx -t

# 3. Nginx をバックグラウンド起動
echo "Starting Nginx in background..."
nginx

# 4. PHP-FPM をフォアグラウンドで起動（コンテナ維持）
echo "Starting PHP-FPM in foreground..."
php-fpm -F
