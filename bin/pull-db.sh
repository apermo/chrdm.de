#!/bin/bash
set -e

# Pull production database and import into local DDEV environment
# Usage: ./bin/pull-db.sh
#
# Requires these variables in .env:
#   PROD_SSH_USER, PROD_SSH_HOST, PROD_SSH_KEY, PROD_SSH_PATH

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# Load .env file
if [ -f .env ]; then
    export $(grep -E '^PROD_SSH_' .env | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

# Validate required variables
if [ -z "$PROD_SSH_USER" ] || [ -z "$PROD_SSH_HOST" ] || [ -z "$PROD_SSH_KEY" ] || [ -z "$PROD_SSH_PATH" ]; then
    echo "Error: Missing SSH configuration in .env"
    echo "Required: PROD_SSH_USER, PROD_SSH_HOST, PROD_SSH_KEY, PROD_SSH_PATH"
    exit 1
fi

# Expand tilde in SSH key path
PROD_SSH_KEY="${PROD_SSH_KEY/#\~/$HOME}"

echo "Exporting production database from $PROD_SSH_HOST..."
ssh -i "$PROD_SSH_KEY" "$PROD_SSH_USER@$PROD_SSH_HOST" "cd $PROD_SSH_PATH && wp db export --add-drop-table -" > /tmp/prod-db.sql

echo "Fixing MySQL 8 collation for MariaDB compatibility..."
sed -i.bak 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' /tmp/prod-db.sql

echo "Importing into local database..."
ddev import-db --file=/tmp/prod-db.sql

echo "Running search-replace for local development..."

# Main domain
ddev wp search-replace 'christoph-daum.de' 'cd-de.ddev.site' --network --all-tables --skip-columns=guid --quiet

# English domain
ddev wp search-replace 'christoph-daum.com' 'cd-com.ddev.site' --network --all-tables --skip-columns=guid --quiet

# Short domain (kb.chrdm.de etc)
ddev wp search-replace 'chrdm.de' 'cd-de.ddev.site' --network --all-tables --skip-columns=guid --quiet

# Content path (wp-content -> app for Bedrock)
ddev wp search-replace '/wp-content/uploads/' '/app/uploads/' --network --all-tables --skip-columns=guid --quiet

# Cleanup
rm -f /tmp/prod-db.sql /tmp/prod-db.sql.bak

echo ""
echo "Done! Local database updated from production."
echo ""
echo "Sites available:"
ddev wp site list --fields=blog_id,url --format=table 2>/dev/null | grep -v "Warning\|uninitialized" || true
