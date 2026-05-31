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
- Prefer self-hosted, cookieless alternatives. Treat regressions here as high severity.

## Configuration & Bedrock

- Application config lives in `config/application.php` and `config/environments/`; secrets come
  from `.env` (never committed — only `.env.example` is tracked).
- Flag any hard-coded secret, credential, salt, or environment-specific value that belongs in
  `.env`.
- `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` are intentional — flag changes that re-enable
  in-dashboard file editing or runtime file modification.

## Deployment & CI (`.github/workflows/`)

- The deploy is tag-triggered (`v*`) or manual dispatch; merges to `main` do **not** deploy.
  Flag changes that would auto-deploy on push.
- The deployed code tree is locked read-only (`555`/`444`), with only `web/app/uploads/`
  writable. Flag changes that broaden writable paths without justification, or that would let
  rsync `--delete` wipe runtime state (uploads, caches).
- `.env`, `web/app/uploads/`, and `web/.htaccess` must stay excluded from rsync. Flag removal of
  these exclusions.
- Flag secrets referenced outside GitHub Secrets, or secrets echoed into logs.

## PHP

- PHP 8.3 required. Use strict typing where possible.
- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/);
  PHPCS is configured via `phpcs.xml.dist`.
- All output must be escaped (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`).
- All user input must be sanitized and validated; nonce verification is required for form
  submissions.
- Use post-increment (`$i++`) over pre-increment (`++$i`).

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

- This project uses [Conventional Commits](https://www.conventionalcommits.org/) with a 50-char
  subject / 72-char body limit, enforced by git hooks and CI.
- Each commit should address a single concern and be atomic (cherry-pickable, revertable).
