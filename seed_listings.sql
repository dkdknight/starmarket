-- Listings de démonstration avec utilisateurs de test
USE starmarket;

-- Créer quelques utilisateurs de test (hashs pour mot de passe "password123")
INSERT INTO users (email, username, password_hash, bio, rating_avg, rating_count) VALUES
('john.doe@test.com', 'JohnSpacePilot', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pilote expérimenté, spécialisé dans les vaisseaux d\'exploration.', 4.8, 15),
('sarah.trader@test.com', 'SarahTrader', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Commerçante professionnelle, transactions sécurisées garanties.', 4.9, 23),
('mike.combat@test.com', 'MikeCombat', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Expert en combat, vend uniquement du matériel militaire.', 4.7, 8),
('anna.explorer@test.com', 'AnnaExplorer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Exploratrice passionnée, toujours en quête de nouveaux horizons.', 4.6, 12),
('alex.miner@test.com', 'AlexMiner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mineur professionnel, équipement de qualité uniquement.', 4.5, 19);

-- Listings REAL_MONEY (Argent réel)
INSERT INTO listings (seller_id, item_id, variant_id, sale_type, price_real, currency, region, notes, status) VALUES
(1, 1, NULL, 'REAL_MONEY', 75.00, 'EUR', 'EU', 'Aegis Avenger Titan neuf, jamais utilisé. Transaction via gifting RSI.', 'ACTIVE'),
(2, 6, NULL, 'REAL_MONEY', 260.00, 'EUR', 'EU', 'Mercury Star Runner avec LTI. Vente urgente, prix négociable.', 'ACTIVE'),
(1, 7, NULL, 'REAL_MONEY', 350.00, 'USD', 'NA', 'Constellation Phoenix avec tous les modules premium inclus.', 'ACTIVE'),
(3, 8, NULL, 'REAL_MONEY', 170.00, 'EUR', 'EU', 'Aegis Sabre parfait pour le combat PvP. Livraison immédiate.', 'ACTIVE'),
(4, 9, NULL, 'REAL_MONEY', 600.00, 'USD', 'NA', 'Carrack d\'exploration avec hangar Pisces inclus.', 'ACTIVE'),
(2, 4, 33, 'REAL_MONEY', 85.00, 'EUR', 'EU', 'Origin 325a variante Crimson, très rare et élégante.', 'ACTIVE'),
(5, 14, NULL, 'REAL_MONEY', 25.00, 'EUR', 'EU', 'Calico Jack Armor complet, style pirate authentique.', 'ACTIVE'),

-- Listings IN_GAME (aUEC)
INSERT INTO listings (seller_id, item_id, variant_id, sale_type, price_auec, meet_location, availability, notes, status) VALUES
(1, 11, 31, 'IN_GAME', 45000, 'Area18 - ArcCorp', 'Disponible tous les soirs 20h-23h CET', 'Armure ORC-mkX Desert en excellent état.', 'ACTIVE'),
(3, 15, NULL, 'IN_GAME', 8500, 'Port Olisar - Crusader', 'Weekends uniquement', 'C-54 SMG avec munitions incluses.', 'ACTIVE'),
(4, 16, NULL, 'IN_GAME', 12000, 'Lorville - Hurston', 'Flexible, contactez-moi', 'P4-AR excellent pour missions FPS.', 'ACTIVE'),
(5, 21, NULL, 'IN_GAME', 65000, 'New Babbage - microTech', 'Tous les jours 18h-22h', 'JS-300 Power Plant, performance garantie.', 'ACTIVE'),
(2, 12, 35, 'IN_GAME', 38000, 'Grim HEX - Yela', 'Sur RDV uniquement', 'Pembroke Arctic, parfait pour planètes froides.', 'ACTIVE'),
(1, 18, NULL, 'IN_GAME', 15500, 'Area18 - ArcCorp', 'Après 19h en semaine', 'Arrowhead Sniper, visée parfaite.', 'ACTIVE'),
(4, 30, NULL, 'IN_GAME', 95000, 'Port Tressler - microTech', 'Weekends préférés', 'Greycat ROC en parfait état, ideal mining.', 'ACTIVE'),
(3, 22, NULL, 'IN_GAME', 28000, 'Everus Harbor - Hurston', 'Soirs et weekends', 'Aurora Shield Generator T1, recharge rapide.', 'ACTIVE'),
(5, 17, NULL, 'IN_GAME', 18500, 'Port Olisar - Crusader', 'Journées disponible', 'Devastator Shotgun, destruction garantie.', 'ACTIVE'),
(2, 5, 36, 'IN_GAME', 1850000, 'Area18 - ArcCorp', 'Planning flexible', 'Cutlass Black Red Alert, configuration pirate.', 'ACTIVE');

-- Prix de référence pour calculer les bonnes affaires
INSERT INTO price_reference (item_id, ref_price_real, ref_currency, ref_price_auec) VALUES
(1, 85.00, 'EUR', NULL),  -- Avenger Titan
(6, 300.00, 'EUR', NULL), -- Mercury Star Runner  
(7, 400.00, 'USD', NULL), -- Constellation Phoenix
(8, 200.00, 'EUR', NULL), -- Sabre
(9, 700.00, 'USD', NULL), -- Carrack
(4, 95.00, 'EUR', NULL),  -- Origin 325a
(14, 35.00, 'EUR', NULL), -- Calico Jack Armor
(11, NULL, 'EUR', 55000), -- ORC-mkX Armor
(15, NULL, 'EUR', 12000), -- C-54 SMG
(16, NULL, 'EUR', 15000), -- P4-AR
(21, NULL, 'EUR', 75000), -- JS-300 Power Plant
(12, NULL, 'EUR', 45000), -- Pembroke Armor
(18, NULL, 'EUR', 18000), -- Arrowhead Sniper
(30, NULL, 'EUR', 110000), -- Greycat ROC
(22, NULL, 'EUR', 35000), -- Aurora Shield
(17, NULL, 'EUR', 22000), -- Devastator Shotgun
(5, NULL, 'EUR', 2100000); -- Cutlass Black

-- Historique des prix pour quelques items populaires
INSERT INTO price_history (item_id, sale_type, price, currency) VALUES
(1, 'REAL_MONEY', 80.00, 'EUR'),
(1, 'REAL_MONEY', 78.00, 'EUR'),
(1, 'REAL_MONEY', 75.00, 'EUR'),
(6, 'REAL_MONEY', 280.00, 'EUR'),
(6, 'REAL_MONEY', 270.00, 'EUR'),
(11, 'IN_GAME', 48000.00, 'aUEC'),
(11, 'IN_GAME', 46000.00, 'aUEC'),
(5, 'IN_GAME', 1900000.00, 'aUEC'),
(5, 'IN_GAME', 1875000.00, 'aUEC');