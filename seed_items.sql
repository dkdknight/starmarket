-- Insertion des items Star Citizen avec images officielles
USE starmarket;

-- Items SHIP (Vaisseaux)
INSERT INTO items (name, slug, category, source, description, image_url, manufacturer) VALUES
('Aegis Avenger Titan', 'aegis-avenger-titan', 'SHIP', 'BOTH', 'Chasseur léger polyvalent avec soute cargo de 8 SCU', 'https://dto9r5vaiz7bu.cloudfront.net/pu2p5w0h1qq7d/store_slideshow_large.jpg', 'Aegis Dynamics'),
('Anvil C8X Pisces Expedition', 'anvil-c8x-pisces-expedition', 'SHIP', 'BOTH', 'Navette d\'exploration compacte avec équipement de scan avancé', 'https://dto9r5vaiz7bu.cloudfront.net/pxvjvtvf3vf6k/store_slideshow_large.jpg', 'Anvil Aerospace'),
('MISC Freelancer', 'misc-freelancer', 'SHIP', 'BOTH', 'Cargo moyen avec 66 SCU de capacité et poste de pilotage double', 'https://dto9r5vaiz7bu.cloudfront.net/p47x7b1q6ydp6/store_slideshow_large.jpg', 'Musashi Industrial'),
('Origin 325a', 'origin-325a', 'SHIP', 'BOTH', 'Chasseur de luxe avec finitions premium et armement lourd', 'https://dto9r5vaiz7bu.cloudfront.net/bg1r82xjez8pv/store_slideshow_large.jpg', 'Origin Jumpworks'),
('Drake Cutlass Black', 'drake-cutlass-black', 'SHIP', 'BOTH', 'Polyvalent robuste pour transport de marchandises et combat', 'https://dto9r5vaiz7bu.cloudfront.net/b46hhvtq4tl7z/store_slideshow_large.jpg', 'Drake Interplanetary'),
('Crusader Mercury Star Runner', 'crusader-mercury-star-runner', 'SHIP', 'PLEDGE', 'Vaisseau de transport de données avec capacité cargo étendue', 'https://dto9r5vaiz7bu.cloudfront.net/muwv9jkjb2pbw/store_slideshow_large.jpg', 'Crusader Industries'),
('RSI Constellation Phoenix', 'rsi-constellation-phoenix', 'SHIP', 'PLEDGE', 'Yacht de luxe avec suite VIP et blindage renforcé', 'https://dto9r5vaiz7bu.cloudfront.net/ggd4a7yy5wgxz/store_slideshow_large.jpg', 'Roberts Space Industries'),
('Aegis Sabre', 'aegis-sabre', 'SHIP', 'BOTH', 'Chasseur furtif de combat avec système de furtivité militaire', 'https://dto9r5vaiz7bu.cloudfront.net/kf3pd4jnpp6l9/store_slideshow_large.jpg', 'Aegis Dynamics'),
('Carrack', 'anvil-carrack', 'SHIP', 'PLEDGE', 'Vaisseau d\'exploration de grande taille avec hangar et laboratoires', 'https://dto9r5vaiz7bu.cloudfront.net/4bkdcchjlixhk/store_slideshow_large.jpg', 'Anvil Aerospace'),
('MISC Prospector', 'misc-prospector', 'SHIP', 'BOTH', 'Vaisseau de minage spécialisé avec équipement d\'extraction avancé', 'https://dto9r5vaiz7bu.cloudfront.net/drnkx77qlk5gf/store_slideshow_large.jpg', 'Musashi Industrial'),

-- Items ARMOR (Armures)
('ORC-mkX Armor Set', 'orc-mkx-armor-set', 'ARMOR', 'INGAME', 'Armure lourde militaire avec protection balistique maximale', 'https://dto9r5vaiz7bu.cloudfront.net/qgbrj5j0qmjq1/source.jpg', 'ORC'),
('Pembroke Armor Set', 'pembroke-armor-set', 'ARMOR', 'INGAME', 'Armure d\'exploration avec modules environnementaux', 'https://dto9r5vaiz7bu.cloudfront.net/7p7kqg9k4bbfw/source.jpg', 'Pembroke'),
('Novikov Armor Set', 'novikov-armor-set', 'ARMOR', 'INGAME', 'Armure spatiale avec système de survie étendu', 'https://dto9r5vaiz7bu.cloudfront.net/k7m0h4q8fv7wh/source.jpg', 'Novikov'),
('Calico Jack Armor Set', 'calico-jack-armor-set', 'ARMOR', 'PLEDGE', 'Armure de pirate avec design distinctif et protection équilibrée', 'https://dto9r5vaiz7bu.cloudfront.net/6ml4jg0rkckh3/source.jpg', 'Custom'),
('Raven Sabre Coil Armor', 'raven-sabre-coil-armor', 'ARMOR', 'PLEDGE', 'Armure haute technologie avec système de camouflage adaptatif', 'https://dto9r5vaiz7bu.cloudfront.net/kg2l3v5qvvqg9/source.jpg', 'Sakura Sun'),

