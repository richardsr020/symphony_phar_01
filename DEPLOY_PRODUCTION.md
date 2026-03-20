# Deploiement production - Symphony

Domaine cible: https://selleriewinner.ct.ws

## Fichiers production crees
- `config.prod.php`
- `.htaccess.prod`

## Bascule en production
1. Sauvegarder les fichiers actuels:
   - `config.php` -> `config.dev.backup.php`
   - `.htaccess` -> `.htaccess.dev.backup`
2. Remplacer:
   - `config.prod.php` -> `config.php`
   - `.htaccess.prod` -> `.htaccess`
3. Renseigner les secrets dans `config.php`:
   - `PROVIDER_ADMIN_PASSWORD`
   - `PROVIDER_API_KEY`
   - `PROVIDER_WEBHOOK_SECRET`
   - `CRON_TOKEN`
   - `AI_PROVIDERS['gemini']['api_key']`
4. Verifier permissions ecriture:
   - `storage/sessions`
   - `storage/cache`
   - `logs`
5. Verifier que HTTPS et le domaine canonique redirigent bien vers:
   - `https://selleriewinner.ct.ws`

## Conseils exploitation
- En production, `DB_AUTO_INIT` est desactive pour eviter une initialisation schema involontaire.
- Les migrations SQL applicatives sont lancees automatiquement via `DB_AUTO_MIGRATE=true` (uniquement les migrations non encore appliquees).
- `PROVIDER_BOOTSTRAP_ENABLED` est desactive pour eviter de recreer un compte admin fournisseur automatiquement.
- Garder une copie hors depot des mots de passe et tokens de production.
