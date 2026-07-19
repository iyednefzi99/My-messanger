<?php
include __DIR__ . '/../src/auth.php';
include __DIR__ . '/../src/database.php';

// Deux clients possibles : le formulaire classique, qui attend une
// redirection, et js/script.js, qui attend du JSON. La page reste
// utilisable sans JavaScript, l'AJAX n'etant qu'une amelioration.
$wants_json = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

// Repond selon le client puis interrompt. Pour le formulaire, la
// redirection porte le message dans l'URL ; pour l'AJAX, un statut et un
// corps JSON.
function respond_error($wants_json, $message, $status = 400) {
    if ($wants_json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
        exit();
    }
    redirect_error('index.php', $message);
}

// require_login() redirige, ce qui donnerait une page HTML a fetch() : en
// AJAX on repond 401 pour que le client sache rediriger lui-meme.
if (!is_logged_in()) {
    respond_error($wants_json, 'not_authenticated', 401);
}

if(isset($_POST['submit'])){
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        respond_error($wants_json, 'Invalid session, please try again.');
    }

    // L'auteur vient de la session, jamais du formulaire : sinon n'importe
    // qui pourrait poster sous le nom d'un autre.
    $user_id = current_user_id();
    $user    = current_username();
    $message = trim($_POST['message'] ?? '');
    // Le fuseau est fixe une seule fois dans database.php, via config.php.
    $time = date("H:i:s");

    if ($message === ''){
        respond_error($wants_json, 'please fill in your message');
    }
    if (mb_strlen($message) > MESSAGE_MAX_LENGTH){
        // maxlength cote client peut etre contourne : on revalide ici.
        respond_error($wants_json, 'Message too long (max ' . MESSAGE_MAX_LENGTH . ' characters).');
    }

    $query = "INSERT INTO messange (user_id, user, message, time)
     VALUES (?, ?, ?, ?)";
    try {
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'isss', $user_id, $user, $message, $time);
        mysqli_stmt_execute($stmt);
        $id = mysqli_stmt_insert_id($stmt);
        mysqli_stmt_close($stmt);
    } catch (mysqli_sql_exception $e) {
        error_log('Insert message failed: ' . $e->getMessage());
        respond_error($wants_json, 'Service unavailable, please try again later.', 503);
    }

    if ($wants_json) {
        header('Content-Type: application/json; charset=utf-8');
        // Le message est renvoye tel qu'insere, avec son id : le client
        // l'affiche immediatement et avance son curseur, de sorte que le
        // prochain sondage ne le rapporte pas une seconde fois.
        echo json_encode(['message' => [
            'id'      => (int) $id,
            'time'    => $time,
            'author'  => $user,
            'message' => $message,
        ]]);
        exit();
    }

    header("Location: index.php");
    exit();
}