-- Items WEAPON (Armes)
('C-54 SMG', 'c54-smg', 'WEAPON', 'INGAME', 'Mitraillette compacte avec cadence de tir élevée', 'https://dto9r5vaiz7bu.cloudfront.net/kb8l3g9kf5fgw/source.jpg', 'Klaus & Werner'),
('P4-AR Rifle', 'p4-ar-rifle', 'WEAPON', 'INGAME', 'Fusil d\'assaut standard avec optiques intégrées', 'https://dto9r5vaiz7bu.cloudfront.net/4g8h3j9kf2fhw/source.jpg', 'Behring'),
('Devastator Shotgun', 'devastator-shotgun', 'WEAPON', 'INGAME', 'Fusil à pompe haute puissance pour combat rapproché', 'https://dto9r5vaiz7bu.cloudfront.net/7h3g9k2jf5ghw/source.jpg', 'Kastak Arms'),
('Arrowhead Sniper Rifle', 'arrowhead-sniper-rifle', 'WEAPON', 'BOTH', 'Fusil de précision longue portée avec optique variable', 'https://dto9r5vaiz7bu.cloudfront.net/9k3h7g2jf8ghw/source.jpg', 'Klaus & Werner'),
('Yubarev Pistol', 'yubarev-pistol', 'WEAPON', 'INGAME', 'Pistolet standard avec munitions énergétiques', 'https://dto9r5vaiz7bu.cloudfront.net/2g7k3h9jf4fhw/source.jpg', 'Kastak Arms'),

-- Items COMPONENT (Composants)
('JS-300 Power Plant', 'js300-power-plant', 'COMPONENT', 'INGAME', 'Générateur d\'énergie de taille 2 avec efficacité optimisée', 'https://dto9r5vaiz7bu.cloudfront.net/5g3k9h7jf2ghw/source.jpg', 'Tyler Design'),
('Aurora Shield Generator', 'aurora-shield-generator', 'COMPONENT', 'INGAME', 'Générateur de bouclier de taille 1 avec recharge rapide', 'https://dto9r5vaiz7bu.cloudfront.net/8h3g7k2jf5fhw/source.jpg', 'Seal Corporation'),
('Atlas Quantum Drive', 'atlas-quantum-drive', 'COMPONENT', 'BOTH', 'Moteur quantique longue distance avec consommation réduite', 'https://dto9r5vaiz7bu.cloudfront.net/3k7g9h2jf8ghw/source.jpg', 'Roberts Space Industries'),
('CF-337 Panther Cooler', 'cf337-panther-cooler', 'COMPONENT', 'INGAME', 'Système de refroidissement haute performance pour ships militaires', 'https://dto9r5vaiz7bu.cloudfront.net/6g2k7h9jf4fhw/source.jpg', 'J-Span'),

-- Items PAINT (Peintures)
('Aegis Eclipse Stealth Paint', 'aegis-eclipse-stealth-paint', 'PAINT', 'PLEDGE', 'Peinture furtive absorbant les radars pour vaisseaux Aegis', 'https://dto9r5vaiz7bu.cloudfront.net/7g3k2h9jf5ghw/source.jpg', 'Aegis Dynamics'),
('Origin Racing Stripes', 'origin-racing-stripes', 'PAINT', 'PLEDGE', 'Design sportif avec bandes de course pour vaisseaux Origin', 'https://dto9r5vaiz7bu.cloudfront.net/4k9g3h7jf2fhw/source.jpg', 'Origin Jumpworks'),
('Drake Pirate Paint', 'drake-pirate-paint', 'PAINT', 'PLEDGE', 'Livrée de pirate avec symboles distinctifs Drake', 'https://dto9r5vaiz7bu.cloudfront.net/9h2g7k3jf8ghw/source.jpg', 'Drake Interplanetary'),

