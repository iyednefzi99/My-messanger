<?php
// Bootstrap de session + helpers d'authentification.
// A inclure avant toute sortie HTML.

// Longueur maximale d'un message, en caracteres. La colonne `message` est
// un TEXT (~65000 octets) : sans plafond applicatif, un seul envoi peut
// remplir l'ecran de tout le monde. Partagee entre la validation cote
// serveur (process.php) et l'attribut maxlength du formulaire (index.php).
const MESSAGE_MAX_LENGTH = 2000;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Le cookie n'est marque Secure que derriere HTTPS, sinon il serait
        // ignore en developpement local (XAMPP sert en http).
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_username() {
    return $_SESSION['username'] ?? null;
}

// Ouvre la session applicative. Regenere l'identifiant pour couper toute
// fixation de session posee avant le login.
function login_user($id, $username) {
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $id;
    $_SESSION['username'] = $username;
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Renvoie false si le jeton est absent ou ne correspond pas.
function csrf_check($token) {
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Redirige vers $page en propageant un message d'erreur affichable.
function redirect_error($page, $message) {
    header('Location: ' . $page . '?error=' . urlencode($message));
    exit();
}


// --- Limitation des tentatives de connexion -------------------------------

// Fenetre glissante, en minutes.
const LOGIN_WINDOW_MINUTES = 15;
// Seuil par compte vise : protege un compte precis du bruteforce.
const LOGIN_MAX_PER_USER = 5;
// Seuil par adresse IP, plus haut : protege du balayage de plusieurs comptes
// depuis une meme source, sans penaliser un reseau partage trop vite.
const LOGIN_MAX_PER_IP = 20;

// On s'en tient a REMOTE_ADDR. X-Forwarded-For est fourni par le client et
// donc falsifiable : s'y fier laisserait contourner la limite par IP en
// changeant d'en-tete a chaque requete. Derriere un vrai reverse proxy, il
// faudra lire cet en-tete uniquement s'il vient d'un proxy de confiance.
function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_failed_login($con, $username, $ip) {
    $query = "INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, NOW())";
    $stmt  = mysqli_prepare($con, $query);
    if (!$stmt) {
        error_log('Prepare failed (record_failed_login): ' . mysqli_error($con));
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $username, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Efface l'ardoise du compte apres une connexion reussie.
function clear_login_attempts($con, $username) {
    $stmt = mysqli_prepare($con, "DELETE FROM login_attempts WHERE username = ?");
    if (!$stmt) {
        error_log('Prepare failed (clear_login_attempts): ' . mysqli_error($con));
        return;
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Purge opportuniste : evite que la table grossisse indefiniment sans
// imposer une tache planifiee. Se declenche sur ~1 % des tentatives.
function purge_old_login_attempts($con) {
    if (random_int(1, 100) !== 1) {
        return;
    }
    mysqli_query($con, "DELETE FROM login_attempts
        WHERE attempted_at < DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)");
}

// Renvoie le nombre de minutes de blocage restantes, ou 0 si la tentative
// est autorisee.
function login_lockout_minutes($con, $username, $ip) {
    // Le reste a courir est calcule par MySQL. Le lire via strtotime() cote
    // PHP reinterpreterait la chaine dans le fuseau de PHP, qui n'est pas
    // forcement celui du serveur SQL : un decalage d'une heure suffit a
    // annoncer n'importe quoi a l'utilisateur.
    $query = "SELECT
                SUM(username = ?) AS by_user,
                SUM(ip = ?)       AS by_ip,
                CEIL(TIMESTAMPDIFF(SECOND, NOW(),
                     MIN(attempted_at) + INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE) / 60) AS remaining
              FROM login_attempts
              WHERE (username = ? OR ip = ?)
                AND attempted_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)";
    $stmt = mysqli_prepare($con, $query);
    if (!$stmt) {
        error_log('Prepare failed (login_lockout_minutes): ' . mysqli_error($con));
        // En cas de panne du compteur, on laisse passer plutot que de
        // verrouiller tout le monde dehors.
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ssss', $username, $ip, $username, $ip);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row || $row['remaining'] === null) {
        return 0;
    }
    if ((int) $row['by_user'] < LOGIN_MAX_PER_USER && (int) $row['by_ip'] < LOGIN_MAX_PER_IP) {
        return 0;
    }

    // Plancher a 1 : la derniere seconde de blocage doit rester un blocage,
    // pas un « reessayez dans 0 minute ».
    return max((int) $row['remaining'], 1);
}
