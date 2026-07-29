#!/usr/bin/env bash
#
# Run the official WordPress.org Plugin Check against the packaged plugin.
#
# Plugin Check is a WordPress plugin, so it needs a WordPress context — but it
# does not need MySQL. WordPress Playground (PHP-WASM + SQLite) gives us that
# with no Docker and no local WordPress install.
#
# We deliberately check the *packaged* output rather than the source tree, so
# what gets validated is exactly what reviewers receive.
#
# Usage:
#   ./scripts/check-wporg.sh                 # gate on plugin_repo (submission blockers)
#   ./scripts/check-wporg.sh --all           # every check + experimental (advisory)
#   ./scripts/check-wporg.sh path/to/x.zip   # check a specific prebuilt zip
#
# Exit codes: 0 = no errors, 1 = errors found, 2 = harness/setup failure.

set -euo pipefail

PLUGIN_SLUG="botspot"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRATCH="$(mktemp -d)"
trap 'rm -rf "$SCRATCH"' EXIT

MODE="repo"
ZIP=""
for arg in "$@"; do
    case "$arg" in
        --all) MODE="all" ;;
        *.zip) ZIP="$arg" ;;
        *) echo "Unknown argument: $arg" >&2; exit 2 ;;
    esac
done

# Colors (suppressed when not a TTY, e.g. in CI logs).
if [ -t 1 ]; then
    RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; NC=$'\033[0m'
else
    RED=""; GREEN=""; YELLOW=""; NC=""
fi

command -v node >/dev/null 2>&1 || { echo "${RED}ERROR: node is required (>= 20.18).${NC}" >&2; exit 2; }
command -v jq   >/dev/null 2>&1 || { echo "${RED}ERROR: jq is required.${NC}" >&2; exit 2; }

cd "$REPO_ROOT"

# Build the production zip unless one was supplied. The production target is
# what gets submitted, and it is the only build with production URLs baked in.
if [ -z "$ZIP" ]; then
    echo "${YELLOW}==> Building production zip${NC}"
    ./build.sh --production >/dev/null
    VERSION=$(grep "Version:" botspot.php | awk '{print $3}' | head -1)
    ZIP="dist/${PLUGIN_SLUG}-${VERSION}.zip"
fi

[ -f "$ZIP" ] || { echo "${RED}ERROR: zip not found: ${ZIP}${NC}" >&2; exit 2; }
echo "${YELLOW}==> Checking ${ZIP}${NC}"

# Plugin Check accepts a directory or an http(s) URL — never a local zip path.
unzip -q "$ZIP" -d "$SCRATCH"
PLUGIN_DIR="${SCRATCH}/${PLUGIN_SLUG}"
[ -d "$PLUGIN_DIR" ] || { echo "${RED}ERROR: expected ${PLUGIN_SLUG}/ inside the zip.${NC}" >&2; exit 2; }

# --slug is mandatory: the slug is otherwise derived from the directory
# basename, and any mismatch produces a flood of bogus TextDomainMismatch
# errors that mask real findings.
ARGS=(
    plugin check "$PLUGIN_SLUG"
    --slug="$PLUGIN_SLUG"
    --format=strict-json
    --fields=file,line,column,type,code,message
)
if [ "$MODE" = "all" ]; then
    ARGS+=(--include-experimental --include-low-severity-errors --include-low-severity-warnings)
    echo "    mode: all checks + experimental (advisory)"
else
    ARGS+=(--categories=plugin_repo)
    echo "    mode: plugin_repo (submission blockers)"
fi

# wp-cli.phar is auto-provisioned at /tmp/wp-cli.phar by the blueprint's
# wp-cli step. stdout carries PHP deprecation notices from inside the phar, so
# we slice from the first '[' to recover parseable JSON.
set +e
RAW=$(npx --yes @wp-playground/cli@latest php \
    --blueprint=scripts/plugin-check-blueprint.json \
    --mount="${PLUGIN_DIR}:/wordpress/wp-content/plugins/${PLUGIN_SLUG}" \
    --quiet \
    -- /tmp/wp-cli.phar "${ARGS[@]}" 2>/dev/null)
PLAYGROUND_STATUS=$?
set -e

if [ $PLAYGROUND_STATUS -ne 0 ]; then
    echo "${RED}ERROR: Playground failed to run (exit ${PLAYGROUND_STATUS}).${NC}" >&2
    echo "$RAW" >&2
    exit 2
fi

# "No errors found" is Plugin Check's success path and emits no JSON at all.
if printf '%s' "$RAW" | grep -q "Checks complete. No errors found."; then
    echo "${GREEN}PASS: Plugin Check found no errors.${NC}"
    exit 0
fi

JSON=$(printf '%s' "$RAW" | sed -n '/^\[/,$p')
if [ -z "$JSON" ] || ! printf '%s' "$JSON" | jq -e . >/dev/null 2>&1; then
    # Neither a success marker nor parseable findings: assume the worst rather
    # than silently reporting a pass.
    echo "${RED}ERROR: could not parse Plugin Check output.${NC}" >&2
    printf '%s\n' "$RAW" >&2
    exit 2
fi

printf '%s\n' "$JSON" | jq -r '
    group_by(.code)
    | sort_by(-length)
    | map("  \(.[0].type)  \(.[0].code)  x\(length)")
    | .[]'
echo
printf '%s\n' "$JSON" | jq -r '.[] | "  \(.file):\(.line):\(.column)  [\(.type)] \(.code)\n      \(.message)"' | head -60

ERRORS=$(printf '%s' "$JSON" | jq '[.[] | select(.type=="ERROR")] | length')
WARNINGS=$(printf '%s' "$JSON" | jq '[.[] | select(.type=="WARNING")] | length')

echo
# NOTE: `wp plugin check` always exits 0, even with errors. The gate below is
# the whole reason this wrapper exists — never rely on the command's own status.
if [ "$ERRORS" -gt 0 ]; then
    echo "${RED}FAIL: ${ERRORS} error(s), ${WARNINGS} warning(s).${NC}"
    exit 1
fi
echo "${GREEN}PASS: 0 errors, ${WARNINGS} warning(s).${NC}"
exit 0
