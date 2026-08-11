Releasing BotSpot
=================

Merging a PR from `main` into `release` publishes the plugin. Nothing else does.

```
1. bump the version on main, in a normal PR
2. verify locally, and smoke-test on a real WordPress site
3. PR from main -> release, complete the checklist, merge
4. approve the `wporg` environment when GitHub asks
5. a few hours later: just verify-published
```

The `v<version>` git tag is created by CI at the **end**, on success — so a tag
means "this shipped", not "someone meant to ship this".


The one rule
------------

**WordPress.org tags are immutable.** Once `tags/3.5.15/` exists it cannot be
changed, deleted, or re-pointed, and sites with auto-updates begin pulling it
within hours. There is no force-push, no amend, no unpublish.

A bad release is fixed by **shipping the next version** — never by altering the
one already published. Do not edit files under `tags/` even to correct a comment:
wp.org caches and rebuilds from tags, so anyone who already updated never sees
the change.

Every gate below exists to protect that single moment, which is also why there
is a manual approval immediately before it.


We cannot reach a customer site
-------------------------------

No shell, no WP-CLI, no database. A fix that reads "run
`wp option delete bspt_foo` on the affected site" is not deliverable. Anything a
release has to undo on a site belongs in plugin code that runs on upgrade.
`Bspt_Activator::migrate_clear_stale_settings_lock()` is the pattern: guard on a
`bspt_migrated_*` option, do the work once, record the timestamp.

The same constraint decides how a release pairs with a locus-core deploy.


Ordering against locus-core
---------------------------

A release that depends on a core change ships **after** that core deploy, so the
plugin never calls an endpoint that returns 404. `register_webhook()` shows the
alternative: it falls back to `/api/v1/webhooks` on a 404, which lets the plugin
ship first.

Reverse the order when a core deploy makes older plugin builds behave worse.
BOT-348 is the case that established this. Core began dispatching
`settings.updated`, which every build up to 3.5.14 answers by writing
`bspt_settings_dashboard_locked` and locking its own settings screen. The
documented remedy was `wp option delete`, which the section above rules out.
Shipping the plugin first lets auto-updates carry sites past the build that sets
the lock.

Before deploying a core change that reaches the plugin, ask which released
builds receive it and what they do with it. `SELECT plugin_version, count(*) FROM
connectors GROUP BY 1` answers the first half when connectors report a version;
that table was empty on production on 2026-08-11, so the answer came from reading
the released tag instead:

```bash
git grep -n 'the_option_name' v3.5.14 -- '*.php'
```


1. Bump the version
-------------------

On a branch off `main`, as an ordinary reviewed PR. Six places:

| File | Field |
| --- | --- |
| `botspot.php` | ` * Version: X.Y.Z` header |
| `botspot.php` | `@version X.Y.Z` docblock |
| `botspot.php` | `define('BSPT_VERSION', 'X.Y.Z')` |
| `readme.txt` | `Stable tag: X.Y.Z` |
| `readme.txt` | `= X.Y.Z =` under `== Changelog ==` |
| `readme.txt` | `= X.Y.Z =` under `== Upgrade Notice ==` |

```bash
just check-version
```

CI blocks on the first four plus the changelog entry. It does **not** check
`== Upgrade Notice ==` — that is the text WordPress shows in the update nag, and
therefore the only release note most users ever read. Write one for anything
security- or behaviour-related.

Changelog entries should describe user-visible impact, not internal
refactoring.


2. Verify
---------

```bash
just verify-submission     # PHPCS, i18n, escaping, Plugin Check, runtime (~45s)
just check-reproducible    # two consecutive builds must be content-identical
```

CI runs both; running them locally just saves a round trip.
`verify-submission` includes the official WordPress.org Plugin Check, which is
what gates directory approval.

### The part nothing can automate

Install the built zip on a **real** WordPress site with `WP_DEBUG`,
`WP_DEBUG_LOG`, and `SCRIPT_DEBUG` enabled. Activate, connect with a real access
key, sync a post, view it on the front end, then deactivate and uninstall.
Confirm no notices in `debug.log`.

Every automated check runs in WordPress Playground, which has no real cron and
no object cache — it cannot cover cron retries, transient behaviour under
caching, or multisite. v3.5.12 shipped an activation fatal; this step is why.


3. PR from `main` to `release`
------------------------------

```bash
git log --oneline release..main    # what you are about to ship
gh pr create --base release --head main --title "Release X.Y.Z"
```

