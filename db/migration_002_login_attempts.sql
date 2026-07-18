-- Migration 002 : limitation des tentatives de connexion
-- Journalise les echecs de login pour bloquer le bruteforce.
-- Pas de cle etrangere vers `users` : les tentatives sur un identifiant
-- inexistant doivent aussi etre comptees (sinon l'attaquant contourne la
-- limite en visant un compte qui n'existe pas encore).

START TRANSACTION;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username_time` (`username`, `attempted_at`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
