#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
WORK_DIR="$ROOT_DIR/.jed-build"
ZIP_NAME="${ZIP_NAME:-plg_system_vigilanthealthchecks.zip}"

rm -rf "$WORK_DIR"
mkdir -p "$WORK_DIR"

# Copy only the files needed to assemble the plugin
tar -cf - \
    --exclude=.git \
    --exclude=.github \
    --exclude=.idea \
    --exclude=.vscode \
    --exclude=dist \
    --exclude=.jed-build \
    --exclude=build \
    --exclude=devenv \
    --exclude=scripts \
    --exclude=tests \
    --exclude=stubs \
    --exclude=art \
    --exclude=vendor \
    -C "$ROOT_DIR" . | tar -xf - -C "$WORK_DIR"

composer install \
    --no-dev \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-interaction \
    --working-dir="$WORK_DIR"

mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/$ZIP_NAME"

WORK_DIR="$WORK_DIR" DIST_ZIP="$DIST_DIR/$ZIP_NAME" php <<'PHP'
<?php
$root = getenv('WORK_DIR');
$zipPath = getenv('DIST_ZIP');
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create ZIP at {$zipPath}\n");
    exit(1);
}
$flags = FilesystemIterator::SKIP_DOTS;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, $flags),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $zip->addFile($file->getPathname(), $relative);
    }
}
$zip->close();
PHP

rm -rf "$WORK_DIR"

echo "Package created at $DIST_DIR/$ZIP_NAME"
