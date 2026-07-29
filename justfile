PLUGIN_SLUG := "botspot"
BUCKET := "botspot-plugins"
GCS_PREFIX := "gs://" + BUCKET + "/" + PLUGIN_SLUG

# Read version from the plugin header (single source of truth).
VERSION := `grep "Version:" botspot.php | awk '{print $3}' | head -1`

default:
    @just --list

# Build the production zip (rewrites URLs to bot.spot custom domains).
[group('build')]
build-prod:
    ./build.sh --production

# Build the staging zip.
[group('build')]
build-staging:
    ./build.sh --staging

# Build both prod and staging zips.
[group('build')]
build: build-prod build-staging

# Upload built zips to the versioned bucket path. Does not touch latest/.
[group('release')]
upload-versioned:
    gcloud storage cp \
        "dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip" \
        "dist/{{PLUGIN_SLUG}}-{{VERSION}}-staging.zip" \
        "{{GCS_PREFIX}}/v{{VERSION}}/"

# Promote the current version to latest/ (mutable pointer for new installs).
[group('release')]
promote-latest:
    gcloud storage cp "dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip"         "{{GCS_PREFIX}}/latest/{{PLUGIN_SLUG}}.zip"
    gcloud storage cp "dist/{{PLUGIN_SLUG}}-{{VERSION}}-staging.zip" "{{GCS_PREFIX}}/latest/{{PLUGIN_SLUG}}-staging.zip"

# Tag the current commit and push the tag (skipped if tag already exists).
[group('release')]
tag:
    #!/usr/bin/env bash
    set -euo pipefail
    if git rev-parse "v{{VERSION}}" >/dev/null 2>&1; then
        echo "Tag v{{VERSION}} already exists — skipping."
        exit 0
    fi
    git tag -a "v{{VERSION}}" -m "{{PLUGIN_SLUG}} {{VERSION}}"
    git push origin "v{{VERSION}}"

# Full release: build both targets, upload to versioned path, promote latest, tag.
# Run after bumping the version in botspot.php and updating the changelog.
[group('release')]
release: build upload-versioned promote-latest tag
    @echo "Released v{{VERSION}} to {{GCS_PREFIX}}/v{{VERSION}}/ and latest/."

# Show what's currently in the bucket.
[group('info')]
ls-bucket:
    gcloud storage ls -l "{{GCS_PREFIX}}/"
    @echo
    @echo "--- latest/ ---"
    gcloud storage ls -l "{{GCS_PREFIX}}/latest/"

# Print the version this justfile sees (sanity check before release).
[group('info')]
version:
    @echo {{VERSION}}

# PHPCS: security, escaping, i18n, DB, PHP compat.
#
# Scoped via phpcs.xml.dist to what Plugin Check blocks on, not full WP
# formatting.

# Run PHPCS over the source tree.
[group('qa')]
check:
    @test -x vendor/bin/phpcs || composer install
    vendor/bin/phpcs

# Auto-fix the mechanically-fixable PHPCS findings, then re-report.
[group('qa')]
check-fix:
    @test -x vendor/bin/phpcbf || composer install
    -vendor/bin/phpcbf
    vendor/bin/phpcs

# Uses WordPress Playground (PHP-WASM + SQLite), so no Docker and no local
# WordPress install is needed. Checks the packaged production zip, not the
# source tree, so what gets validated is exactly what reviewers receive.

# Run official WordPress.org Plugin Check on the packaged zip.
[group('qa')]
check-wporg:
    ./scripts/check-wporg.sh

# Plugin Check with every check + experimental ones (advisory, wider than the gate).
[group('qa')]
check-wporg-all:
    ./scripts/check-wporg.sh --all

# PHP syntax check across all tracked PHP files (excludes vendor).
[group('qa')]
check-syntax:
    #!/usr/bin/env bash
    set -euo pipefail
    fail=0
    for f in $(git ls-files '*.php' | grep -v '^vendor/'); do
        php -l "$f" >/dev/null || fail=1
    done
    [ "$fail" -eq 0 ] && echo "php -l: all files OK"
    exit "$fail"

# Regenerate the translation template and surface i18n problems (wrong text
# domain, variables passed to __(), inconsistent translator comments).

# Check i18n usage via wp i18n make-pot.
[group('qa')]
check-i18n:
    ./scripts/check-i18n.sh

# Verify the output-escaping pipeline against real WordPress (not test stubs),
# so wp_kses/wp_kses_allowed_html/wp_json_encode are core's implementations.
# Proves the appendix payload (inline CSS, SVG, style attrs) survives escaping
# and that bspt_appendix_html filter output is escaped.

# Run the escaping pipeline checks inside WordPress Playground.
[group('qa')]
check-escaping:
    npx --yes @wp-playground/cli@latest php --quiet \
        --mount=.:/wordpress/wp-content/plugins/botspot \
        -- /wordpress/wp-content/plugins/botspot/scripts/verify-escaping.php

# Activates the built zip in a real WordPress with WP_DEBUG on and drives the
# actual hooks, so wiring mistakes (wrong hook, renamed callback, fatal on load)
# surface. Fails on any plugin PHP notice.

# Smoke-test the packaged plugin at runtime in WordPress Playground.
[group('qa')]
check-runtime:
    ./scripts/verify-runtime.sh

# A mismatch between the plugin header, version constant, and readme stable tag
# is a guaranteed WordPress.org review rejection.

# Confirm version metadata agrees across all files.
[group('qa')]
check-version:
    #!/usr/bin/env bash
    set -euo pipefail
    header=$(grep -m1 ' \* Version:' botspot.php | awk '{print $3}')
    docblock=$(grep -m1 '@version' botspot.php | awk '{print $3}')
    constant=$(grep -m1 "define('BSPT_VERSION'" botspot.php | sed "s/.*'\([0-9.]*\)'.*/\1/")
    stable=$(grep -m1 '^Stable tag:' readme.txt | awk '{print $3}')
    echo "  header:   $header"
    echo "  @version: $docblock"
    echo "  constant: $constant"
    echo "  readme:   $stable"
    if [ "$header" = "$docblock" ] && [ "$header" = "$constant" ] && [ "$header" = "$stable" ]; then
        echo "version metadata is consistent ($header)"
    else
        echo "ERROR: version metadata disagrees" >&2
        exit 1
    fi

# Fast local gate: no network, no browser. Run before pushing.
[group('qa')]
verify: check-version check-syntax check
    @echo "Local verification passed."

# Full pre-submission gate incl. official Plugin Check. Run before submitting.
[group('qa')]
verify-submission: verify check-i18n check-escaping check-wporg check-runtime
    @echo "Submission verification passed."
