#!/bin/sh
# What happens on the server after new code arrives. See DEPLOYMENT.md.
# Run by .cpanel.yml, or by hand:  sh scripts/cpanel-deploy.sh
#
# If a step fails, the script stops and leaves the site in maintenance mode.
# PHP and COMPOSER_BIN may be set to the full paths if they are not found.

set -e
cd "$(dirname "$0")/.."

# cPanel may run deployments without HOME, which Composer needs.
if [ -z "$HOME" ]; then
    HOME=$(cd ~ && pwd)
    export HOME
fi

new_enough() {
    "$1" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' >/dev/null 2>&1
}

if [ -z "$PHP" ]; then
    for candidate in \
        /opt/cpanel/ea-php84/root/usr/bin/php \
        /opt/cpanel/ea-php85/root/usr/bin/php \
        /opt/alt/php84/usr/bin/php \
        /opt/alt/php85/usr/bin/php \
        "$(command -v php || true)"; do
        if [ -n "$candidate" ] && new_enough "$candidate"; then
            PHP=$candidate
            break
        fi
    done
fi

if [ -z "$PHP" ] || ! new_enough "$PHP"; then
    echo "ERROR: PHP 8.4.1 or newer was not found." >&2
    exit 1
fi

if [ -z "$COMPOSER_BIN" ]; then
    for candidate in "$HOME/composer.phar" /opt/cpanel/composer/bin/composer /usr/local/bin/composer "$(command -v composer || true)"; do
        if [ -n "$candidate" ] && [ -f "$candidate" ]; then
            COMPOSER_BIN=$candidate
            break
        fi
    done
fi

if [ -z "$COMPOSER_BIN" ]; then
    echo "ERROR: Composer was not found." >&2
    exit 1
fi

# "Configured" means .env exists and has an app key. Before that, only install the packages.
configured=false
if [ -f .env ] && grep -q '^APP_KEY=base64:' .env; then
    configured=true
fi

if [ "$configured" = true ] && [ -f vendor/autoload.php ]; then
    "$PHP" artisan down --render="errors::503"
fi

"$PHP" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-progress

if [ "$configured" = false ]; then
    echo "Packages installed. Create .env and its app key (DEPLOYMENT.md, steps 5 and 6), then deploy again."
    exit 0
fi

"$PHP" artisan migrate --force
"$PHP" artisan optimize
"$PHP" artisan up

echo "Deployed."
