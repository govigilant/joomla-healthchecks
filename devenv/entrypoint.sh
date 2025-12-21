#!/usr/bin/env bash
set -euo pipefail

/usr/local/bin/joomla-setup.sh

docker-php-entrypoint php-fpm -D

exec "$@"
