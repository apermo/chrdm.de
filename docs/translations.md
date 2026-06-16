# Translations — GlotPress + Traduttore

Self-hosted translation platform for this project's **custom** code — the `sovereignty` theme and the
`apermo-stash` / `apermo-notify` plugins. Third-party plugins and WordPress core keep getting their
translations from wp.org; only our own packages are served from here.

## Architecture

```
GitHub push (apermo/sovereignty …)
     │  webhook → /wp-json/traduttore/v1/incoming-webhook  (shared secret)
     ▼
translate.chrdm.de   (multisite subsite, blog_id 6; theme: glotpress-blank, "/" 302s to /glotpress/)
   GlotPress   — translation UI + read-only TranslationsPress API at /glotpress/api/translations/<slug>
   Traduttore  — clone repo → wp i18n make-pot → import originals → build .mo/.po/.json language packs
     │  (TranslationsPress JSON)
     ▼
chrdm.de deploy: composer install
   inpsyde/wp-translation-downloader  ──(extra.wp-translation-downloader.api.names)──▶ fetches de_DE pack
   → web/app/languages/themes/  → deploy regenerates .l10n.php → performant-translations serves it
```

- **GlotPress** (`wpackagist-plugin/glotpress`) — the translation web UI and the read-only API. Mounted
  under `/glotpress/` (its `GP_URL_BASE` default).
- **Traduttore** (`wearerequired/traduttore`) — extracts strings on each push and builds the packs.
- **wp-translation-downloader** (`inpsyde/wp-translation-downloader`) — on the consumer site, downloads
  packs at `composer install` time.
- **performant-translations** — loads a compiled `.l10n.php` (faster than `.mo`).
- Locales: **`de_DE` only** today.

## Consumer wiring (the only translation code in this repo)

`composer.json` → `extra.wp-translation-downloader.api.names` maps each apermo package to the Traduttore
endpoint; everything else still comes from wp.org:

```json
"wp-translation-downloader": {
  "languages": ["de_DE"],
  "directory": "web/app/languages",
  "api": {
    "names": {
      "apermo/sovereignty":   "https://translate.chrdm.de/glotpress/api/translations/sovereignty",
      "apermo/apermo-stash":  "https://translate.chrdm.de/glotpress/api/translations/apermo-stash",
      "apermo/apermo-notify": "https://translate.chrdm.de/glotpress/api/translations/apermo-notify"
    }
  }
}
```

