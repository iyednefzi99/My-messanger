<?php
include 'auth.php';
include 'database.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

if (isset($_POST['submit'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        redirect_error('register.php', 'Invalid session, please try again.');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        redirect_error('register.php', 'Please fill in a username and a password.');
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        redirect_error('register.php', 'Username must be 3-50 characters: letters, digits or underscore.');
    }
    if (strlen($password) < 8) {
        redirect_error('register.php', 'Password must be at least 8 characters.');
    }

    $query = "INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())";
    $stmt  = mysqli_prepare($con, $query);
    if (!$stmt) {
        error_log('Prepare failed: ' . mysqli_error($con));
        redirect_error('register.php', 'Service unavailable, please try again later.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, 'ss', $username, $hash);

    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_stmt_insert_id($stmt);
        mysqli_stmt_close($stmt);
        login_user($id, $username);
        header('Location: index.php');
        exit();
    }

    // 1062 = violation de la contrainte UNIQUE sur username.
    $errno = mysqli_stmt_errno($stmt);
    mysqli_stmt_close($stmt);
    if ($errno === 1062) {
        redirect_error('register.php', 'This username is already taken.');
    }
    error_log('Insert user failed: ' . $errno);
    redirect_error('register.php', 'Service unavailable, please try again later.');
}
?>
<!doctype html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>MESSANGER - Sign up</title>
        <link rel="icon" href="media/icons8-facebook-messenger-144.png" type="image/png">
        <link rel="stylesheet" href="css/style1.css">
    </head>
    <body>
        <div id="container">
            <div id="header">
                <img class="media" src="media/icons8-facebook-messenger-100.png" alt="MESSANGER">
                <h1>Create your account</h1>
            </div>

            <div id="send">
                <?php if (isset($_GET['error'])): ?>
                    <div class="error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form action="register.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="user-box">
                        <input type="text" name="username" required>
                        <label>Choose a username</label>
                    </div>
                    <div class="user-box">
                        <input type="password" name="password" required>
                        <label>Choose a password</label>
                    </div>
                    <input type="submit" name="submit" class="send-btn" value="Sign up">
                </form>
                <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </body>
</html>
