# chrdm.de - Code Review Style Guide

## Project Context

This is the WordPress multisite installation for christoph-daum.de, built on the
[Bedrock](https://roots.io/bedrock/) architecture. It is an **infrastructure / deployment repo**,
not a plugin or theme: the theme (`sovereignty`) and plugins are pulled in via Composer and
symlinked locally, so the code reviewed here is mostly configuration, the deploy workflow, and
glue. Deployment targets cPanel shared hosting via GitHub Actions on `v*` tags.

## Privacy — consent-free by design (hard constraint)

The site is **consent-free by design**: no non-essential cookies, no consent-requiring third
parties, no cookie banner. This is enforced by the stack (`statify`, `embed-privacy`,
`antispam-bee`, no ad/retargeting tags).

- Flag any change that introduces plugins, blocks, embeds, web fonts, or scripts that set
  non-essential cookies or require consent (e.g. Google Fonts loaded from Google, Google
  Analytics, Meta Pixel, unblocked third-party embeds).
- Flag changes that would sell or share personal data, or ignore Global Privacy Control
  (`Sec-GPC`), which the site honours by design.
- Prefer self-hosted, cookieless alternatives. Treat regressions here as high severity.

## Configuration & Bedrock

- Application config lives in `config/application.php` and `config/environments/`; secrets come
  from `.env` (never committed — only `.env.example` is tracked).
- Flag any hard-coded secret, credential, salt, or environment-specific value that belongs in
  `.env`.
- `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` are intentional — flag changes that re-enable
  in-dashboard file editing or runtime file modification.

## Deployment & CI (`.github/workflows/`)

- The deploy is tag-triggered (tags matching `v[0-9]+.[0-9]+.[0-9]+`) or manual dispatch; merges
  to `main` do **not** deploy. Flag changes that would auto-deploy on push, or that loosen the
  tag pattern to fire on non-release tags.
- The deploy runs **unlock → rsync → lock**: a pre-rsync step re-adds owner write (`chmod u+w`)
  to the code-tree dirs that the previous deploy locked to `555` so rsync can write; rsync syncs
  with a transient `--chmod=D755,F644`; a post-rsync step then locks the tree back down to `555`
  dirs / `444` files. Only `web/app/uploads/` stays writable (`755`/`644`) and `.env` is tightened
  to `600`. Flag changes that drop the unlock or lock step, broaden writable paths beyond
  `uploads/` without justification, or that would let rsync `--delete` wipe runtime state
  (uploads, caches).
- Known trade-off: with the code tree (including `web/`) at `555`, WordPress cannot rewrite
  `web/.htaccess` at runtime (e.g. on a permalink change) — that needs a manual unlock. Flag
  changes that silently widen permissions to work around this instead of handling it deliberately.
- `.env`, `web/app/uploads/`, and `web/.htaccess` must stay excluded from rsync. Flag removal of
  these exclusions.
- Flag secrets referenced outside GitHub Secrets, or secrets echoed into logs.

## PHP

- PHP 8.3 required. Use strict typing where possible.
- Code style is the **`Apermo` PHPCS ruleset** (`phpcs.xml.dist`, scanning `config/` and
  `web/app/mu-plugins/`) — WPCS-derived but with deliberate deviations. Do **not** flag these as
  errors: fully-qualified native calls in namespaced code (`\header()`), **no Yoda conditions**
  (`$x === 'y'`, not `'y' === $x`), and trailing commas after the last argument in multi-line
  calls.
- All output must be escaped (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`).
- All user input must be sanitized and validated; nonce verification is required for form
  submissions.
- Use post-increment (`$i++`) over pre-increment (`++$i`).

## Must-use plugins (`web/app/mu-plugins/`)

- Site-specific glue lives as **single-file** mu-plugins, each whitelisted individually in
  `.gitignore` (the directory is ignore-all + per-file allow). Flag a new mu-plugin that is not
  whitelisted (it would be silently untracked).
- Namespace must be **`Apermo\<Feature>`** — flag `Chrdm\…` or other vendor namespaces for
  consistency.
- Several implement [specification.website](https://specification.website) adoptions (security
  headers, `robots.txt` Content-Signal, hreflang `x-default`). The consent-free constraint above
  still applies.

## Code Style

- Flag inline comments that merely restate what the code does instead of explaining intent.
- Flag commented-out code.
- Do not flag docblocks — these may be required by coding standards even when self-explanatory.
- Flag new code that duplicates existing functionality in the repository.

## File Operations

- Flag files that appear to be deleted and re-added as new files instead of being moved/renamed
  (losing git history).

## Build & Packaging

- Flag newly added files or directories that are missing from build/packaging configs
  (`.gitignore`, `setup-dev.sh`, deploy workflow rsync includes/excludes, etc.).
- When a new theme or plugin is added, it should be Composer-managed and reflected in
  `composer.json`, `setup-dev.sh`, and `.gitignore` — flag partial additions.

## Documentation

- If a change affects setup, deployment, or environment behavior, flag missing updates to
  `README.md` or `CLAUDE.md`.

## Commits

- This project follows [Conventional Commits](https://www.conventionalcommits.org/) with a
  50-char subject / 72-char body limit (convention, not currently CI-enforced).
- Each commit should address a single concern and be atomic (cherry-pickable, revertable).
