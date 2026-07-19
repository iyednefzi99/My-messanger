<?php
// Modele de configuration. Copier ce fichier en config.php et adapter les
// valeurs a l'environnement. config.php n'est pas suivi par git : chaque
// installation garde ses propres identifiants.
//
//   cp config.example.php config.php

return [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        // Valeur par defaut de XAMPP. A ne jamais laisser vide en production.
        'pass' => '',
        'name' => 'messanger',
    ],
    // Fuseau applique a la fois a PHP et a MySQL. Un identifiant de la base
    // de fuseaux PHP : https://www.php.net/manual/fr/timezones.php
    'timezone' => 'Africa/Tunis',
];
