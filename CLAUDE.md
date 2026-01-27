# CLAUDE.md

This file provides guidance for Claude Code when working with this repository.

## Project Overview

WordPress multisite installation for christoph-daum.de using [Bedrock](https://roots.io/bedrock/) architecture. The project uses Composer for dependency management and GitHub Actions for automated deployments to Plesk shared hosting.

## Architecture

### Directory Structure

```
├── config/                    # WordPress configuration
│   ├── application.php        # Main config (loads .env)
│   └── environments/          # Environment-specific overrides
├── repos/                     # Local dev only - cloned theme/plugin repos
│   ├── sovereignty/           # Theme repo clone
│   └── custom-plugin/         # Plugin repo clone
├── web/                       # Document root (point Plesk here)
│   ├── app/                   # wp-content equivalent
│   │   ├── mu-plugins/        # Must-use plugins
│   │   ├── plugins/           # Symlinks (dev) or Composer-installed (prod)
│   │   ├── themes/            # Symlinks (dev) or Composer-installed (prod)
│   │   ├── uploads/           # Media uploads (gitignored)
│   │   └── sunrise.php        # Multisite domain handling
│   ├── wp/                    # WordPress core (via Composer)
│   ├── index.php              # WordPress entry point
│   └── wp-config.php          # Loads Bedrock config
├── vendor/                    # Composer dependencies
├── composer.json
├── setup-dev.sh               # Script to set up local development
└── .env                       # Environment config (not in git)
```

### Multisite Configuration

- Type: Subdomain-based (e.g., blog.christoph-daum.de, shop.christoph-daum.de)
- Main domain: christoph-daum.de
- Requires wildcard DNS: *.christoph-daum.de → server IP

## Theme and Plugin Management

### Sovereignty Theme

Repository: https://github.com/apermo/sovereignty

- Managed via Composer, pinned to release tags
- Development: Clone to `repos/sovereignty/`, symlinked to `web/app/themes/`
- Production: Installed by Composer during deployment

### Custom Plugins

Custom plugins are separate repositories, managed the same way as sovereignty:

1. Create plugin repo on GitHub (public)
2. Add proper `composer.json` with `type: wordpress-plugin`
3. Add VCS repository and require in `composer.json`
4. Add clone + symlink commands to `setup-dev.sh`
5. Add to `.gitignore`

Example composer.json for a custom plugin:
```json
{
  "name": "apermo/plugin-name",
  "type": "wordpress-plugin",
  "require": {
    "composer/installers": "^2.0"
  }
}
```

### Third-Party Plugins

Install via Composer from wpackagist:
```bash
composer require wpackagist-plugin/plugin-name
```

## Development Setup

```bash
# Clone this repository
git clone git@github.com:apermo/chrdm.de.git
cd chrdm.de

# Install Composer dependencies
composer install

# Set up local development (clones repos, creates symlinks)
./setup-dev.sh

# Configure environment
cp .env.example .env
# Edit .env with your settings
```

### Working on Theme/Plugins

After running `setup-dev.sh`, theme and plugin repos are in `repos/`:

```bash
# Work on sovereignty theme
cd repos/sovereignty
git checkout -b feature/my-change
# make changes...
git commit -m "feat: add feature"
git push origin feature/my-change

# Work on custom plugin
cd repos/my-plugin
# same workflow...
```

Changes are made in their respective repos. After merging and tagging a release:
```bash
# Update this project to use new version
composer update apermo/sovereignty
git add composer.lock
git commit -m "chore: update sovereignty to v1.1.0"
git push
# Triggers deployment
```

## Development Commands

```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Add a WordPress plugin from wpackagist
composer require wpackagist-plugin/plugin-name

# Run PHP linting
composer test
```

## Environment Configuration

Copy `.env.example` to `.env` and configure:

- Database credentials
- WordPress URLs
- Authentication salts (generate at https://roots.io/salts.html)
- Environment type (`WP_ENV`: development, staging, production)

## Deployment

### GitHub Secrets Required

| Secret | Description |
|--------|-------------|
| `DEPLOY_HOST` | Server hostname or IP |
| `DEPLOY_USER` | SSH username |
| `DEPLOY_KEY` | SSH private key |
| `DEPLOY_PORT` | SSH port (usually 22) |
| `DEPLOY_PATH` | Absolute path to web directory |

### Deployment Process

1. Push to `main` branch triggers deployment
2. GitHub Actions runs `composer install` (installs WP core, themes, plugins)
3. rsync deploys to production server
4. Files excluded from sync: `.env`, `web/app/uploads/`, `web/.htaccess`

### Plesk Document Root

Set document root to the `web/` subdirectory:
1. Log into Plesk
2. Go to Websites & Domains → domain → Hosting Settings
3. Set Document root to: `httpdocs/web` (or your path + `/web`)

## Coding Standards

### Commits

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add new feature
fix: resolve bug
docs: update documentation
style: formatting, missing semicolons, etc.
refactor: code restructuring
test: add tests
chore: maintenance tasks
```

Keep commits atomic - one logical change per commit.

### PHP

- PHP 8.3 required
- Follow WordPress Coding Standards where applicable
- Use strict typing where possible

## WordPress CLI

WP-CLI is available on the server. Run commands from the project root:

```bash
# List sites in multisite
wp site list

# Activate a plugin network-wide
wp plugin activate plugin-name --network

# Clear cache
wp cache flush
```

## Troubleshooting

### Common Issues

**White screen / 500 error:**
- Check `web/app/debug.log` for PHP errors
- Verify `.env` file exists and has correct values
- Ensure document root points to `web/`

**Composer install fails:**
- Ensure GitHub token is configured for private repos
- Check PHP version matches requirements
- Verify theme/plugin repos have tagged releases

**Multisite not working:**
- Verify wildcard DNS is configured
- Check `SUBDOMAIN_INSTALL` is true in `.env`
- Ensure `sunrise.php` exists in `web/app/`

**Symlinks not working:**
- Run `./setup-dev.sh` to recreate symlinks
- On Windows, run terminal as Administrator
