PLUGIN_SLUG := "botspot"
BUCKET := "botspot-plugins"
GCS_PREFIX := "gs://" + BUCKET + "/" + PLUGIN_SLUG

# WordPress.org SVN. The working copy is a sibling of this repo, never inside
# it — SVN metadata in a git tree invites accidents in both directions.
SVN_URL := "https://plugins.svn.wordpress.org/" + PLUGIN_SLUG
SVN_DIR := parent_directory(justfile_directory()) / PLUGIN_SLUG + "-svn"
SVN_USER := env("SVN_USERNAME", "haavardmk")

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

# NOTE: releases are tagged by CI on success, not from here — the tag is a
# receipt that a release completed. Use this only to backfill a tag for a
# version that shipped before the release workflow existed.

# Tag the current commit and push it (prefer letting CI tag on release).
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

# The normal path is a PR into `release`, which publishes to WordPress.org and
# GCS and then tags. This recipe deliberately does NOT tag or touch
# WordPress.org; use `just svn-publish` for that.

# Manual GCS-only release, for when CI is unavailable.
[group('release')]
release: build upload-versioned promote-latest
    @echo "Released v{{VERSION}} to {{GCS_PREFIX}}/v{{VERSION}}/ and latest/ (GCS only, untagged)."

# ---------------------------------------------------------------------------
# WordPress.org (SVN)
#
# SVN here is a *release* system, not a development VCS: every commit makes
# wp.org rebuild the served zip for every version, so a release is staged
# locally and pushed as ONE commit.
#
# Auth: export SVN_PW with the password from
# https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
# (separate from your wordpress.org login). If SVN_PW is unset, svn prompts and
# uses its own keychain cache. The password is never passed as an argv value,
# so it cannot leak into the process list.
# ---------------------------------------------------------------------------

# One-time: check out the wp.org SVN working copy next to this repo.
[group('wporg')]
svn-checkout:
    #!/usr/bin/env bash
    set -euo pipefail
    if [ -d "{{SVN_DIR}}/.svn" ]; then
        echo "Working copy already exists at {{SVN_DIR}}"
        exit 0
    fi
    # Sparse: tags/ at depth empty, so we do not download every historical
    # release. `svn cp trunk tags/X` still records a proper copy-with-history
    # from this state (verified), and the checkout stays ~3MB forever.
    svn co --depth empty "{{SVN_URL}}" "{{SVN_DIR}}"
    cd "{{SVN_DIR}}"
    svn up --set-depth infinity trunk assets
    svn up --set-depth empty tags
    svn info

# Stage the release into the SVN working copy. Commits nothing.
#
# Run this first, review the output, then run svn-publish. It is idempotent —
# re-running just re-syncs.

# Sync the production zip into trunk/ and assets/, staging but not pushing.
[group('wporg')]
svn-stage: build-prod
    #!/usr/bin/env bash
    set -euo pipefail
    ZIP="dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip"
    SVN="{{SVN_DIR}}"

    [ -d "$SVN/.svn" ] || { echo "No working copy at $SVN — run: just svn-checkout" >&2; exit 1; }
    [ -f "$ZIP" ]      || { echo "Missing $ZIP" >&2; exit 1; }

    # A staging build points customers at staging infrastructure, and tags are
    # immutable, so this check has no cheap recovery if it is skipped.
    unzip -p "$ZIP" "{{PLUGIN_SLUG}}/botspot.php" | grep -q 'locus-api.bot.spot' \
        || { echo "ERROR: $ZIP lacks production URLs — staging build?" >&2; exit 1; }

    echo "==> svn up (the working copy is a mirror; a stale one cannot commit)"
    svn up "$SVN"

    SCRATCH=$(mktemp -d)
    trap 'rm -rf "$SCRATCH"' EXIT
    unzip -q "$ZIP" -d "$SCRATCH"

    # The trailing slash on the source copies the *contents* of botspot/.
    # Without it you get trunk/botspot/botspot.php, which breaks every download.
    echo "==> rsync into trunk/ and assets/"
    rsync -a --delete --exclude='.svn' "$SCRATCH/{{PLUGIN_SLUG}}/" "$SVN/trunk/"
    rsync -a --delete --exclude='.svn' assets/ "$SVN/assets/"

    cd "$SVN"

    # --force is required: trunk/ and assets/ are already versioned, so a plain
    # `svn add trunk` errors out instead of descending into it.
    echo "==> svn add"
    svn add --force --parents --depth infinity -q trunk assets

    # Deleting a file from disk does NOT delete it in SVN; it shows as '!' and
    # would otherwise linger in the repo forever.
    MISSING=$(svn status trunk assets | awk '/^!/ {print $2}')
    if [ -n "$MISSING" ]; then
        echo "==> svn rm (files removed since the last release)"
        printf '%s\n' "$MISSING" | while read -r f; do svn rm --force -q "$f"; done
    fi

    echo "==> assertions"
    [ -f trunk/botspot.php ] || { echo "FAIL: trunk/botspot.php missing" >&2; exit 1; }
    [ -f trunk/readme.txt ]  || { echo "FAIL: trunk/readme.txt missing" >&2; exit 1; }
    [ ! -d trunk/{{PLUGIN_SLUG}} ] || { echo "FAIL: nested trunk/{{PLUGIN_SLUG}}/" >&2; exit 1; }
    grep -q '^Stable tag: {{VERSION}}$' trunk/readme.txt \
        || { echo "FAIL: trunk/readme.txt Stable tag != {{VERSION}}" >&2; exit 1; }
    grep -q ' \* Version: *{{VERSION}}$' trunk/botspot.php \
        || { echo "FAIL: trunk/botspot.php header != {{VERSION}}" >&2; exit 1; }
    if svn status | grep -iE 'dist/|tests/|node_modules|phpunit|\.git|\.DS_Store|strauss|squizlabs'; then
        echo "FAIL: junk staged (see above)" >&2; exit 1
    fi
    VENDOR=$(find trunk/vendor -maxdepth 1 -mindepth 1 -type d -exec basename {} \;)
    [ "$VENDOR" = "botspot-prefixed" ] \
        || { echo "FAIL: trunk/vendor should hold only botspot-prefixed, got: $VENDOR" >&2; exit 1; }
    echo "    all assertions passed"

    echo
    echo "==> staged changes ({{VERSION}})"
    svn status | awk '{print $1}' | sort | uniq -c
    echo
    svn status
    echo
    echo "Nothing has been pushed. To publish: just svn-publish"

