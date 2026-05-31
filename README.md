# christoph-daum.de

WordPress multisite installation using [Bedrock](https://roots.io/bedrock/).

## Requirements

- PHP 8.3+
- Composer 2.x
- MySQL 5.7+ / MariaDB 10.3+

## Quick Start

```bash
# Clone repository
git clone git@github.com:apermo/chrdm.de.git
cd chrdm.de

# Install dependencies
composer install

# Set up local development (clones theme/plugin repos, creates symlinks)
./setup-dev.sh

# Configure environment
cp .env.example .env
# Edit .env with your settings
```

## Local Development

Theme and plugins are managed as separate repositories. For local development, `setup-dev.sh` clones them to `repos/` and creates symlinks.

### Repos to Clone

| Repo | Location | Symlink Target |
|------|----------|----------------|
| [apermo/sovereignty](https://github.com/apermo/sovereignty) | `repos/sovereignty/` | `web/app/themes/sovereignty/` |

Run `./setup-dev.sh` to set these up automatically.

### Working on Theme/Plugins

```bash
cd repos/sovereignty
# make changes, commit, push to sovereignty repo
# after release, update composer.lock in this repo
```

## Deployment

Push to `main` branch triggers automatic deployment via GitHub Actions.

### Required GitHub Secrets

- `DEPLOY_HOST`: Server hostname
- `DEPLOY_USER`: SSH username
- `DEPLOY_KEY`: SSH private key
- `DEPLOY_PORT`: SSH port
- `DEPLOY_PATH`: Path to project on server

## cPanel Setup

1. Set document root to the `web/` subdirectory inside `DEPLOY_PATH`
   (e.g. `path/to/project/web` relative to the home dir)
2. Configure wildcard DNS for subdomains: `*.christoph-daum.de`
3. Create database and update `.env`

## Privacy — consent-free by design

This site sets **no non-essential cookies and loads no consent-requiring third parties**, so it
needs **no cookie-consent banner**. The stance is enforced by the plugin stack and a strict
no-tracking policy:

- **statify** — cookieless, aggregated analytics (no personal data, no tracking cookies).
- **embed-privacy** — blocks third-party embeds (YouTube, etc.) until the visitor opts in
  per-embed, so no third-party cookies load on page view.
- **antispam-bee** — privacy-friendly spam filtering with no external service.
- **No tracking tags** — no advertising, retargeting, or data-broker tags load anywhere.

Because nothing sells or shares personal data, Global Privacy Control (`Sec-GPC`) is honoured by
design. **Keep the site consent-free:** do not add plugins, embeds, fonts, or scripts that set
non-essential cookies or require consent without revisiting this decision (see
[CLAUDE.md](CLAUDE.md)).

## Documentation

See [CLAUDE.md](CLAUDE.md) for detailed development guidelines.

## Prerequisites for Theme/Plugins

Before `composer install` works, the sovereignty theme needs:

1. Updated `composer.json` with:
   ```json
   {
     "name": "apermo/sovereignty",
     "type": "wordpress-theme",
     "extra": {
       "installer-name": "sovereignty"
     }
   }
   ```
2. A tagged release (e.g., `v1.0.0`)
