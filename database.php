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

try {
    $con = mysqli_connect("localhost", "root", "", "messanger");
} catch (mysqli_sql_exception $e) {
    error_log('Failed to connect to MySQL: ' . $e->getMessage());
    http_response_code(503);
    die('Service unavailable, please try again later.');
}
