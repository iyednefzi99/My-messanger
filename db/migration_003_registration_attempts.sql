-- Migration 003 : limitation des inscriptions
-- Journalise les inscriptions par adresse IP pour brider la creation
-- automatisee de comptes.
--
-- Table distincte de login_attempts : l'abus d'inscription se mesure par
-- IP seule (chaque compte cree porte un nom different, l'axe username n'a
-- pas de sens ici), la revalider dans la table de login melerait deux
-- logiques aux seuils differents.

START TRANSACTION;

CREATE TABLE `registration_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
