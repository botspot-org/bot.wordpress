PLUGIN_SLUG := "botspot"
BUCKET := "botspot-plugins"
GCS_PREFIX := "gs://" + BUCKET + "/" + PLUGIN_SLUG

# Read version from the plugin header (single source of truth).
VERSION := `grep "Version:" botspot.php | awk '{print $3}' | head -1`

default:
    @just --list

# Build the production zip (rewrites URLs to bot.spot custom domains).
build-prod:
    ./build.sh --production

# Build the staging zip (default URLs).
build-staging:
    ./build.sh

# Build both prod and staging zips.
build: build-prod build-staging

# Upload built zips to the versioned bucket path. Does not touch latest/.
upload-versioned:
    gcloud storage cp \
        "dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip" \
        "dist/{{PLUGIN_SLUG}}-{{VERSION}}-staging.zip" \
        "{{GCS_PREFIX}}/v{{VERSION}}/"

# Promote the current version to latest/ (mutable pointer for new installs).
promote-latest:
    gcloud storage cp "dist/{{PLUGIN_SLUG}}-{{VERSION}}.zip"         "{{GCS_PREFIX}}/latest/{{PLUGIN_SLUG}}.zip"
    gcloud storage cp "dist/{{PLUGIN_SLUG}}-{{VERSION}}-staging.zip" "{{GCS_PREFIX}}/latest/{{PLUGIN_SLUG}}-staging.zip"

# Tag the current commit and push the tag (skipped if tag already exists).
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
release: build upload-versioned promote-latest tag
    @echo "Released v{{VERSION}} to {{GCS_PREFIX}}/v{{VERSION}}/ and latest/."

# Show what's currently in the bucket.
ls-bucket:
    gcloud storage ls -l "{{GCS_PREFIX}}/"
    @echo
    @echo "--- latest/ ---"
    gcloud storage ls -l "{{GCS_PREFIX}}/latest/"

# Print the version this justfile sees (sanity check before release).
version:
    @echo {{VERSION}}
