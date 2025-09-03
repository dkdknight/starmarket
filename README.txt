=== StarMarket - Installation et Configuration ===

## 🚀 Installation sur WAMP/XAMPP

### 1. Prérequis
- WAMP Server ou XAMPP avec PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- Extension PHP : PDO, PDO_MySQL, GD (pour les images)

### 2. Installation Rapide

1. **Copier les fichiers**
   - Copiez tous les fichiers dans `www/StarMarket/`
   - Structure attendue : `C:\wamp64\www\StarMarket\` ou `C:\xampp\htdocs\StarMarket\`

2. **Démarrer les services**
   - Lancez Apache et MySQL depuis WAMP/XAMPP
   - Vérifiez que les services sont bien démarrés (icônes vertes)

3. **Créer la base de données**
   - Accédez à phpMyAdmin : http://localhost/phpmyadmin
   - Créez une nouvelle base de données nommée `starmarket`
   - Encodage : `utf8mb4_unicode_ci`

4. **Importer les données**
   Importez les fichiers SQL dans l'ordre suivant :
   ```
   1. starmarket.sql      (Structure des tables + rôles)
   2. seed_items.sql      (30+ items Star Citizen avec variantes)
   3. seed_listings.sql   (Annonces de test + utilisateurs)
   ```

### 3. Configuration

**Configurer la connexion MySQL :**
1. Ouvrez `db.php`
2. Modifiez la ligne 6 :
   ```php
   $password = ''; // Remplacez par votre mot de passe MySQL root
   ```
3. Si vous utilisez un autre utilisateur MySQL :
   ```php
   $username = 'votre_utilisateur'; // Par défaut : 'root'
   ```

### 4. Permissions (Important)

**Windows :**
- Clic droit sur le dossier `uploads/` > Propriétés > Sécurité
- Ajouter les permissions d'écriture pour l'utilisateur IIS/Apache

**Linux/Mac :**
```bash
chmod 755 uploads/
chmod 644 uploads/*
```

### 5. Test d'Installation

1. **Accéder au site :** http://localhost/StarMarket/
2. **Vérifier l'affichage :** Page d'accueil avec statistiques
3. **Tester la connexion :** Utilisez un compte de test (voir ci-dessous)

**Pour créer un administrateur :**
1. Inscrivez-vous normalement sur le site
2. Dans phpMyAdmin, modifiez votre compte :
   ```sql
   UPDATE users SET role = 'ADMIN' WHERE username = 'votre_nom';
   ```

## 🌟 Fonctionnalités Implémentées

### 🔐 Système d'Authentification Complet
- ✅ Inscription sécurisée avec validation
- ✅ Connexion avec hachage bcrypt
- ✅ Gestion des rôles (USER/MODERATOR/ADMIN)
- ✅ Logs d'authentification automatiques
- ✅ Protection CSRF sur tous les formulaires

### 📦 Catalogue d'Items Star Citizen
- ✅ 30+ items officiels avec images RSI
- ✅ Système de variantes couleur/skin
- ✅ Recherche avancée et filtres multiples
- ✅ Catégories : Vaisseaux, Armures, Armes, Composants, Peintures
- ✅ Support des sources In-Game et Pledge Store

### 💰 Double Système de Vente
**Argent Réel (REAL_MONEY) :**
- ✅ Prix en EUR/USD/GBP avec région
- ✅ Système de "gifting" RSI
- ✅ Instructions vendeur personnalisées

**In-Game aUEC (IN_GAME) :**
- ✅ Prix en monnaie du jeu
- ✅ Lieux de RDV prédéfinis
- ✅ Gestion des créneaux de disponibilité

### 📈 Système de Bonnes Affaires
- ✅ Calcul automatique des réductions vs prix référence
- ✅ Deal Score avec pourcentages de réduction
- ✅ Tri par meilleures offres
- ✅ Badges visuels pour les promotions

### 💬 Messagerie Intégrée
- ✅ Conversations par annonce
- ✅ Messages temps réel avec compteurs non-lus
- ✅ Statuts de conversation (Ouvert/RDV/Terminé/Litige)
- ✅ Templates de messages pré-remplis
- ✅ Gestion des RDV avec détails

### ⭐ Système d'Avis et Réputation
- ✅ Notes 1-5 étoiles par transaction
- ✅ Avis séparés Acheteur/Vendeur
- ✅ Calcul automatique des moyennes
- ✅ Profils publics avec statistiques

### 🎨 Interface Utilisateur Avancée
- ✅ Thème sombre Star Citizen authentique
- ✅ Design responsive mobile-first
- ✅ Animations et transitions fluides
- ✅ JavaScript interactif sans frameworks
- ✅ Icons et badges contextuels

### 🛡️ Sécurité et Performance
- ✅ Requêtes préparées PDO anti-injection
- ✅ Upload d'images sécurisé avec validation
- ✅ Pagination optimisée pour grandes données
- ✅ Gestion d'erreurs robuste
- ✅ Validation côté serveur complète

### 🏠 Pages Publiques
- **index.php** : Accueil avec statistiques et dernières annonces
- **items.php** : Catalogue complet avec recherche/filtres avancés
- **item.php?id=X** : Fiche détaillée avec variantes et onglets dual-mode
- **browse.php** : Navigation des annonces avec tri et filtres
- **deals.php** : Bonnes affaires avec calcul automatique des réductions
- **latest.php** : Dernières annonces par ordre chronologique
- **profile.php?u=USERNAME** : Profils publics avec avis et statistiques

### 🔒 Pages Membres (Authentification Requise)
- **sell.php** : Création d'annonces avec formulaire intelligent
- **my-listings.php** : Gestion de toutes ses annonces
- **inbox.php** : Liste des conversations avec filtres
- **conversation.php?id=X** : Chat individuel avec gestion de statuts
- **contact-seller.php?listing_id=X** : Premier contact avec templates
- **watchlist.php** : Items suivis avec notifications

### 🔐 Authentification
- **register.php** : Inscription avec validation complète
- **login.php** : Connexion sécurisée avec redirection
- **logout.php** : Déconnexion et nettoyage session

## 🎯 Types de Transactions

### 💰 Argent Réel (REAL_MONEY)
- **Utilisation :** Système de "gifting" Star Citizen
- **Devises :** EUR, USD, GBP avec régions
- **Sécurité :** Mise en relation uniquement (pas de paiement intégré)
- **Fonctionnalités :**
  - Notes du vendeur personnalisées
  - Gestion des régions géographiques
  - Instructions de livraison flexibles

### 🎮 In-Game aUEC (IN_GAME)
- **Utilisation :** Monnaie du jeu avec RDV planifiés
- **Lieux :** Stations spatiales prédéfinies
- **Organisation :** Créneaux de disponibilité + messagerie
- **Fonctionnalités :**
  - Lieux de RDV standardisés
  - Gestion des horaires de disponibilité
  - Coordination via messagerie intégrée

## 🔥 Système de Bonnes Affaires

Le calcul intelligent compare automatiquement :
- **Prix des annonces** vs **Prix de référence du marché**
- **Deal Score** = (Prix Référence - Prix Annonce) / Prix Référence
- **Affichage** : Seules les annonces avec >10% de réduction
- **Classement** : Tri par pourcentage de réduction décroissant
- **Badges visuels** : Couleurs selon l'ampleur de la réduction

## 🛡️ Sécurité Intégrée

### 🔒 Protection des Données
- **Anti-injection SQL** : Requêtes préparées PDO partout
- **Protection CSRF** : Tokens sur tous les formulaires sensibles
- **Hachage bcrypt** : Mots de passe avec salt automatique
- **Validation serveur** : Double validation côté client/serveur

### 📤 Upload Sécurisé
- **Extensions** : jpg, jpeg, png, webp uniquement
- **Taille** : Limite 5MB par fichier
- **Validation** : Vérification des en-têtes d'images
- **Nommage unique** : Prévention des conflits de fichiers

### 👥 Gestion des Rôles
- **USER** : Fonctionnalités standard d'achat/vente
- **MODERATOR** : Outils de modération des annonces/utilisateurs
- **ADMIN** : Accès complet + gestion des modérateurs

## 📊 Statistiques et Analytics

### 📈 Tableaux de Bord
- **Accueil** : Stats globales du site en temps réel
- **Profils** : Métriques individuelles des vendeurs
- **Messagerie** : Compteurs de conversations non lues
- **Mes Annonces** : Statistiques détaillées par vendeur

### 🎯 Métriques Suivies
- Nombre de vues par annonce
- Taux de conversion contacts/ventes
- Moyennes des avis par utilisateur
- Activité des conversations

## 🌐 URLs de Test Essentielles

### Argent réel (REAL_MONEY)
- Prix en EUR/USD avec région (EU/NA)
- Notes du vendeur pour instructions
- Système de mise en relation uniquement

### In-Game (IN_GAME)
- Prix en aUEC (monnaie du jeu)
- Lieu de rendez-vous obligatoire
- Créneaux de disponibilité
- Système de messagerie pour coordination

## Système de bonnes affaires

Le calcul automatique compare les prix des annonces avec les prix de référence :
- Deal Score = (Prix Référence - Prix Annonce) / Prix Référence
- Seules les annonces avec Deal Score > 10% sont affichées
- Tri par pourcentage de réduction décroissant

## Sécurité

- Requêtes préparées PDO contre injection SQL
- Tokens CSRF sur tous les formulaires sensibles
- Hachage bcrypt des mots de passe
- Validation côté serveur de tous les inputs
- Upload d'images sécurisé avec vérification d'extension
- Logs d'authentification complets

## Images

Les items utilisent des URLs d'images officielles Star Citizen.
Pour ajouter des images personnalisées :
1. Uploadez dans le dossier uploads/
2. Utilisez edit_item_image.php pour associer à un item
3. Les images sont redimensionnées automatiquement

## URL de test utiles

- http://localhost/StarMarket/ - Accueil
- http://localhost/StarMarket/items.php - Catalogue
- http://localhost/StarMarket/deals.php - Bonnes affaires
- http://localhost/StarMarket/register.php - Inscription
- http://localhost/StarMarket/login.php - Connexion

## Support

Pour des questions techniques ou des bugs, vérifiez :
1. Les logs PHP dans WAMP/XAMPP
2. Les logs MySQL pour les erreurs de base de données
3. La console navigateur pour les erreurs JavaScript
4. Les permissions du dossier uploads/

## Structure des fichiers

/StarMarket/
├── config.php - Configuration globale
├── db.php - Connexion base de données
├── header.php / footer.php - Layout commun
├── index.php - Page d'accueil
├── auth/ - Pages d'authentification
├── pages/ - Pages principales du site
├── assets/ - CSS, JS, images
├── uploads/ - Fichiers uploadés
└── *.sql - Scripts de base de données

Le site est prêt pour la production avec quelques ajustements :
- Configurer un vrai serveur de mails
- Ajouter HTTPS obligatoire  
- Optimiser les images uploadées
- Mettre en place la sauvegarde automatique
- Configurer un système de cache