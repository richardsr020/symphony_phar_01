# Migrations Symphony

Les migrations sont executees automatiquement au demarrage quand `DB_AUTO_MIGRATE=true`.

## Format d'une migration

- Un fichier PHP dans ce dossier, prefixe par un timestamp pour garder l'ordre.
- Le fichier doit retourner:
  - soit un `callable`
  - soit un tableau avec la cle `up` (callable) et optionnellement `description`.

Exemple:

```php
<?php

use App\Core\MigrationContext;

return [
    'description' => 'Mon changement de schema',
    'up' => static function (MigrationContext $context): void {
        $context->addColumnIfNotExists('ma_table', 'ma_colonne', 'TEXT NULL');
    },
];
```

## Suivi des migrations

- Les migrations appliquees sont enregistrees dans la table `schema_migrations`.
- Seules les migrations non encore appliquees sont executees.
- Si le contenu d'une migration deja appliquee change, l'execution est stoppee pour proteger l'integrite.
