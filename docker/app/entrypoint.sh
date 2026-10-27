#!/usr/bin/env sh
set -eu

umask 002

if [ -f artisan ]; then
    mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs
fi

exec "$@"

