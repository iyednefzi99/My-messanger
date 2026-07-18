<?php
include 'auth.php';
include 'database.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

if (isset($_POST['submit'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        redirect_error('login.php', 'Invalid session, please try again.');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        redirect_error('login.php', 'Please fill in your username and password.');
    }

    $ip = client_ip();
    purge_old_login_attempts($con);

    // Verifie le quota avant de toucher au mot de passe : inutile de faire
    // travailler password_verify() pour une tentative deja bloquee.
    $lockout = login_lockout_minutes($con, $username, $ip);
    if ($lockout > 0) {
        redirect_error('login.php', 'Too many failed attempts. Please try again in ' . $lockout . ' minute(s).');
    }

    $query = "SELECT id, username, password_hash FROM users WHERE username = ?";
    $stmt  = mysqli_prepare($con, $query);
    if (!$stmt) {
        error_log('Prepare failed: ' . mysqli_error($con));
        redirect_error('login.php', 'Service unavailable, please try again later.');
    }
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Message volontairement identique dans les deux cas : distinguer
    // "utilisateur inconnu" de "mauvais mot de passe" permettrait d'enumerer
    // les comptes existants.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_login($con, $username, $ip);
        redirect_error('login.php', 'Incorrect username or password.');
    }

    clear_login_attempts($con, $username);
    login_user($user['id'], $user['username']);
    header('Location: index.php');
    exit();
}
?>
<!doctype html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>MESSANGER - Log in</title>
        <link rel="icon" href="media/icons8-facebook-messenger-144.png" type="image/png">
        <link rel="stylesheet" href="css/style1.css">
    </head>
    <body>
        <div id="container">
            <div id="header">
                <img class="media" src="media/icons8-facebook-messenger-100.png" alt="MESSANGER">
                <h1>Welcome back</h1>
            </div>

            <div id="send">
                <?php if (isset($_GET['error'])): ?>
                    <div class="error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form action="login.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="user-box">
                        <input type="text" name="username" required>
                        <label>Your username</label>
                    </div>
                    <div class="user-box">
                        <input type="password" name="password" required>
                        <label>Your password</label>
                    </div>
                    <input type="submit" name="submit" class="send-btn" value="Log in">
                </form>
                <p class="auth-switch">No account yet? <a href="register.php">Sign up</a></p>
            </div>
        </div>
    </body>
</html>
