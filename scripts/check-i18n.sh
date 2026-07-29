#!/usr/bin/env bash
#
# Regenerate the translation template and surface i18n problems that the
# WordPress.org review team cares about: wrong or missing text domains,
# variables passed to translation functions, and inconsistent translator
# comments for the same string.
#
# Runs `wp i18n make-pot` inside WordPress Playground so no local WordPress or
# Docker is needed. The generated POT is written to a scratch dir and discarded
# unless --write is passed, because we only care about the warnings.
#
# Usage:
#   ./scripts/check-i18n.sh            # report only
#   ./scripts/check-i18n.sh --write    # also write languages/botspot.pot
#
# Exit codes: 0 = no warnings, 1 = warnings found, 2 = harness failure.

set -euo pipefail

PLUGIN_SLUG="botspot"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRATCH="$(mktemp -d)"
trap 'rm -rf "$SCRATCH"' EXIT

WRITE=0
[ "${1:-}" = "--write" ] && WRITE=1

if [ -t 1 ]; then
    RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; NC=""
fi

command -v node >/dev/null 2>&1 || { echo "${RED}ERROR: node is required (>= 20.18).${NC}" >&2; exit 2; }

cd "$REPO_ROOT"
echo "${YELLOW}==> Generating POT and checking i18n usage${NC}"

# Mount the source tree read-write is unnecessary here, but Playground mounts
# are always read-write; we write the POT to /tmp inside the guest instead.
set +e
RAW=$(npx --yes @wp-playground/cli@latest php \
    --blueprint=scripts/plugin-check-blueprint.json \
    --mount="${REPO_ROOT}:/wordpress/wp-content/plugins/${PLUGIN_SLUG}" \
    --quiet \
    -- /tmp/wp-cli.phar i18n make-pot \
        "/wordpress/wp-content/plugins/${PLUGIN_SLUG}" \
        "/tmp/${PLUGIN_SLUG}.pot" \
        --slug="${PLUGIN_SLUG}" \
        --domain="${PLUGIN_SLUG}" \
        --exclude=vendor,tests,testing,dist,node_modules,scripts 2>&1)
STATUS=$?
set -e

if [ $STATUS -ne 0 ]; then
    echo "${RED}ERROR: make-pot failed to run (exit ${STATUS}).${NC}" >&2
    printf '%s\n' "$RAW" >&2
    exit 2
fi

# Strip ANSI codes and the PHP deprecation noise emitted from inside wp-cli.phar.
CLEAN=$(printf '%s' "$RAW" | sed 's/\x1b\[[0-9;]*m//g' | grep -viE '^(php )?deprecated:' || true)

if ! printf '%s' "$CLEAN" | grep -q "POT file successfully generated"; then
    echo "${RED}ERROR: make-pot did not report success.${NC}" >&2
    printf '%s\n' "$CLEAN" >&2
    exit 2
fi

if [ "$WRITE" -eq 1 ]; then
    echo "${YELLOW}==> --write requested; re-run make-pot writing into languages/${NC}"
    npx --yes @wp-playground/cli@latest php \
        --blueprint=scripts/plugin-check-blueprint.json \
        --mount="${REPO_ROOT}:/wordpress/wp-content/plugins/${PLUGIN_SLUG}" \
        --quiet \
        -- /tmp/wp-cli.phar i18n make-pot \
            "/wordpress/wp-content/plugins/${PLUGIN_SLUG}" \
            "/wordpress/wp-content/plugins/${PLUGIN_SLUG}/languages/${PLUGIN_SLUG}.pot" \
            --slug="${PLUGIN_SLUG}" \
            --domain="${PLUGIN_SLUG}" \
            --exclude=vendor,tests,testing,dist,node_modules,scripts >/dev/null 2>&1
    echo "    wrote languages/${PLUGIN_SLUG}.pot"
fi

WARNINGS=$(printf '%s' "$CLEAN" | grep -E '^(Warning|Error):' || true)
if [ -n "$WARNINGS" ]; then
    printf '%s\n' "$WARNINGS"
    COUNT=$(printf '%s\n' "$WARNINGS" | grep -c . )
    echo
    echo "${RED}FAIL: ${COUNT} i18n warning(s).${NC}"
    exit 1
fi

echo "${GREEN}PASS: no i18n warnings.${NC}"
exit 0
