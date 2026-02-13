#!/bin/bash
set -e

# Development setup script for chrdm.de
# Clones theme/plugin repos and creates symlinks for local development

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPOS_DIR="$SCRIPT_DIR/repos"
THEMES_DIR="$SCRIPT_DIR/web/app/themes"
PLUGINS_DIR="$SCRIPT_DIR/web/app/plugins"

# Create repos directory
mkdir -p "$REPOS_DIR"

echo "Setting up development environment..."

# Clone sovereignty theme if not exists
if [ ! -d "$REPOS_DIR/sovereignty" ]; then
    echo "Cloning sovereignty theme..."
    git clone git@github.com:apermo/sovereignty.git "$REPOS_DIR/sovereignty"
else
    echo "sovereignty already cloned, pulling latest..."
    git -C "$REPOS_DIR/sovereignty" pull
fi

# Remove Composer-installed version and create symlink
if [ -d "$THEMES_DIR/sovereignty" ] && [ ! -L "$THEMES_DIR/sovereignty" ]; then
    echo "Removing Composer-installed sovereignty..."
    rm -rf "$THEMES_DIR/sovereignty"
fi

if [ ! -L "$THEMES_DIR/sovereignty" ]; then
    echo "Creating symlink for sovereignty..."
    ln -s ../../../repos/sovereignty "$THEMES_DIR/sovereignty"
fi

# Add custom plugins here as needed:
# Example:
# if [ ! -d "$REPOS_DIR/my-plugin" ]; then
#     git clone git@github.com:apermo/my-plugin.git "$REPOS_DIR/my-plugin"
# fi
# if [ ! -L "$PLUGINS_DIR/my-plugin" ]; then
#     rm -rf "$PLUGINS_DIR/my-plugin" 2>/dev/null || true
#     ln -s ../../../repos/my-plugin "$PLUGINS_DIR/my-plugin"
# fi

echo ""
echo "Development setup complete!"
echo ""
echo "Repos cloned to: $REPOS_DIR"
echo "Symlinks created in themes/plugins directories"
echo ""
echo "To add a new plugin, edit this script and add clone + symlink commands."
