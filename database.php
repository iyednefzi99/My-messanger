<?php
// Depuis PHP 8.1, mysqli signale ses erreurs par exception et non plus par
// une valeur de retour fausse. On l'assume explicitement plutot que de le
// subir : sans filet, la moindre erreur SQL affiche une trace complete avec
// les chemins du serveur dans le navigateur.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Filet de securite global : tout ce qui n'est pas rattrape localement
// finit ici, journalise et remplace par un message neutre.
set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
    }
    die('Service unavailable, please try again later.');
});

// Les identifiants vivent dans config.php, hors du depot : les versionner
// obligerait chaque installation a partager ceux de la machine d'origine.
$config_file = __DIR__ . '/config.php';
if (!is_file($config_file)) {
    error_log('Missing config.php (copy config.example.php to config.php)');
    http_response_code(503);
    die('Service unavailable, please try again later.');
}

$config = require $config_file;
if (!isset($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name'], $config['timezone'])) {
    error_log('config.php is incomplete: expected db.host, db.user, db.pass, db.name and timezone');
    http_response_code(503);
    die('Service unavailable, please try again later.');
}

// Fuseau horaire : une seule source de verite, appliquee a PHP et a MySQL.
// Les laisser diverger a deja produit un bug (583de67) : une date ecrite
// par l'un et relue par l'autre se decalait d'une heure sans rien signaler.
try {
    $timezone = new DateTimeZone($config['timezone']);
} catch (Exception $e) {
    error_log('Invalid timezone in config.php: ' . $config['timezone']);
    http_response_code(503);
    die('Service unavailable, please try again later.');
}
date_default_timezone_set($config['timezone']);

try {
    $con = mysqli_connect(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        $config['db']['name']
    );
} catch (mysqli_sql_exception $e) {
    error_log('Failed to connect to MySQL: ' . $e->getMessage());
    http_response_code(503);
    die('Service unavailable, please try again later.');
}

// Decalage numerique plutot que le nom du fuseau : les tables de fuseaux de
// MySQL ne sont pas peuplees par defaut sous XAMPP, et « SET time_zone =
// 'Africa/Tunis' » y echouerait. Le decalage est recalcule a chaque
// connexion, ce qui suit les changements d'heure saisonniers.
$offset = (new DateTime('now', $timezone))->format('P');
mysqli_query($con, "SET time_zone = '" . $offset . "'");
