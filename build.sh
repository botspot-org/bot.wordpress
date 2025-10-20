#!/bin/bash

# BotDot WP Plugin Build Script
# Creates a distributable WordPress plugin zip file

set -e

# Configuration
PLUGIN_SLUG="botdot-wp"
VERSION=$(grep "Version:" botdot-wp.php | awk '{print $3}' | head -1)
BUILD_DIR="build"
DIST_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Building BotDot WP Plugin v${VERSION}${NC}"
echo "========================================"

# Clean up old build directories
echo -e "${YELLOW}Cleaning up old build files...${NC}"
rm -rf "${BUILD_DIR}"
rm -rf "${DIST_DIR}"

# Create build directories
echo -e "${YELLOW}Creating build directories...${NC}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"
mkdir -p "${DIST_DIR}"

# Copy plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"

# Copy main plugin file
cp botdot-wp.php "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy README and documentation
cp README.md "${BUILD_DIR}/${PLUGIN_SLUG}/"
cp THEME-INTEGRATION.md "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy debug and utility scripts
cp ./testing/debug-check.sh "${BUILD_DIR}/${PLUGIN_SLUG}/"
chmod +x "${BUILD_DIR}/${PLUGIN_SLUG}/debug-check.sh"

# Copy uninstall script
cp uninstall.php "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy includes directory
cp -r includes "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy admin directory
cp -r admin "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Copy public directory
cp -r public "${BUILD_DIR}/${PLUGIN_SLUG}/"

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
