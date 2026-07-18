<?php
// Endpoint JSON consomme par js/script.js.
// Renvoie les messages dont l'id est superieur a ?after=, c'est-a-dire
// uniquement ce que le client n'a pas encore : le polling ne retelecharge
// pas toute la conversation a chaque tour.

include 'auth.php';
include 'database.php';

header('Content-Type: application/json; charset=utf-8');
// Reponse propre a chaque appel : sans ca, un proxy ou le cache navigateur
// peut resservir un ancien lot et figer la conversation.
header('Cache-Control: no-store');

// Pas de redirection ici : le client attend du JSON, pas une page de login.
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit();
}

// Plafond volontaire. Au premier chargement (after=0) une conversation
// ancienne renverrait sinon des milliers de lignes d'un coup.
const MESSAGES_PAGE_SIZE = 50;

$after = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT);
if ($after === false || $after === null || $after < 0) {
    $after = 0;
}

$query = "SELECT m.id, m.time, m.message, COALESCE(u.username, m.user) AS author
          FROM messange m
          LEFT JOIN users u ON m.user_id = u.id
          WHERE m.id > ?
          ORDER BY m.id
          LIMIT " . MESSAGES_PAGE_SIZE;

$stmt = mysqli_prepare($con, $query);
if (!$stmt) {
    error_log('Prepare failed (messages): ' . mysqli_error($con));
    http_response_code(503);
    echo json_encode(['error' => 'unavailable']);
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $after);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id'      => (int) $row['id'],
        'time'    => $row['time'],
        'author'  => $row['author'],
        'message' => $row['message'],
    ];
}
mysqli_stmt_close($stmt);

// Aucun echappement HTML ici : le JSON transporte le texte brut et c'est
// le client qui l'insere via textContent. Echapper des deux cotes
// afficherait des &amp;lt; dans la page.
// page_size accompagne la reponse pour que le client sache reconnaitre un
// lot plein et enchainer, sans dupliquer la constante de son cote.
echo json_encode([
    'messages'  => $messages,
    'page_size' => MESSAGES_PAGE_SIZE,
]);
