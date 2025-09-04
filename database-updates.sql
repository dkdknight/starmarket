-- Mises à jour de la base de données pour les nouvelles fonctionnalités
USE starmarket;

-- Ajouter la colonne discord_user_id pour les notifications
ALTER TABLE users ADD COLUMN discord_user_id VARCHAR(20) DEFAULT NULL AFTER avatar_url;
ALTER TABLE users ADD COLUMN discord_notifications BOOLEAN DEFAULT TRUE AFTER discord_user_id;

-- Créer la table pour les avis en attente
CREATE TABLE IF NOT EXISTS pending_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    rater_id INT NOT NULL,
    rated_id INT NOT NULL,
    role ENUM('BUYER','SELLER') NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pending_review (conversation_id, rater_id, role)
) ENGINE=InnoDB;

-- Ajouter des index pour optimiser les performances temps réel
CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at);
CREATE INDEX idx_messages_read_status ON messages(conversation_id, is_read, sender_id);
CREATE INDEX idx_conversations_updated ON conversations(updated_at);
CREATE INDEX idx_users_discord ON users(discord_user_id);

-- Ajouter une table pour les logs de notifications Discord
CREATE TABLE IF NOT EXISTS discord_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    discord_user_id VARCHAR(20) NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Mettre à jour la table reviews pour lier avec pending_reviews
ALTER TABLE reviews ADD COLUMN pending_review_id INT DEFAULT NULL AFTER listing_id;
ALTER TABLE reviews ADD FOREIGN KEY (pending_review_id) REFERENCES pending_reviews(id) ON DELETE SET NULL;

-- Ajouter un champ pour suivre les images manquantes
ALTER TABLE items ADD COLUMN image_status ENUM('OK', 'MISSING', 'BROKEN') DEFAULT 'OK' AFTER image_url;
ALTER TABLE item_variants ADD COLUMN image_status ENUM('OK', 'MISSING', 'BROKEN') DEFAULT 'OK' AFTER image_url;

-- Créer une table pour l'historique des images
CREATE TABLE IF NOT EXISTS image_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT DEFAULT NULL,
    variant_id INT DEFAULT NULL,
    old_image_url VARCHAR(500),
    new_image_url VARCHAR(500),
    uploaded_by INT NOT NULL,
    action ENUM('UPLOAD', 'UPDATE', 'DELETE') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Créer une vue pour faciliter l'accès aux statistiques de messagerie
CREATE OR REPLACE VIEW user_message_stats AS
SELECT 
    u.id,
    u.username,
    COUNT(DISTINCT c.id) as total_conversations,
    COUNT(DISTINCT CASE WHEN c.status = 'OPEN' THEN c.id END) as open_conversations,
    COUNT(DISTINCT CASE WHEN c.status = 'DONE' THEN c.id END) as completed_conversations,
    COUNT(CASE WHEN m.sender_id != u.id AND m.is_read = FALSE THEN 1 END) as unread_messages,
    MAX(m.created_at) as last_message_time
FROM users u
LEFT JOIN conversations c ON (u.id = c.buyer_id OR u.id = c.seller_id)
LEFT JOIN messages m ON c.id = m.conversation_id
WHERE u.is_banned = FALSE
GROUP BY u.id, u.username;