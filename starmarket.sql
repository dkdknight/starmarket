-- StarMarket Database Schema
-- Création de la base de données
CREATE DATABASE IF NOT EXISTS starmarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE starmarket;

-- Table des utilisateurs avec système de rôles
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    role ENUM('USER', 'MODERATOR', 'ADMIN') DEFAULT 'USER',
    rating_avg DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    is_banned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des items du jeu
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    category ENUM('ARMOR','WEAPON','SHIP','PAINT','COMPONENT','OTHER') NOT NULL,
    source ENUM('INGAME','PLEDGE','BOTH') DEFAULT 'BOTH',
    description TEXT,
    image_url VARCHAR(500),
    manufacturer VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des variantes d'items (couleurs, skins)
CREATE TABLE item_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    color_name VARCHAR(50),
    color_hex VARCHAR(7),
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_variant (item_id, variant_name, color_name)
) ENGINE=InnoDB;

-- Table des annonces
CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    item_id INT NOT NULL,
    variant_id INT DEFAULT NULL,
    sale_type ENUM('REAL_MONEY','IN_GAME') NOT NULL,
    price_real DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'EUR',
    price_auec BIGINT DEFAULT NULL,
    region VARCHAR(50) DEFAULT NULL,
    meet_location VARCHAR(255) DEFAULT NULL,
    availability TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('ACTIVE','PAUSED','SOLD','REMOVED') DEFAULT 'ACTIVE',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des conversations
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    status ENUM('OPEN','SCHEDULED','DONE','DISPUTED','CLOSED') DEFAULT 'OPEN',
    meeting_details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conversation (listing_id, buyer_id)
) ENGINE=InnoDB;

-- Table des messages
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des avis
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rater_id INT NOT NULL,
    rated_id INT NOT NULL,
    listing_id INT NOT NULL,
    role ENUM('BUYER','SELLER') NOT NULL,
    stars TINYINT CHECK (stars >= 1 AND stars <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (rater_id, listing_id, role)
) ENGINE=InnoDB;

-- Table des prix de référence
CREATE TABLE price_reference (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    ref_price_real DECIMAL(10,2) DEFAULT NULL,
    ref_currency VARCHAR(10) DEFAULT 'EUR',
    ref_price_auec BIGINT DEFAULT NULL,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_price_ref (item_id)
) ENGINE=InnoDB;

-- Table de l'historique des prix
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    sale_type ENUM('REAL_MONEY','IN_GAME') NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table de watchlist
CREATE TABLE watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT DEFAULT NULL,
    variant_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_watchlist (user_id, item_id, variant_id)
) ENGINE=InnoDB;

-- Table des logs d'authentification
CREATE TABLE auth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    action ENUM('LOGIN','LOGOUT','REGISTER','FAILED_LOGIN') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des actions de modération
CREATE TABLE moderation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moderator_id INT NOT NULL,
    target_user_id INT DEFAULT NULL,
    target_listing_id INT DEFAULT NULL,
    action ENUM('BAN_USER','UNBAN_USER','REMOVE_LISTING','RESTORE_LISTING','DELETE_MESSAGE') NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (target_listing_id) REFERENCES listings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Index pour optimiser les performances
CREATE INDEX idx_listings_status ON listings(status);
CREATE INDEX idx_listings_sale_type ON listings(sale_type);
CREATE INDEX idx_listings_created_at ON listings(created_at);
CREATE INDEX idx_items_category ON items(category);
CREATE INDEX idx_items_source ON items(source);
CREATE INDEX idx_messages_conversation ON messages(conversation_id, created_at);
CREATE INDEX idx_reviews_rated ON reviews(rated_id);
CREATE INDEX idx_auth_logs_created ON auth_logs(created_at);