# Refuse to publish a version that is already tagged on wp.org.
#
# Tags are immutable in practice: wp.org caches and rebuilds zips from them, and
# users who already downloaded never see a correction. The fix for a bad release
# is always a new version, never a re-tag.

# Abort if tags/VERSION already exists remotely.
[group('wporg')]
svn-guard:
    #!/usr/bin/env bash
    set -euo pipefail
    if svn ls "{{SVN_URL}}/tags/" | grep -qx '{{VERSION}}/'; then
        echo "ERROR: tags/{{VERSION}} already exists on WordPress.org." >&2
        echo "Tags are immutable — bump the version instead of re-tagging." >&2
        exit 1
    fi
    echo "tags/{{VERSION}} is free"

# Publish the staged release: tag it and push trunk + tag in one commit.
[group('wporg')]
svn-publish: svn-guard svn-stage
    #!/usr/bin/env bash
    set -euo pipefail
    cd "{{SVN_DIR}}"

    # A lazy, server-side copy: the tag is recorded as a reference to trunk, so
    # it transmits almost nothing despite being a full snapshot.
    echo "==> svn cp trunk tags/{{VERSION}}"
    svn cp trunk "tags/{{VERSION}}"

    echo "==> svn ci (one commit: wp.org rebuilds all zips on every commit)"
    if [ -n "${SVN_PW:-}" ]; then
        printf '%s' "$SVN_PW" | svn ci -m "Release {{VERSION}}" \
            --username "{{SVN_USER}}" --password-from-stdin --non-interactive
    else
        svn ci -m "Release {{VERSION}}" --username "{{SVN_USER}}"
    fi

    echo
    just svn-verify

# Show the SVN working copy status without changing anything.
[group('wporg')]
svn-status:
    @cd "{{SVN_DIR}}" && svn status && echo "--- info ---" && svn info

# Confirm what wp.org actually has: remote tags, log, and API metadata.
[group('wporg')]
svn-verify:
    #!/usr/bin/env bash
    set -euo pipefail
    echo "==> remote tags"
    svn ls "{{SVN_URL}}/tags/"
    echo "==> last 3 revisions"
    svn log -q -l 3 "{{SVN_URL}}"
    echo "==> api.wordpress.org reported version"
    curl -s "https://api.wordpress.org/plugins/info/1.0/{{PLUGIN_SLUG}}.json" \
        | grep -o '"version":"[^"]*"' | head -1
    echo "==> public page"
    curl -sI "https://wordpress.org/plugins/{{PLUGIN_SLUG}}/" | head -1

# Runtime-check the zip wp.org actually serves (not our local build).
#
# wp.org rebuilds the zip from SVN, so this is a genuinely different artifact
# from dist/. Expect up to ~6h after publishing before the URL reflects a new
# version.

# Download the wp.org-served zip and run it through the runtime checks.
[group('wporg')]
verify-published:
    #!/usr/bin/env bash
    set -euo pipefail
    URL="https://downloads.wordpress.org/plugin/{{PLUGIN_SLUG}}.{{VERSION}}.zip"
    OUT="/tmp/{{PLUGIN_SLUG}}-published-{{VERSION}}.zip"
    echo "==> downloading $URL"
    # -f so a 404 fails loudly instead of writing an HTML error page into the zip.
    curl -fL --progress-bar -o "$OUT" "$URL"
    unzip -p "$OUT" "{{PLUGIN_SLUG}}/botspot.php" | grep -m1 ' \* Version:'
    ./scripts/verify-runtime.sh "$OUT"


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

# If the build is not reproducible, `svn status` cannot be trusted: rebuilding
# shows phantom modifications and you lose the ability to tell a real change
# from a timestamp. Strauss stamps a date into prefixed vendor files unless
# extra.strauss.include_modified_date is false in composer.json.

# Confirm two consecutive production builds are content-identical.
[group('qa')]
check-reproducible:
    #!/usr/bin/env bash
    set -euo pipefail
    ZIP="dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip"
    ./build.sh --production >/dev/null
    cp "$ZIP" /tmp/repro-a.zip
    ./build.sh --production >/dev/null
    cp "$ZIP" /tmp/repro-b.zip
    rm -rf /tmp/repro-a /tmp/repro-b
    mkdir -p /tmp/repro-a /tmp/repro-b
    unzip -q /tmp/repro-a.zip -d /tmp/repro-a
    unzip -q /tmp/repro-b.zip -d /tmp/repro-b
    if diff -r /tmp/repro-a /tmp/repro-b; then
        echo "reproducible: two consecutive builds are content-identical"
    else
        echo "FAIL: build is not reproducible (check extra.strauss.include_modified_date)" >&2
        exit 1
    fi

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

# Fast local gate: no network, no browser. Run before pushing.[group('qa')]
verify: check-version check-syntax check
    @echo "Local verification passed."

# Full pre-submission gate incl. official Plugin Check. Run before submitting.
[group('qa')]
verify-submission: verify check-i18n check-escaping check-wporg check-runtime
    @echo "Submission verification passed."