#!/usr/bin/env bash
#
# Runtime smoke test for the packaged plugin, inside WordPress Playground.
#
# Static analysis cannot prove the plugin still works. This activates the built
# zip in a real WordPress with WP_DEBUG on and drives the actual hooks, so
# wiring mistakes (wrong hook, renamed callback, fatal on load) surface here.
#
# Also fails if activating and exercising the plugin writes any PHP
# notice/warning/deprecation to debug.log.
#
# Usage:
#   ./scripts/verify-runtime.sh [path/to/plugin.zip]
set -euo pipefail

cd "$(dirname "$0")/.."
REPO_ROOT="$PWD"

ZIP="${1:-}"
if [ -z "$ZIP" ]; then
    VERSION=$(grep -m1 ' \* Version:' botspot.php | awk '{print $3}')
    ZIP="dist/botspot-${VERSION}.zip"
    if [ ! -f "$ZIP" ]; then
        echo "==> Building $ZIP"
        ./build.sh --production >/dev/null
    fi
fi

if [ ! -f "$ZIP" ]; then
    echo "ERROR: zip not found: $ZIP" >&2
    exit 1
fi

SCRATCH=$(mktemp -d)
trap 'rm -rf "$SCRATCH"' EXIT

echo "==> Unpacking $ZIP"
unzip -q "$ZIP" -d "$SCRATCH"
if [ ! -d "$SCRATCH/botspot" ]; then
    echo "ERROR: zip does not contain a botspot/ directory" >&2
    exit 1
fi

# The probe ships in scripts/ and is stripped from the zip, so copy it in.
cp "$REPO_ROOT/scripts/verify-runtime.php" "$SCRATCH/botspot/"

echo "==> Running runtime checks in WordPress Playground"
set +e
OUTPUT=$(npx --yes @wp-playground/cli@latest php \
    --quiet \
    --blueprint="$REPO_ROOT/scripts/runtime-blueprint.json" \
    --mount="$SCRATCH/botspot:/wordpress/wp-content/plugins/botspot" \
    -- /wordpress/wp-content/plugins/botspot/verify-runtime.php 2>&1)
STATUS=$?
set -e

echo "$OUTPUT"

if [ "$STATUS" -ne 0 ] || ! grep -q "^PASS:" <<<"$OUTPUT"; then
    echo
    echo "FAIL: runtime checks did not pass."
    exit 1
fi

# WP_DEBUG_LOG writes into the mounted plugin dir's parent inside Playground,
# which is not visible here, so re-run asking the probe to surface any logged
# notices. Playground echoes PHP notices to stdout, so scan the captured output.
if grep -qiE "PHP (Warning|Notice|Deprecated|Fatal)" <<<"$OUTPUT"; then
    # wp-cli.phar inside Playground emits its own deprecations; only fail on
    # ones pointing at our plugin.
    if grep -iE "PHP (Warning|Notice|Deprecated|Fatal)" <<<"$OUTPUT" | grep -q "plugins/botspot"; then
        echo
        echo "FAIL: plugin produced PHP notices/warnings under WP_DEBUG."
        grep -iE "PHP (Warning|Notice|Deprecated|Fatal)" <<<"$OUTPUT" | grep "plugins/botspot"
        exit 1
    fi
fi

echo
echo "PASS: runtime checks clean, no plugin PHP notices under WP_DEBUG."
