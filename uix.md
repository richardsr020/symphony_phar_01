

• 🎨 Style startup Silicon Valley
• 🧱 Flat design
• 🚫 Borderless containers
• 🌫 Légères ombres soft
• ⚪ Dominante blanc
• ⚫ Noir sur widgets
• 🌗 Light & Dark theme (mat, pas glossy)
• 📱 Ultra responsive



# 🎯 Direction UI/UX Complète pour ton SaaS Comptable IA

---

## 1️⃣ Identité Visuelle Globale

Style :

* Minimal
* Élégant
* Moderne
* Aéré
* Technologie premium
* Sérieux mais pas rigide

Inspiration visuelle :

* Stripe
* Linear
* Vercel
* Notion
* Ramp
* Brex

---

## 2️⃣ Palette Couleurs

### 🎨 Light Theme

* Background principal : #F8F9FB
* Surface widget : #FFFFFF
* Noir principal texte : #0F172A
* Gris texte secondaire : #64748B
* Couleur accent (choisir UNE dominante) :

Option recommandée :

* Indigo moderne → #4F46E5
  ou
* Bleu IA → #2563EB

---

### 🌑 Dark Theme (Mat)

* Background principal : #0E1117
* Surface widget : #161B22
* Texte principal : #E6EDF3
* Texte secondaire : #8B949E
* Accent identique au light mais légèrement désaturé

---

## 3️⃣ Containers Borderless

Tu ne mets PAS :

* border: 1px solid
* Encadrements visibles

Tu mets :

```css
background: white;
border-radius: 14px;
box-shadow: 0 6px 20px rgba(0,0,0,0.05);
```

Dark mode :

```css
background: #161B22;
box-shadow: 0 6px 20px rgba(0,0,0,0.4);
```

Ombres très légères.
Jamais trop fortes.

---

## 4️⃣ Layout SaaS Moderne

### Desktop

Sidebar gauche fine :

* Logo
* Dashboard
* Factures
* Transactions
* TVA
* Rapports
* IA Assistant

Zone principale :

* Widgets cartes
* Graphiques minimalistes
* Stats avec typographie large

---

### Mobile

* Sidebar devient menu burger
* Widgets empilés verticalement
* Bouton d’action flottant (FAB)

---

## 5️⃣ Typographie

Ultra important.

Recommandé :

* Inter
* Plus Jakarta Sans
* SF Pro (si possible)

Style :

* Titres semi-bold
* Chiffres financiers en font large
* Espacements généreux

---

## 6️⃣ Animations Modernes (subtiles)

Tu veux des animations mais PAS gadget.

Utilise :

* Fade in
* Slide up léger
* Hover soft
* Micro-interactions sur boutons

Exemple :

```css
transition: all 0.25s ease;
```

Hover :

```css
transform: translateY(-3px);
box-shadow: 0 10px 25px rgba(0,0,0,0.08);
```

---

## 7️⃣ Graphiques Style Startup

Pas de couleurs agressives.

* Courbes fines
* Zones en dégradé léger
* Pas de fond lourd

---

## 8️⃣ Expérience IA Premium

Assistant IA :

* Style chat moderne
* Bulles minimalistes
* Animation typing
* Suggestions intelligentes

---

## 9️⃣ UX Stratégique (Très Important)

Ton public :

PME africaines.

Donc :

* Boutons clairs
* Explications simples
* Icônes visibles
* Pas trop technique
* Tooltips pédagogiques

---

## 🔟 Détails qui font pro

✔ Skeleton loading
✔ Mode dark auto
✔ Toggle smooth theme
✔ Boutons avec états loading
✔ Scroll custom léger
✔ Icônes SVG minimalistes

---

# 💡 Conseil stratégique

Ton UI doit donner cette impression :

"Ce logiciel est plus intelligent que mon comptable."

Pour vos consignes UI/UX, voici une synthèse structurée de la stratégie de navigation par pile (Stack Navigation) adaptée à un dashboard comptable hybride :

L'application doit adopter une **navigation hiérarchique par pile (Stack Navigation)**, particulièrement sur mobile, afin de maximiser l'espace de travail lors de la consultation de données comptables denses. Chaque interaction profonde (ex: cliquer sur une ligne de facture pour voir son détail) doit "empiler" une nouvelle vue en plein écran par-dessus la liste principale. Cette transition doit être gérée via l'**API History du navigateur** (`pushState`) afin que le bouton "Retour" physique du smartphone ou du navigateur permette de "dépiler" la vue et de revenir exactement à l'état précédent de la liste, sans recharger la page.

Sur le plan visuel et adaptatif, la vue empilée doit se comporter comme un **panneau latéral (Side Drawer)** coulissant depuis la droite sur desktop, couvrant environ 40 à 50% de l'écran pour garder le contexte de la liste visible. Sur mobile, ce même composant doit automatiquement passer en **largeur 100% (Full-screen Overlay)** pour offrir un confort de lecture optimal. Les développeurs devront s'assurer que le focus du clavier et le défilement (scroll) sont capturés par la vue de dessus tant qu'elle n'est pas fermée, garantissant ainsi une ergonomie sans friction aussi bien à la souris qu'au tactile.

