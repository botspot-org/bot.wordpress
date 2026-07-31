<!--
Merging this PR into `release` publishes the plugin to WordPress.org and GCS.
For a PR into `main`, delete everything below the first section.
-->

## What changed

<!-- One or two sentences. Why, not just what. -->

---

<!-- ===== Delete the rest unless this is a PR into `release` ===== -->

## Release checklist

Version bumped on `main` in a separate PR, so `just check-version` has already
gated it. This PR should contain no changes of its own.

Automated on merge — do not hand-check these:

- version metadata agreement, changelog entry present, semver shape
- production URLs baked in, no staging hostnames, no dev deps or junk in the zip
- `vendor/` contains only `botspot-prefixed/`
- official Plugin Check (`plugin_repo`), PHPCS, unit tests
- build reproducibility
- wp.org tag still free, git tag does not already exist
- commit is an ancestor of `main`

**Not automatable — confirm by hand:**

- [ ] Smoke test done on a **real** WordPress site (not Playground) with
      `WP_DEBUG` on: activate, connect, sync a post, view it on the front end,
      then deactivate and uninstall. No notices in `debug.log`.
      <!-- Playground has no real cron and no object cache, so it cannot cover
           cron retries, transient behaviour under caching, or multisite. -->
- [ ] `== Upgrade Notice ==` in `readme.txt` has an entry for this version.
      <!-- This is the text WordPress shows in the update nag — the only release
           note most users read. CI does not check it. -->
- [ ] Screenshots in `assets/` still match what `readme.txt` describes, if the
      admin UI changed.
- [ ] The changelog entry describes user-visible impact, not internal
      refactoring.

Then:

- [ ] I understand the wp.org tag is **immutable** once published. A bad release
      is fixed by shipping a new version, never by re-tagging.

<!--
After merging you will be asked to approve the `wporg` environment. That is the
last reversible moment — everything before it (GCS versioned upload) can be
deleted, everything after it cannot.

Once wp.org has rebuilt the zip (up to 6h), run `just verify-published` to
activate the zip wp.org actually serves in a real WordPress and confirm it is
clean.

Full process, including what to do when a stage fails: RELEASING.md
-->
