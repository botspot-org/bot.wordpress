#!/bin/bash

# BotSpot Plugin Build Script
# Creates a distributable WordPress plugin zip file

set -e

# Configuration
PLUGIN_SLUG="botspot"
MAIN_PLUGIN_FILE="botspot.php"
VERSION=$(grep "Version:" "${MAIN_PLUGIN_FILE}" | awk '{print $3}' | head -1)
BUILD_DIR="build"
DIST_DIR="dist"

# Build target — defaults to production for release safety. Use staging only
# when explicitly requested:
#   TARGET=staging ./build.sh
#   or the alias flag: ./build.sh --staging
TARGET="${TARGET:-production}"
if [ "$1" = "--production" ] || [ "$1" = "--prod" ]; then
    TARGET="production"
elif [ "$1" = "--staging" ] || [ "$1" = "--stage" ]; then
    TARGET="staging"
fi

# Per-target URLs — env-var overridable for anyone running a private locus.
# Defaults are the real Cloud Run custom domains verified via gcloud.
case "$TARGET" in
    production)
        LOCUS_API_URL="${LOCUS_API_URL:-https://locus-api.bot.spot}"
        CONNECTOR_URL="${CONNECTOR_URL:-https://locus-connectors.bot.spot}"
        ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
        ;;
    staging)
        LOCUS_API_URL="${LOCUS_API_URL:-https://locus-staging-api.bot.spot}"
        CONNECTOR_URL="${CONNECTOR_URL:-https://staging-locus-connectors.bot.spot}"
        ZIP_NAME="${PLUGIN_SLUG}-${VERSION}-staging.zip"
        ;;
    *)
        echo "ERROR: TARGET must be 'staging' or 'production', got '$TARGET'"
        exit 1
        ;;
esac

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Building BotSpot Plugin v${VERSION} [${TARGET}]${NC}"
echo "  API:        ${LOCUS_API_URL}"
echo "  Connectors: ${CONNECTOR_URL}"
echo "========================================"

# Install Composer dependencies — three-step install so Strauss (a dev dep)
# is available during prefixing, then dev deps are pruned from the vendor
# that actually ships in the zip.
echo -e "${YELLOW}==> Installing Composer dependencies with dev (for Strauss)${NC}"
composer install --optimize-autoloader

echo -e "${YELLOW}==> Running Strauss to prefix third-party packages${NC}"
composer run strauss

if [ ! -d "vendor/botspot-prefixed" ]; then
  echo -e "${RED}ERROR: vendor/botspot-prefixed not created. Strauss run failed.${NC}"
  exit 1
fi

echo -e "${YELLOW}==> Pruning dev dependencies (production-only install)${NC}"
# --no-scripts skips the post-install-cmd @strauss hook from composer.json;
# Strauss was already run in the previous step and we're now pruning dev deps
# (including strauss itself), so re-running it here would fail with 127.
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-scripts

# Clean up old build directory (keep dist for archive history)
echo -e "${YELLOW}Cleaning up old build files...${NC}"
rm -rf "${BUILD_DIR}"

# Create build directories
echo -e "${YELLOW}Creating build directories...${NC}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DIST_DIR}"

# Check if this version already exists
if [ -f "${DIST_DIR}/${ZIP_NAME}" ]; then
    echo -e "${YELLOW}Warning: ${ZIP_NAME} already exists, will be overwritten${NC}"
fi

# Copy plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"

# Copy main plugin file
cp "${MAIN_PLUGIN_FILE}" "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Rewrite build-time URLs in the copy (never touch the source tree).
# Source tree always holds the staging URLs; production builds sed-rewrite
# them to the production custom domains. Env-var overrides (LOCUS_API_URL,
# CONNECTOR_URL) allow arbitrary targets without editing the script.
echo -e "${YELLOW}==> Rewriting build-time URLs for target '${TARGET}'${NC}"
sed -i.bak \
    -e "s|https://locus-staging-api.bot.spot|${LOCUS_API_URL}|g" \
    -e "s|https://staging-locus-connectors.bot.spot|${CONNECTOR_URL}|g" \
    "${BUILD_DIR}/${PLUGIN_SLUG}/${MAIN_PLUGIN_FILE}"
rm -f "${BUILD_DIR}/${PLUGIN_SLUG}/${MAIN_PLUGIN_FILE}.bak"

