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

## Plesk Setup

1. Set document root to `web/` subdirectory
2. Configure wildcard DNS for subdomains: `*.christoph-daum.de`
3. Create database and update `.env`

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
