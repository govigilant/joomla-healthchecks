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

WORK_DIR="$WORK_DIR" DIST_ZIP="$DIST_DIR/$ZIP_NAME" python3 - <<'PY'
import os
from pathlib import Path
import zipfile

root = Path(os.environ["WORK_DIR"])
zip_path = Path(os.environ["DIST_ZIP"])

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as archive:
    for item in root.rglob("*"):
        if item.is_file():
            archive.write(item, item.relative_to(root))
PY

rm -rf "$WORK_DIR"

echo "Package created at $DIST_DIR/$ZIP_NAME"