# Sanity check: confirm the rewritten file still has both defines and they
# point at the expected URLs.
if ! grep -q "define('BOTSPOT_WP_LOCUS_API_URL', '${LOCUS_API_URL}')" "${BUILD_DIR}/${PLUGIN_SLUG}/${MAIN_PLUGIN_FILE}"; then
    echo -e "${RED}ERROR: BOTSPOT_WP_LOCUS_API_URL rewrite failed${NC}"
    exit 1
fi
if ! grep -q "define('BOTSPOT_WP_CONNECTOR_URL', '${CONNECTOR_URL}')" "${BUILD_DIR}/${PLUGIN_SLUG}/${MAIN_PLUGIN_FILE}"; then
    echo -e "${RED}ERROR: BOTSPOT_WP_CONNECTOR_URL rewrite failed${NC}"
    exit 1
fi

# Copy public package metadata and documentation
cp readme.txt "${BUILD_DIR}/${PLUGIN_SLUG}/"
cp LICENSE.txt "${BUILD_DIR}/${PLUGIN_SLUG}/"
cp THIRD-PARTY-LICENSES.txt "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy uninstall script
cp uninstall.php "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy includes directory
cp -r includes "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy admin directory
cp -r admin "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy public directory
cp -r public "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy Strauss-prefixed vendor (runtime dependency: crawler-detect + crawler-user-agents)
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}/vendor"
cp -r vendor/botspot-prefixed "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/botspot-prefixed"

# Preserve upstream license files alongside the prefixed runtime packages.
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/botspot-prefixed/jaybizzle/crawler-detect"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/botspot-prefixed/monperrus/crawler-user-agents"
cp vendor/jaybizzle/crawler-detect/LICENSE "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/botspot-prefixed/jaybizzle/crawler-detect/LICENSE"
cp vendor/monperrus/crawler-user-agents/LICENSE "${BUILD_DIR}/${PLUGIN_SLUG}/vendor/botspot-prefixed/monperrus/crawler-user-agents/LICENSE"

# Create languages directory (even if empty, for i18n readiness)
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}/languages"

# Remove any development files that might have been copied
echo -e "${YELLOW}Removing development files...${NC}"
find "${BUILD_DIR}" -name ".DS_Store" -delete
find "${BUILD_DIR}" -name "Thumbs.db" -delete
find "${BUILD_DIR}" -name ".git*" -delete
find "${BUILD_DIR}" -name "*.bak" -delete
find "${BUILD_DIR}" -name "*.tmp" -delete
find "${BUILD_DIR}" -name "*~" -delete
find "${BUILD_DIR}" -name "test-server.py" -delete
find "${BUILD_DIR}" -name "run-test-server.sh" -delete
find "${BUILD_DIR}" -name "build.sh" -delete
find "${BUILD_DIR}" -name "requirements.txt" -delete
find "${BUILD_DIR}" -name "TODO.md" -delete
find "${BUILD_DIR}" -name "__pycache__" -type d -exec rm -rf {} + 2>/dev/null || true

# Create the zip file
echo -e "${YELLOW}Creating zip archive...${NC}"
cd "${BUILD_DIR}"
zip -r "../${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -q
cd ..

# Calculate zip file size
ZIP_SIZE=$(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)

# Success message
echo ""
echo -e "${GREEN}Build complete!${NC}"
echo "========================================"
echo -e "Plugin: ${GREEN}${PLUGIN_SLUG}${NC}"
echo -e "Version: ${GREEN}${VERSION}${NC}"
echo -e "Archive: ${GREEN}${DIST_DIR}/${ZIP_NAME}${NC}"
echo -e "Size: ${GREEN}${ZIP_SIZE}${NC}"
echo ""
echo "You can now upload this zip file to WordPress:"
echo "Plugins > Add New > Upload Plugin"
echo ""

# Optional: List contents
if [ "$1" == "--list" ] || [ "$1" == "-l" ]; then
    echo -e "${YELLOW}Archive contents:${NC}"
    unzip -l "${DIST_DIR}/${ZIP_NAME}"
fi

# Cleanup build directory (keep dist)
echo -e "${YELLOW}Cleaning up temporary files...${NC}"
rm -rf "${BUILD_DIR}"

echo -e "${GREEN}Done!${NC}"
