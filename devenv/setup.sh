#!/usr/bin/env bash
set -euo pipefail

JOOMLA_ROOT=${JOOMLA_ROOT:-/var/www/html}
JOOMLA_SOURCE_PATH=${JOOMLA_SOURCE_PATH:-/usr/src/joomla}
SEED_MARKER=${SEED_MARKER:-$JOOMLA_ROOT/.joomla_seeded}
PLUGIN_PATH=${PLUGIN_PATH:-$JOOMLA_ROOT/plugins/system/vigilanthealthchecks}
HOST_PACKAGE_PATH=${HOST_PACKAGE_PATH:-/srv/package}
JOOMLA_DEFAULT_LANGUAGE=${JOOMLA_DEFAULT_LANGUAGE:-en-GB}
JOOMLA_DEFAULT_META_LANGUAGE=${JOOMLA_DEFAULT_META_LANGUAGE:-$JOOMLA_DEFAULT_LANGUAGE}
DB_HOST=${JOOMLA_DB_HOST:-${DB_HOST:-db}}
DB_PORT=${JOOMLA_DB_PORT:-${DB_PORT:-3306}}
DB_NAME=${JOOMLA_DB_NAME:-joomla}
DB_USER=${JOOMLA_DB_USER:-joomla}
DB_PASSWORD=${JOOMLA_DB_PASSWORD:-joomla}
DB_PREFIX=${JOOMLA_DB_PREFIX:-vg_}
JOOMLA_SITE_NAME=${JOOMLA_SITE_NAME:-Vigilant Joomla Healthchecks}
JOOMLA_ADMIN_NAME=${JOOMLA_ADMIN_NAME:-Admin User}
JOOMLA_ADMIN_USER=${JOOMLA_ADMIN_USER:-admin}
JOOMLA_ADMIN_PASSWORD=${JOOMLA_ADMIN_PASSWORD:-Admin1234!@#}
JOOMLA_ADMIN_EMAIL=${JOOMLA_ADMIN_EMAIL:-admin@example.com}

log() {
    printf '[joomla-dev] %s\n' "$*"
}

populate_joomla_root() {
    if [[ ! -d "$JOOMLA_SOURCE_PATH" ]]; then
        return
    fi

    if [[ -f "$SEED_MARKER" && -f "$JOOMLA_ROOT/index.php" ]]; then
        return
    fi

    log "Seeding Joomla files into $JOOMLA_ROOT..."
    mkdir -p "$JOOMLA_ROOT"
    cp -a "$JOOMLA_SOURCE_PATH/." "$JOOMLA_ROOT/"
    touch "$SEED_MARKER"
}

restore_installation_files() {
    local source_dir="$JOOMLA_SOURCE_PATH/installation"
    local installer="$source_dir/joomla.php"
    local target_installer="$JOOMLA_ROOT/installation/joomla.php"

    if [[ -f "$target_installer" ]]; then
        return
    fi

    if [[ ! -f "$installer" ]]; then
        log "Installation source not found at $installer"
        return
    fi

    log "Restoring Joomla installation files..."
    rm -rf "$JOOMLA_ROOT/installation"
    cp -a "$source_dir" "$JOOMLA_ROOT/"
}

wait_for_database() {
    log "Waiting for database at ${DB_HOST}:${DB_PORT}..."

    for attempt in $(seq 1 60); do
        if mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" >/dev/null 2>&1; then
            log "Database is available."
            return 0
        fi

        sleep 2
    done

    log "Database did not become available in time."
    exit 1
}

install_package_dependencies() {
    if [[ ! -f "$HOST_PACKAGE_PATH/composer.json" ]]; then
        return
    fi

    if [[ -f "$HOST_PACKAGE_PATH/vendor/autoload.php" ]]; then
        return
    fi

    log "Installing composer dependencies for the plugin..."
    composer install --working-dir="$HOST_PACKAGE_PATH" --no-interaction --no-progress
}

install_site() {
    if [[ -f "$JOOMLA_ROOT/configuration.php" ]]; then
        return
    fi

    restore_installation_files

    log "Running Joomla installer..."
    php "$JOOMLA_ROOT/installation/joomla.php" install \
        --site-name="$JOOMLA_SITE_NAME" \
        --admin-user="$JOOMLA_ADMIN_NAME" \
        --admin-username="$JOOMLA_ADMIN_USER" \
        --admin-password="$JOOMLA_ADMIN_PASSWORD" \
        --admin-email="$JOOMLA_ADMIN_EMAIL" \
        --db-type=mysqli \
        --db-host="$DB_HOST" \
        --db-name="$DB_NAME" \
        --db-user="$DB_USER" \
        --db-pass="$DB_PASSWORD" \
        --db-prefix="$DB_PREFIX"

    if [[ -d "$JOOMLA_ROOT/installation" ]]; then
        rm -rf "$JOOMLA_ROOT/installation"
    fi
}

run_cli() {
    if [[ ! -f "$JOOMLA_ROOT/cli/joomla.php" ]]; then
        return 1
    fi

    php "$JOOMLA_ROOT/cli/joomla.php" "$@"
}

install_plugin() {
    if [[ ! -f "$PLUGIN_PATH/vigilanthealthchecks.xml" ]]; then
        log "Plugin manifest not found at $PLUGIN_PATH"
        return
    fi

    log "Registering Vigilant Healthchecks plugin..."
    run_cli extension:discover || true

    tmp_zip=$(mktemp /tmp/vigilant-XXXXXX.zip)
    rm -f "$tmp_zip"
    (
        cd "$(dirname "$PLUGIN_PATH")" && zip -qr "$tmp_zip" "$(basename "$PLUGIN_PATH")"
    )

    run_cli extension:install --path="$tmp_zip" || true
    rm -f "$tmp_zip"

    enable_plugin
}

enable_plugin() {
    local sql="UPDATE ${DB_PREFIX}extensions SET enabled=1, state=0 WHERE type='plugin' AND folder='system' AND element='vigilanthealthchecks';"

    MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u"$DB_USER" "$DB_NAME" -e "$sql" || true
}

ensure_language_configuration() {
    local config="$JOOMLA_ROOT/configuration.php"

    if [[ ! -f "$config" ]]; then
        return
    fi

    if grep -q "public \$language" "$config"; then
        return
    fi

    log "Adding default language configuration..."

    php /usr/local/bin/ensure-language.php "$config" "$JOOMLA_DEFAULT_LANGUAGE" "$JOOMLA_DEFAULT_META_LANGUAGE"
}

enable_caching() {
    local config="$JOOMLA_ROOT/configuration.php"

    if [[ ! -f "$config" ]]; then
        return
    fi

    log "Enabling Joomla caching for development environment..."
    run_cli config:set caching=1 || true
}

set_permissions() {
    local writable_paths=(
        "$JOOMLA_ROOT/cache"
        "$JOOMLA_ROOT/tmp"
        "$JOOMLA_ROOT/logs"
        "$JOOMLA_ROOT/administrator/cache"
        "$JOOMLA_ROOT/administrator/logs"
    )

    for path in "${writable_paths[@]}"; do
        if [[ -d "$path" ]]; then
            chown -R www-data:www-data "$path" || true
        fi
    done
}

main() {
    populate_joomla_root
    wait_for_database
    install_package_dependencies
    install_site
    enable_caching
    ensure_language_configuration
    install_plugin
    set_permissions
}

main "$@"
