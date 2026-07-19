<?php
// Applique les migrations de db/ qui ne l'ont pas encore ete, dans l'ordre
// du nom de fichier, et note chacune dans la table schema_migrations.
//
//   php migrate.php              applique les migrations en attente
//   php migrate.php --status     liste l'etat sans rien appliquer
//   php migrate.php --baseline   marque les migrations en attente comme
//                                 appliquees SANS les executer, pour adopter
//                                 une base ou le schema existe deja
//
// Le schema de depart (db/messange.sql) n'est pas gere ici : il s'importe
// une fois a la creation de la base, avant le premier appel a ce script.

// Outil de maintenance en ligne de commande uniquement : l'exposer sur le
// web laisserait n'importe qui declencher des changements de schema.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$mode = $argv[1] ?? '--apply';
if (!in_array($mode, ['--apply', '--status', '--baseline'], true)) {
    fwrite(STDERR, "Usage: php migrate.php [--apply|--status|--baseline]\n");
    exit(2);
}

$config_file = __DIR__ . '/config.php';
if (!is_file($config_file)) {
    fwrite(STDERR, "config.php introuvable (copier config.example.php en config.php)\n");
    exit(1);
}
$config = require $config_file;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $con = mysqli_connect(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        $config['db']['name']
    );
} catch (mysqli_sql_exception $e) {
    fwrite(STDERR, 'Connexion impossible : ' . $e->getMessage() . "\n");
    exit(1);
}

// Table de suivi. Le nom de fichier fait office de cle : une migration
// renommee serait rejouee, ce qui est le comportement voulu (un nom est un
// identifiant, on n'en change pas apres coup).
mysqli_query($con, "CREATE TABLE IF NOT EXISTS schema_migrations (
    filename varchar(255) NOT NULL,
    applied_at datetime NOT NULL,
    PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$applied = [];
$res = mysqli_query($con, "SELECT filename FROM schema_migrations");
while ($row = mysqli_fetch_assoc($res)) {
    $applied[$row['filename']] = true;
}

$files = glob(__DIR__ . '/db/migration_*.sql');
sort($files); // tri lexicographique : migration_001, 002, 003...

$pending = [];
foreach ($files as $path) {
    $name = basename($path);
    if (!isset($applied[$name])) {
        $pending[] = $path;
    }
}

if ($mode === '--status') {
    echo "Appliquees :\n";
    echo $applied ? '  ' . implode("\n  ", array_keys($applied)) . "\n" : "  (aucune)\n";
    echo "En attente :\n";
    if ($pending) {
        foreach ($pending as $p) { echo '  ' . basename($p) . "\n"; }
    } else {
        echo "  (aucune)\n";
    }
    exit(0);
}

if (!$pending) {
    echo "Rien a faire : base a jour.\n";
    exit(0);
}

$stmt = mysqli_prepare($con, "INSERT INTO schema_migrations (filename, applied_at) VALUES (?, NOW())");

foreach ($pending as $path) {
    $name = basename($path);

    if ($mode === '--baseline') {
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        echo "baseline  $name (marquee sans execution)\n";
        continue;
    }

    $sql = file_get_contents($path);
    try {
        // multi_query : les fichiers contiennent plusieurs instructions.
        // Il faut vider tous les jeux de resultats, sinon la requete
        // suivante echoue avec « commands out of sync ».
        mysqli_multi_query($con, $sql);
        do {
            if ($result = mysqli_store_result($con)) {
                mysqli_free_result($result);
            }
        } while (mysqli_next_result($con));
    } catch (mysqli_sql_exception $e) {
        // On s'arrete a la premiere migration en echec : appliquer les
        // suivantes sur un schema a moitie migre aggraverait l'incoherence.
        fwrite(STDERR, "ECHEC sur $name : " . $e->getMessage() . "\n");
        fwrite(STDERR, "Migrations suivantes non appliquees.\n");
        exit(1);
    }

    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    echo "applique  $name\n";
}

echo "Termine.\n";
