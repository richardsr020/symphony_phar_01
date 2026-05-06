# Deploiement production - Symphony

Domaine cible: https://selleriewinner.ct.ws

## Bascule en production
1. Dans `config.php`, modifier uniquement:
   - `ENV` -> `production`
   - `SITE_URL` -> votre URL (ex: `https://selleriewinner.ct.ws`)
2. Renseigner les secrets dans `config.php`:
   - `PROVIDER_ADMIN_PASSWORD`
   - `PROVIDER_API_KEY`
   - `PROVIDER_WEBHOOK_SECRET`
   - `CRON_TOKEN`
   - `AI_PROVIDERS['gemini']['api_key']`
3. Verifier permissions ecriture:
   - `storage/sessions`
   - `storage/cache`
   - `storage/database`
   - `storage/logs`
4. Verifier que HTTPS redirige bien vers votre domaine.

## Conseils exploitation
- En production, l'initialisation auto du schema est desactivee (meme si `DB_AUTO_INIT=true` dans le fichier) pour eviter une re-init involontaire.
- Les migrations SQL applicatives sont lancees automatiquement via `DB_AUTO_MIGRATE=true` (uniquement les migrations non encore appliquees).
- Le bootstrap fournisseur est ignore en production.
- Garder une copie hors depot des mots de passe et tokens de production.
