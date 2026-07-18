-- Migration 001 : authentification
-- Ajoute la table `users` et rattache les messages a leur auteur.
-- Non destructif : la colonne `user` est conservee pour les messages
-- anterieurs a l'authentification (user_id restera NULL pour ceux-la).

START TRANSACTION;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `messange`
  ADD `user_id` int(11) DEFAULT NULL,
  ADD KEY `idx_user_id` (`user_id`),
  ADD CONSTRAINT `fk_messange_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL;

COMMIT;
