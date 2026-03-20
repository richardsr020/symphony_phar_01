#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUNDLE_DIR="$ROOT_DIR/production_bundle"

rm -rf "$BUNDLE_DIR"
mkdir -p "$BUNDLE_DIR"

cp "$ROOT_DIR/config.prod.php" "$BUNDLE_DIR/config.php"
cp "$ROOT_DIR/.htaccess.prod" "$BUNDLE_DIR/.htaccess"
cp "$ROOT_DIR/DEPLOY_PRODUCTION.md" "$BUNDLE_DIR/DEPLOY_PRODUCTION.md"

cat > "$BUNDLE_DIR/README.txt" <<'TXT'
Bundle de production prepare.

Contenu:
- config.php (base production)
- .htaccess (regles Apache production)
- DEPLOY_PRODUCTION.md (procedure)

Avant mise en ligne, completez les secrets:
- PROVIDER_ADMIN_PASSWORD
- PROVIDER_API_KEY
- PROVIDER_WEBHOOK_SECRET
- CRON_TOKEN
- AI_PROVIDERS['gemini']['api_key']
TXT

echo "Production bundle pret: $BUNDLE_DIR"
