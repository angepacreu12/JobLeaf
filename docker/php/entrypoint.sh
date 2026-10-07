#!/bin/sh
set -e

# The project is mounted from the laptop, so vendor/ comes from there.
# On a fresh clone it doesn't exist yet: install it once.
if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
    echo "[php] vendor/ missing -> composer install"
    composer install --no-interaction --no-progress --prefer-dist
fi

exec "$@"
