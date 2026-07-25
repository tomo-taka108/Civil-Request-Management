#!/bin/sh
set -e

# storage/framework を名前付きボリュームに載せているため、
# 空ボリュームの初回起動時に Laravel が必要とするサブディレクトリを作成する。
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

# php-fpm（www-data）が storage / bootstrap/cache に書き込めるようにする。
# ローカル開発用途のため所有権を www-data に寄せる。
chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
