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

### 🚀 Navigation Principale
- http://localhost/StarMarket/ - **Accueil** avec stats dynamiques
- http://localhost/StarMarket/items.php - **Catalogue** complet avec filtres
- http://localhost/StarMarket/browse.php - **Annonces** avec recherche avancée
- http://localhost/StarMarket/deals.php - **Bonnes Affaires** automatiques
- http://localhost/StarMarket/latest.php - **Dernières** annonces publiées

### 🔐 Authentification
- http://localhost/StarMarket/register.php - **Inscription** nouvelle
- http://localhost/StarMarket/login.php - **Connexion** membre

### 👤 Espace Membre (Après connexion)
- http://localhost/StarMarket/sell.php - **Créer** une annonce
- http://localhost/StarMarket/my-listings.php - **Gérer** ses annonces
- http://localhost/StarMarket/inbox.php - **Messagerie** conversations

### 🔍 Pages de Test Spécifiques
- http://localhost/StarMarket/item.php?id=1 - **Fiche** Aegis Avenger Titan
- http://localhost/StarMarket/profile.php?u=SarahTrader - **Profil** vendeur exemple
- http://localhost/StarMarket/browse.php?sale_type=IN_GAME - **Filtrer** annonces aUEC

## 🛠️ Maintenance et Troubleshooting

### ❌ Problèmes Courants

**"Erreur de connexion à la base de données"**
- ✅ Vérifiez que MySQL est démarré dans WAMP/XAMPP
- ✅ Contrôlez le mot de passe dans `db.php`
- ✅ Vérifiez que la base `starmarket` existe

**"Page blanche / erreur 500"**
- ✅ Activez l'affichage des erreurs PHP dans WAMP/XAMPP
- ✅ Vérifiez les permissions du dossier `uploads/`
- ✅ Contrôlez les logs Apache pour plus de détails

**"Images ne s'affichent pas"**
- ✅ Permissions du dossier `uploads/` (lecture/écriture)
- ✅ Vérifiez que le dossier existe : `StarMarket/uploads/`
- ✅ Testez l'upload d'une nouvelle image

**"Formulaires ne fonctionnent pas"**
- ✅ Sessions PHP activées (généralement par défaut)
- ✅ Cookies acceptés dans le navigateur
- ✅ JavaScript activé pour l'interactivité

### 🔧 Logs de Debug

**Localisation des logs :**
- **Apache :** `wamp64/logs/apache_error.log`
- **MySQL :** `wamp64/logs/mysql.log`
- **PHP :** Configuré via `php.ini`

**Activer le debug PHP :**
```php
// Ajouter en haut de db.php pour debug temporaire
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### 🚀 Optimisations Production

**Performance :**
- Activer le cache PHP OPcache
- Optimiser les images (WebP recommandé)
- Index MySQL sur les colonnes de recherche

**Sécurité :**
- Changer les mots de passe par défaut
- Configurer HTTPS obligatoire
- Limiter les tentatives de connexion
- Sauvegardes automatiques de la BDD

## 🌟 Fonctionnalités Avancées Incluses

### 🎨 Interface Utilisateur
- **Thème sombre** authentique Star Citizen
- **Responsive design** mobile-first optimisé
- **Animations CSS** fluides et modernes
- **JavaScript vanilla** sans dépendances lourdes
- **Icons contextuels** pour chaque action

### 🔄 Temps Réel
- **Compteurs non-lus** mis à jour automatiquement
- **Notifications** visuelles pour nouveaux messages
- **Auto-refresh** des conversations actives
- **Indicateurs de fraîcheur** sur les annonces récentes

### 📱 Adaptabilité
- **Mobile-first** : Interface optimisée tactile
- **Responsive grids** : Adaptation automatique écrans
- **Touch-friendly** : Boutons et liens adaptés mobile
- **Offline graceful** : Dégradation élégante sans JS

## 📞 Support et Documentation

### 🆘 Besoin d'Aide ?

**Pour des problèmes techniques :**
1. Vérifiez les **logs** Apache/MySQL/PHP
2. Consultez la section **Troubleshooting** ci-dessus
3. Testez sur un **environnement propre** WAMP/XAMPP

**Pour des questions fonctionnelles :**
- Parcourez ce README complet
- Testez avec les **comptes de démonstration**
- Explorez les **données seed** fournies

**Structure des fichiers détaillée :**
```
/StarMarket/
├── 📁 assets/          CSS, JS, images système
├── 📁 api/             Endpoints AJAX (JSON)
├── 📁 uploads/         Images uploadées users
├── 🗂️ *.php            Pages principales
├── 🗂️ *.sql            Scripts de base de données
└── 📄 README.txt       Cette documentation
```

---

## ✨ Conclusion

**StarMarket** est un marketplace complet et production-ready pour la communauté Star Citizen. 

### 🎯 Prêt pour :
- ✅ **Déploiement immédiat** sur WAMP/XAMPP
- ✅ **Utilisation communautaire** avec plusieurs centaines d'utilisateurs
- ✅ **Extension modulaire** avec nouvelles fonctionnalités
- ✅ **Maintenance facile** avec code documenté et structuré

### 🚀 Caractéristiques Uniques :
- **Double économie** (Argent réel + In-Game)
- **Bonnes affaires automatiques** avec intelligence de prix
- **Messagerie intégrée** avec gestion de RDV
- **Réputation communautaire** via système d'avis
- **Sécurité renforcée** contre les attaques courantes

**Bon trading dans l'univers de Star Citizen ! 🚀⭐**