#!/usr/bin/env bash
set -euo pipefail

SLUG="gt-link-manager"
VERSION=$(grep -m1 "Version:" gt-link-manager.php | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
BUILD_DIR="/tmp/${SLUG}-build"
ZIP_NAME="${SLUG}-${VERSION}.zip"

echo "Building ${SLUG} v${VERSION}..."

rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${SLUG}"

# Copy plugin files.
cp gt-link-manager.php "${BUILD_DIR}/${SLUG}/"
cp uninstall.php "${BUILD_DIR}/${SLUG}/"
cp readme.txt "${BUILD_DIR}/${SLUG}/"
cp -r includes "${BUILD_DIR}/${SLUG}/"
cp -r assets "${BUILD_DIR}/${SLUG}/"
cp -r blocks "${BUILD_DIR}/${SLUG}/"
cp -r languages "${BUILD_DIR}/${SLUG}/"

# Remove block source and dev artifacts (only ship build output).
find "${BUILD_DIR}/${SLUG}/blocks" -type d -name "src" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}/${SLUG}/blocks" -type d -name "node_modules" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}/${SLUG}/blocks" -name "package.json" -delete 2>/dev/null || true
find "${BUILD_DIR}/${SLUG}/blocks" -name "package-lock.json" -delete 2>/dev/null || true

# Clean junk files.
find "${BUILD_DIR}" -name ".DS_Store" -delete 2>/dev/null || true
find "${BUILD_DIR}" -name "__MACOSX" -exec rm -rf {} + 2>/dev/null || true
find "${BUILD_DIR}" -name ".gitignore" -delete 2>/dev/null || true

# Build zip.
cd "${BUILD_DIR}"
zip -rq "${ZIP_NAME}" "${SLUG}"
mv "${ZIP_NAME}" "${OLDPWD}/"
cd "${OLDPWD}"

# Cleanup.
rm -rf "${BUILD_DIR}"

echo "Done: ${ZIP_NAME} ($(du -h "${ZIP_NAME}" | cut -f1))"
