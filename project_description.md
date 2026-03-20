Très bien.
Voici la **version consolidée et professionnelle finale du cahier des charges de Symphony**, intégrant tes nouvelles exigences critiques.

---

# 📘 CAHIER DES CHARGES FINAL – SYMPHONY

**Système Comptable Intelligent avec Agent IA Omniprésent**

---

# 1️⃣ IDENTITÉ DU SYSTÈME

Nom du système : **Symphony**
Nom de l’agent IA : **Symphony**

Symphony est :

> Un système comptable automatisé doté d’un agent IA omniprésent, capable d’agir, d’analyser, de conseiller et de surveiller en continu l’activité financière de l’entreprise.

---

# 2️⃣ PRINCIPES FONDAMENTAUX DE L’AGENT IA

## 🔒 Confidentialité Absolue des Conversations

Symphony :

* ❌ Ne révèle jamais les anciennes conversations
* ❌ Ne fournit jamais l’historique d’un autre utilisateur
* ❌ Ne restitue jamais une conversation passée textuellement

Les conversations sont :

* Stockées pour mémoire interne
* Utilisées uniquement pour améliorer la cohérence
* Strictement liées au user_id
* Inaccessibles via interface

Même un Admin ne peut pas demander :

> “Montre-moi les conversations de Jean.”

C’est interdit par conception.

---

# 3️⃣ MÉMOIRE INTERNE IA

Table `ai_memory`

* id
* user_id
* context_summary
* last_activity
* internal_vector (optionnel futur)
* created_at

⚠ Cette mémoire est :

* Résumée
* Structurée
* Non brute
* Non exportable

---

# 4️⃣ IA EN SURVEILLANCE CONTINUE (MODE BACKEND LOOP)

Symphony fonctionne en permanence côté backend.

## Mécanisme :

* Cron job périodique (ex: toutes les 5 minutes)
* Analyse automatique transactions
* Analyse comportements
* Analyse anomalies fiscales
* Analyse schémas suspects

---

## 🛑 Détection d’activités suspectes

Exemples :

* Dépense anormalement élevée
* Transactions répétitives fractionnées
* Suppression fréquente d’écritures
* Modification TVA inhabituelle
* Variation brutale trésorerie

---

## En cas d’anomalie :

1. Création entrée dans `alerts`
2. Notification Admin
3. Log audit

Message exemple :

> “Une dépense inhabituelle de 15 000$ a été enregistrée. Cela dépasse votre moyenne habituelle.”

---

# 5️⃣ ARCHITECTURE IA OMNIPRÉSENTE

Symphony doit être :

* Accessible depuis toutes les pages
* Intégré au layout global
* Actif même sans interaction utilisateur

Il agit :

* À la demande
* De manière proactive
* En surveillance automatique

---

# 6️⃣ CONNAISSANCE FISCALE CONGOLAISE

Symphony doit intégrer la fiscalité congolaise.
Support linguistique : Francais et Anglais
L'agent IA doit parle la langue actuellement selectionnee dans le localstorage

---

## 📂 Fichier Fiscalité

Créer :

```
/app/ai_knowledge/fiscalite_congo.txt
```

Contenu :

* TVA RDC
* Impôt sur bénéfices
* Charges sociales
* Obligations déclaratives
* Pénalités fiscales
* Textes simplifiés

---

## Utilisation

Lorsqu’un utilisateur demande :

> “Combien dois-je payer d’impôt cette année ?”

Symphony :

1. Analyse données comptables
2. Consulte règles internes
3. Consulte fichier fiscalité
4. Applique règles
5. Fournit réponse contextualisée

---

# 7️⃣ STRUCTURE AGENT IA AVEC TOOLS MCP

Dossier :

```
/app/ai_tools/
```

Chaque action passe par un Tool.

L’IA n’a aucun accès direct base de données.

---

# 8️⃣ LIMITES STRICTES DE L’IA

Symphony ne peut jamais :

* Supprimer utilisateurs
* Modifier rôles
* Modifier sécurité système
* Accéder mots de passe
* Modifier logs
* Accéder configuration serveur
* Accéder fichiers système

Même si l’utilisateur le demande.

Réponse obligatoire :

> “Je ne suis pas autorisé à effectuer cette action.”

---

# 9️⃣ AUTOMATISATION MAXIMALE

Objectif :

Réduire actions manuelles.

Exemples :

* Catégorie détectée automatiquement
* TVA calculée automatiquement
* Suggestion client fréquent
* Prévision générée automatiquement
* Résumé mensuel automatique
* Alertes automatiques

Moins de boutons.
Plus d’intelligence.

---

# 🔟 FRONTEND JS DOMINANT

JavaScript doit :

* Gérer soumissions AJAX
* Mettre à jour tableaux dynamiquement
* Rafraîchir dashboard sans reload complet
* Gérer chat IA
* Gérer notifications alertes
* Gérer animations
* Gérer thème Light/Dark
* Gérer validation instantanée

---

# 11️⃣ SÉCURITÉ GLOBALE

### Protection :

* SQL Injection (PDO prepared)
* XSS
* CSRF
* Session hijacking
* Brute force login
* Contrôle permissions strict
* Logs audit obligatoires
* Tool whitelist IA obligatoire

---

# 12️⃣ TABLES SUPPLÉMENTAIRES

### alerts

* id
* type
* severity
* message
* created_at
* resolved

### ai_logs

* id
* user_id
* message
* tool_called
* result
* created_at

### ai_memory

* id
* user_id
* summary
* updated_at

---

# 13️⃣ COMPORTEMENT HUMAIN DE SYMPHONY

Symphony doit :

* Parler naturellement
* Expliquer simplement
* Ne pas utiliser jargon technique inutile
* Être pédagogique
* Être rassurant
* Être professionnel

Jamais robotique.
Jamais technique brut.

---

# 14️⃣ OBJECTIF FINAL

Symphony doit donner l’impression que :

> L’entreprise possède un directeur financier numérique intelligent, discret, vigilant et toujours actif.

---

# 🎯 RÉSUMÉ STRATÉGIQUE

Symphony =

* Système comptable
* Agent IA opérationnel
* Surveillant financier
* Conseiller fiscal congolais
* Assistant pédagogique
* Automatisation maximale
* Sécurité stricte
* Confidentialité totale


