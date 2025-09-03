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

## 👥 Comptes de Test Disponibles

**Tous les comptes utilisent le mot de passe :** `password123`

| Email | Nom d'utilisateur | Rôle | Rating | Spécialité |
|-------|------------------|------|--------|------------|
| john.doe@test.com | JohnSpacePilot | USER | 4.8/5 | Vaisseaux d'exploration |
| sarah.trader@test.com | SarahTrader | USER | 4.9/5 | Commerce professionnel |
| mike.combat@test.com | MikeCombat | USER | 4.7/5 | Équipement militaire |
| anna.explorer@test.com | AnnaExplorer | USER | 4.6/5 | Items d'exploration |
| alex.miner@test.com | AlexMiner | USER | 4.5/5 | Équipement de minage |

## Fonctionnalités principales

### Pages utilisateur
- index.php : Accueil avec dernières annonces et bonnes affaires
- items.php : Catalogue des items avec recherche et filtres
- item.php?id=X : Fiche détaillée d'un item avec variantes
- browse.php : Navigation des annonces avec filtres avancés
- deals.php : Bonnes affaires calculées automatiquement
- latest.php : Dernières annonces par ordre chronologique
- sell.php : Création d'annonces (connecté uniquement)
- inbox.php : Liste des conversations (connecté uniquement)
- conversation.php?id=X : Chat avec acheteur/vendeur
- profile.php?u=USERNAME : Profil public avec avis et statistiques

### Authentification
- register.php : Inscription avec validation complète
- login.php : Connexion sécurisée
- logout.php : Déconnexion et nettoyage session

### Administration
- Rôles : USER, MODERATOR, ADMIN
- Logs d'authentification automatiques
- Système de modération intégré (à développer)

## Types de transactions

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