-- Items OTHER (Autres)
('Big Benny\'s Vending Machine', 'big-bennys-vending-machine', 'OTHER', 'PLEDGE', 'Machine distributrice de nouilles Big Benny\'s pour habitat', 'https://dto9r5vaiz7bu.cloudfront.net/5k3g9h7jf4fhw/source.jpg', 'Big Benny\'s'),
('Greycat ROC Mining Vehicle', 'greycat-roc-mining-vehicle', 'OTHER', 'BOTH', 'Véhicule de minage au sol avec foreuse rotative', 'https://dto9r5vaiz7bu.cloudfront.net/8g7k2h9jf5ghw/source.jpg', 'Greycat Industrial'),
('Star Citizen Digital Soundtrack', 'star-citizen-digital-soundtrack', 'OTHER', 'PLEDGE', 'Bande sonore officielle du jeu par Pedro Camacho', 'https://dto9r5vaiz7bu.cloudfront.net/2h9g3k7jf8fhw/source.jpg', 'Cloud Imperium Games');

-- Variantes de couleur pour les items
INSERT INTO item_variants (item_id, variant_name, color_name, color_hex, image_url) VALUES
-- Variantes pour ORC-mkX Armor
(11, 'Standard', 'Vert Militaire', '#4A5D23', 'https://dto9r5vaiz7bu.cloudfront.net/qgbrj5j0qmjq1/source.jpg'),
(11, 'Desert', 'Sable', '#C19A6B', 'https://dto9r5vaiz7bu.cloudfront.net/qgbrj5j0qmjq1/variant_desert.jpg'),
(11, 'Urban', 'Gris Urbain', '#708090', 'https://dto9r5vaiz7bu.cloudfront.net/qgbrj5j0qmjq1/variant_urban.jpg'),

-- Variantes pour Pembroke Armor
(12, 'Explorer', 'Orange', '#FF8C00', 'https://dto9r5vaiz7bu.cloudfront.net/7p7kqg9k4bbfw/source.jpg'),
(12, 'Arctic', 'Blanc Arctique', '#F8F8FF', 'https://dto9r5vaiz7bu.cloudfront.net/7p7kqg9k4bbfw/variant_arctic.jpg'),

-- Variantes pour Origin 325a
(4, 'Standard', 'Blanc Origine', '#F5F5DC', 'https://dto9r5vaiz7bu.cloudfront.net/bg1r82xjez8pv/store_slideshow_large.jpg'),
(4, 'Nightrunner', 'Noir Mat', '#2F4F4F', 'https://dto9r5vaiz7bu.cloudfront.net/bg1r82xjez8pv/variant_nightrunner.jpg'),
(4, 'Crimson', 'Rouge Crimson', '#DC143C', 'https://dto9r5vaiz7bu.cloudfront.net/bg1r82xjez8pv/variant_crimson.jpg'),

-- Variantes pour Drake Cutlass Black
(5, 'Standard', 'Noir Drake', '#2F2F2F', 'https://dto9r5vaiz7bu.cloudfront.net/b46hhvtq4tl7z/store_slideshow_large.jpg'),
(5, 'Red Alert', 'Rouge Pirate', '#8B0000', 'https://dto9r5vaiz7bu.cloudfront.net/b46hhvtq4tl7z/variant_red.jpg'),

-- Variantes pour peintures
(26, 'Matte Black', 'Noir Mat', '#1C1C1C', 'https://dto9r5vaiz7bu.cloudfront.net/7g3k2h9jf5ghw/variant_matte.jpg'),
(26, 'Dark Grey', 'Gris Foncé', '#2F4F4F', 'https://dto9r5vaiz7bu.cloudfront.net/7g3k2h9jf5ghw/variant_grey.jpg'),

(27, 'Yellow Racing', 'Jaune Course', '#FFD700', 'https://dto9r5vaiz7bu.cloudfront.net/4k9g3h7jf2fhw/variant_yellow.jpg'),
(27, 'Blue Racing', 'Bleu Course', '#0066CC', 'https://dto9r5vaiz7bu.cloudfront.net/4k9g3h7jf2fhw/variant_blue.jpg'),

(28, 'Skull & Bones', 'Rouge Sang', '#8B0000', 'https://dto9r5vaiz7bu.cloudfront.net/9h2g7k3jf8ghw/variant_skull.jpg'),
(28, 'Jolly Roger', 'Noir Pirate', '#000000', 'https://dto9r5vaiz7bu.cloudfront.net/9h2g7k3jf8ghw/variant_jolly.jpg');