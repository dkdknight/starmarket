=== StarMarket - Installation et Configuration ===

## Installation sur WAMP/XAMPP

### 1. Prérequis
- WAMP Server ou XAMPP avec PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- Extension PHP : PDO, PDO_MySQL

### 2. Installation

1. Copiez tous les fichiers dans votre dossier www/StarMarket/
2. Démarrez Apache et MySQL depuis WAMP/XAMPP
3. Accédez à phpMyAdmin (http://localhost/phpmyadmin)
4. Créez une base de données "starmarket"
5. Importez les fichiers SQL dans l'ordre :
   - starmarket.sql (structure des tables)
   - seed_items.sql (items et variantes)
   - seed_listings.sql (annonces de test)

### 3. Configuration

1. Ouvrez le fichier db.php
2. Modifiez la ligne : $password = ''; 
   Remplacez par votre mot de passe MySQL root
3. Si nécessaire, ajustez $host et $username selon votre configuration

### 4. Permissions

Assurez-vous que le dossier uploads/ est accessible en écriture :
- Windows : Clic droit > Propriétés > Sécurité > Modifier les permissions
- Linux : chmod 755 uploads/

### 5. Accès au site

Accédez à : http://localhost/StarMarket/

## Comptes de test disponibles

Les utilisateurs suivants sont créés avec le mot de passe "password123" :

- john.doe@test.com / JohnSpacePilot (Rating: 4.8/5)
- sarah.trader@test.com / SarahTrader (Rating: 4.9/5) 
- mike.combat@test.com / MikeCombat (Rating: 4.7/5)
- anna.explorer@test.com / AnnaExplorer (Rating: 4.6/5)
- alex.miner@test.com / AlexMiner (Rating: 4.5/5)

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