The PR template supplies the checklist. This PR should contain **no changes of
its own** — the version bump already landed on `main` and was reviewed there.
CI runs on the PR, so you see green before merging.


4. Merge, then approve
----------------------

Merging starts `release.yml`:

```
verify    reuses the full CI gate (static, package, plugin-check)
guard     version agreement, changelog, semver shape, git tag free,
          commit is an ancestor of main, wp.org tag still free
build     prod + staging zips, reproducibility check, GCS versioned upload
⏸  approval — the `wporg` GitHub Environment
wporg     svn trunk + tags/<version> in ONE commit      <-- irreversible
finalize  GCS latest/, git tag, GitHub Release
```

Reversible work happens first; the irreversible step sits behind the approval;
the mutable pointer customers pull from (`latest/`) moves last. If the wp.org
step fails, `latest/` still serves the previous version and no tag is created.

The approval pause is the last reversible moment. Everything before it can be
deleted; nothing after it can. Read the `guard` output before clicking.

Each wp.org commit makes them rebuild the zip for *every* version, which is why
a release is one commit rather than several.


5. Verify what shipped
----------------------

```bash
just svn-verify         # remote tags, revisions, API version, page status
just verify-published   # download wp.org's zip and activate it in real WordPress
```

`verify-published` is the one that matters: wp.org **rebuilds** the zip from
`tags/<version>/` with its own tooling, so it is a different artifact from
`dist/`. This is the only check that exercises what a customer installs.

It 404s until that rebuild completes — documented as up to 6 hours, though in
practice it has been minutes. A 404 shortly after release means "wait".


When something fails
--------------------

| Failed at | State | Do this |
| --- | --- | --- |
| `verify` / `guard` | nothing published | fix on `main`, PR again |
| `build` | GCS versioned path may hold a zip; nothing points at it | re-run; the upload overwrites |
| **`wporg`** | wp.org **may** be published | check first — see below |
| `finalize` | wp.org live, GCS `latest/` possibly stale | re-run with `skip_wporg: true` |

A step can fail *after* a successful `svn ci`, so always establish the truth
first:

```bash
just svn-verify    # is tags/<version>/ there?
```

- **Tag absent** — nothing published; fix and re-run normally.
- **Tag present** — that version is permanent. Do not attempt to correct it.
  Re-run via `workflow_dispatch` with `skip_wporg: true` to finish the GCS
  promotion, tag, and GitHub Release. If what shipped is broken, bump to the next
  patch version and ship again.

`skip_wporg` also skips the "wp.org tag must be free" guard, precisely so this
recovery path works.


Setup
-----

Per machine:

```bash
brew install svn        # not present on macOS by default
just svn-checkout       # sparse working copy at ../botspot-svn
```

Per repository, in **Settings > Environments** — the pipeline cannot publish
without this:

- An environment named `wporg`
- Required reviewers (this is what creates the approval pause)
- Secrets `SVN_USERNAME` and `SVN_PW`

`SVN_PW` is the SVN-specific password from your wordpress.org profile's
Account & Security page, **not** the wordpress.org login password. The username
is case-sensitive and is never an email address. Keeping these on the
environment rather than as repository secrets means no earlier job can read
them.


Manual fallback
---------------

If CI is unavailable. `svn-stage` pushes nothing and is the best way to see what
a release *would* do:

```bash
just svn-stage      # dry run: sync, stage, assert; commits nothing
just svn-publish    # trunk + tag in one commit
just release        # GCS only — deliberately does not tag
just tag            # then tag by hand
```

`just --list` shows the rest. The `wporg` group is all SVN-related; only
`svn-publish` writes to WordPress.org.


Gotchas
-------

- **`Stable tag` must name a tag that exists.** If it names a missing tag,
  wp.org silently serves `trunk/` instead — it mostly works, so the mistake can
  go unnoticed for a long time.
- **`assets/` is not in the plugin zip.** The banner, icon, and screenshots live
  only in SVN. The `screenshot-N.png` count must match the `== Screenshots ==`
  entries in `readme.txt`, or the public page renders captions with no images.
- **Deleting a file from disk does not delete it in SVN.** It needs `svn rm`;
  otherwise it stays in the repository. `just svn-stage` handles this.
- **The build must stay reproducible.** Strauss stamps a date into prefixed
  vendor files unless `extra.strauss.include_modified_date` is `false` in
  `composer.json`. Without it, `svn status` reports phantom modifications and
  real changes become indistinguishable from timestamps. CI asserts this.
- **`just release` does not tag** and does not touch WordPress.org. It is a
  GCS-only fallback.
