#!/bin/sh
# 本番用 entrypoint（インフラ設計書 6.3）。
# ローカル用 entrypoint.sh は storage 初期化のみだが、本番では起動時に
# マイグレーションと各種キャッシュ最適化を行う。
set -e

# storage/framework の必要サブディレクトリを用意（空ボリューム初回起動対策）。
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache || true

# 本番マイグレーション（対話プロンプトを出さない）。
# CREATE DATABASE / ユーザー作成は RDS 側で事前実施済みである前提（設計書 3.1・4.3）。
php artisan migrate --force

# 設定・ルート・ビューのキャッシュ（APP_KEY 等が確定済みであること）。
# config:cache 実行後は .env が読まれなくなるため、.env は事前に確定させておく。
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
