<?php
// Bootstrap de session + helpers d'authentification.
// A inclure avant toute sortie HTML.

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