The endpoint includes the **`/glotpress/`** base — the bare `/api/translations/…` path is a 404. After
changing `api.names`, run `composer install` locally and commit `composer.lock` (the `extra` block only
bumps the lock's content-hash; no packages change). `wp-translation-downloader.lock` is gitignored —
the server regenerates it on deploy.

## Runtime consumption — Traduttore Registry (installs outside chrdm.de)

The `api.names` wiring above is **build-time and project-scoped**: it only delivers translations to a
project whose `composer.json` opts in (i.e. chrdm.de). A plugin/theme installed on **another site** —
manually, as a ZIP, or via a different Composer project — gets nothing from it.

For a package to fetch its **own** translations on **any** install, embed
[`wearerequired/traduttore-registry`](https://github.com/wearerequired/traduttore-registry) and register
its source at runtime. WordPress then treats the pack like a wp.org-hosted one: it appears under
Dashboard → Updates, downloads to `wp-content/languages/plugins/` (or `…/themes/`), and auto-updates
(~twice daily). The packs are the same Traduttore packs — only the *registration* differs.

In the plugin/theme code, on `init` (guarded so it degrades gracefully if the lib is absent):

```php
if ( function_exists( 'Required\\Traduttore_Registry\\add_project' ) ) {
    \Required\Traduttore_Registry\add_project(
        'plugin',                                                       // or 'theme'
        'apermo-stash',                                                 // must equal the slug
        'https://translate.chrdm.de/glotpress/api/translations/apermo-stash/'
    );
}
```

- **Bundle the library** (`composer require wearerequired/traduttore-registry`, then ship `vendor/`) for
  ZIP / non-Composer installs; a plain `require` of the project autoloader is enough where the host site
  is Composer-managed. Load the autoloader before the `add_project()` call.
- This is **runtime code in the package** — a deliberate trade-off vs. the build-time `api.names` model.
  The two can coexist (chrdm.de pulls at build time *and* the package self-registers everywhere else).

Tracked as #102 (apermo-stash), #103 (apermo-notify), #104 (template-wordpress scaffolding). Decide per
package by distribution: chrdm.de-only → `api.names` only; arbitrary installs → embed the registry;
wp.org-published → use translate.wordpress.org instead of self-hosting.

## Server configuration (on the translate subsite)

Set in production `.env` (read by `config/application.php`, which defines the constants guarded so they
only exist where the secret is set):

```
TRADUTTORE_GITHUB_SYNC_SECRET='<random; matches each repo's GitHub webhook secret>'
TRADUTTORE_WP_BIN='/usr/local/bin/wp'
```

A cron drains Traduttore's queue (its webhook schedules `traduttore.update` / `traduttore.generate_zip`
events on blog_id 6, which page visits alone won't reliably run):

```cron
*/5 * * * * cd /home/<user>/chrdm-bedrock && /usr/local/bin/wp cron event run --due-now --url=https://translate.chrdm.de --quiet
```

Two mu-plugins support the platform:

- `traduttore-content-dir.php` — stores language packs under `uploads/` (the deploy locks the rest of
  the tree read-only, and `uploads/` is the one writable, web-accessible location). Git clones use the
  system temp dir and are unaffected.
- The deploy itself regenerates `.l10n.php` (see Gotchas) — there is no longer a `.mo`-forcing mu-plugin.

## Onboard a new custom theme/plugin

1. **Create the GlotPress project** (UI — no CLI for this): `https://translate.chrdm.de/glotpress/projects/`
   → *New Project*. Slug **must equal** the short package name used in `api.names` (e.g. `apermo-stash`).
   Set *Source file URL* to `https://github.com/apermo/<repo>/blob/main/%file%#L%line%`.
2. **Create the `de_DE` translation set** (UI — no CLI): project → *New translation set*, locale
   German (`de`), slug `default`.
3. **Add the GitHub webhook** on the repo → payload `https://translate.chrdm.de/wp-json/traduttore/v1/incoming-webhook`,
   content type `application/json`, secret = `TRADUTTORE_GITHUB_SYNC_SECRET`, **push** event only.
   (REST lives at `/wp-json/`, *not* under `/glotpress/`.)
4. **Import originals**: `wp traduttore project update <slug> --url=https://translate.chrdm.de/`
   (clone → `make-pot` → import).
5. **Import an existing `.po`** (optional, to preserve prior work):
   `wp glotpress translation-set import <slug> de <file.po> --set=default --status=current --url=https://translate.chrdm.de/`.
6. **Build the pack**: `wp traduttore language-pack build <slug> --url=https://translate.chrdm.de/`,
   then check `https://translate.chrdm.de/glotpress/api/translations/<slug>` returns JSON with a
   `de_DE` package.
7. **Wire the consumer**: add the `api.names` entry, `composer install`, commit `composer.lock`, deploy.

## Push approved translations live

Translations reach the live site **only on a deploy** (the downloader runs during `composer install`):

1. Approve the strings in GlotPress.
2. Rebuild the pack: `wp traduttore language-pack build <slug> --url=https://translate.chrdm.de/`
   — or just push to the repo; the webhook rebuilds automatically.
3. Deploy: `gh workflow run deploy.yml -R apermo/chrdm.de` (a translations-only deploy needs no tag;
   it re-runs the downloader, regenerates `.l10n.php`, and flushes Cachify).

## Useful WP-CLI commands

All require `--url=https://translate.chrdm.de/` to target the subsite.

```bash
wp traduttore info                                   # environment: git/wp-cli paths, cache dir, versions
wp traduttore project info <slug>                    # repo, text domain, last update
wp traduttore project update <slug>                  # clone + make-pot + import originals
wp traduttore language-pack build <slug>             # (re)build packs
wp traduttore language-pack list <slug>              # locales, % complete, package URL
wp glotpress translation-set export <slug> de        # export current de translations (.po)
wp glotpress translation-set import <slug> de <file> # import .po (--status=current|waiting)
```

## Gotchas

- **Read-only deploy + `performant-translations`.** The deploy hardens the tree to `555/444`.
  performant-translations prefers a compiled `.l10n.php` over the `.mo`; a stale one would shadow a
  freshly deployed `.mo` and the site would serve old strings. The deploy's *Lock down* step therefore
  deletes and regenerates `.l10n.php` from the deployed `.po` (`wp i18n make-php`) before locking, so the
  cache is always fresh.
- **WP-CLI `--url` targeting.** `config/application.php` only sets the CLI `HTTP_HOST` fallback when it's
  empty (`empty($_SERVER['HTTP_HOST'])`), so `--url` actually selects a subsite. Without that guard every
  `wp` command would hit the main site.
- **GlotPress is under `/glotpress/`** (UI + API), but the **webhook is REST at `/wp-json/`**.
- **Project and translation-set creation are UI-only** — there is no `wp glotpress create-project`.
  Everything after creation (update / import / build) is CLI.
- **Advertised pack URLs use `christoph-daum.de`** (Bedrock pins `WP_CONTENT_URL` to the main domain);
  harmless, since every domain shares the docroot and the file is reachable.
