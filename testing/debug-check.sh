#!/bin/bash

# Debug check script for BotDot WP plugin
# Validates all PHP files and checks for common issues

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}BotDot WP Debug Check${NC}"
echo "================================"
echo ""

errors=0

# Check syntax of all PHP files
echo -e "${YELLOW}Checking PHP syntax...${NC}"
for file in $(find . -name "*.php" -not -path "./dev/*" -not -path "./build/*" -not -path "./dist/*" -not -path "./.git/*" -not -path "./testing/*"); do
    if php -l "$file" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file"
        php -l "$file"
        ((errors++))
    fi
done

echo ""
echo -e "${YELLOW}Checking class dependencies...${NC}"

# Check if Options class is loaded before Activator uses it
if grep -q "BotDot_WP_Options" includes/class-botdot-wp-activator.php; then
    if grep -q "class-botdot-wp-options.php" botdot-wp.php | grep -B5 "class-botdot-wp-activator.php"; then
        echo -e "${GREEN}✓${NC} Options class loaded before Activator"
    else
        echo -e "${YELLOW}⚠${NC} Check if Options class is loaded before Activator"
    fi
fi

# Check for private methods being called externally
echo ""
echo -e "${YELLOW}Checking for potential visibility issues...${NC}"
if grep -r "private static function" includes/ | grep -v "cast_option_value\|add("; then
    echo -e "${YELLOW}⚠${NC} Found private static methods (check if called externally)"
fi

# Check file structure
echo ""
echo -e "${YELLOW}Checking file structure...${NC}"
required_files=(
    "botdot-wp.php"
    "includes/class-botdot-wp.php"
    "includes/class-botdot-wp-loader.php"
    "includes/class-botdot-wp-options.php"
    "includes/class-botdot-wp-fetcher.php"
    "includes/class-botdot-wp-injector.php"
    "includes/class-botdot-wp-logger.php"
    "includes/class-botdot-wp-activator.php"
    "includes/class-botdot-wp-deactivator.php"
    "admin/class-botdot-wp-admin.php"
    "uninstall.php"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (missing)"
        ((errors++))
    fi
done

# Summary
echo ""
echo "================================"
if [ $errors -eq 0 ]; then
    echo -e "${GREEN}All checks passed!${NC}"
    echo "Plugin is ready for activation."
else
    echo -e "${RED}Found $errors error(s)${NC}"
    echo "Please fix the errors before activating."
    exit 1
fi
